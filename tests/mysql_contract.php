<?php
declare(strict_types=1);

$dsn = getenv('JYP_TEST_DSN');
if (!is_string($dsn) || $dsn === '') {
    echo "SKIP MySQL contract requires JYP_TEST_DSN.\n";
    exit(0);
}

$root = dirname(__DIR__);
$pdo = new PDO($dsn, getenv('JYP_TEST_USER') ?: null, getenv('JYP_TEST_PASS') ?: null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if (preg_match('/^jyp_contract_[a-z0-9_]+$/', $database) !== 1) {
    throw new RuntimeException('MySQL contract requires a dedicated jyp_contract_* database.');
}
$existingTables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn();
if ($existingTables !== 0) throw new RuntimeException('MySQL contract database must be empty.');

const JYP_PROFILES_TABLE = 'jyp_profiles';
const JYP_PROFILE_TEXTS_TABLE = 'jyp_profile_texts';
const JYP_TERMS_TABLE = 'jyp_terms';
const JYP_PROFILE_TERMS_TABLE = 'jyp_profile_terms';
const JYP_LINKS_TABLE = 'jyp_links';
const JYP_ENTRIES_TABLE = 'jyp_entries';
const JYP_ENTRY_TEXTS_TABLE = 'jyp_entry_texts';
require_once $root . '/includes/validation.php';
require_once $root . '/includes/repository.php';
require_once $root . '/includes/lifecycle.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$condition) $failures[] = $message;
};

try {
    $sql = (string)file_get_contents($root . '/migrations/0001-people-directory.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
    $verify = require $root . '/migrations/0002-verify-schema.php';
    $verify($pdo);
    $check(jyp_schema_ready($pdo), 'fresh MySQL schema passes structural readiness');

    $pdo->exec('CREATE TABLE settings (`key` VARCHAR(191) PRIMARY KEY, `value` TEXT) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO settings (`key`,`value`) VALUES ('jyp_owned','remove'),('jypXforeign','keep')");
    $pdo->exec("INSERT INTO jyp_profiles (slug,source_locale,status) VALUES ('sample-person','en','published')");
    $profileId = (int)$pdo->lastInsertId();
    $text = $pdo->prepare('INSERT INTO jyp_profile_texts (profile_id,locale,display_name,translation_status) VALUES (?,?,?,?)');
    $text->execute([$profileId, 'en', 'Sample Person', 'draft']);
    $check(jyp_profile_by_slug($pdo, 'sample-person') === null, 'draft source representation is not public');
    $pdo->exec("UPDATE jyp_profile_texts SET translation_status='published' WHERE profile_id=" . $profileId);
    $check(is_array(jyp_profile_by_slug($pdo, 'sample-person')), 'published source representation is public');

    $pdo->prepare('INSERT INTO jyp_links (profile_id,link_type,label,url) VALUES (?,?,?,?)')->execute([$profileId, 'website', 'Unsafe', 'javascript:alert(1)']);
    $pdo->prepare('INSERT INTO jyp_entries (profile_id,entry_type,external_url,status) VALUES (?,?,?,?)')->execute([$profileId, 'research', 'javascript:alert(1)', 'published']);
    $entryId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO jyp_entry_texts (entry_id,locale,title,translation_status) VALUES (?,?,?,?)')->execute([$entryId, 'en', 'Example Research', 'published']);
    $profile = jyp_profile_by_slug($pdo, 'sample-person');
    $check($profile['links'] === [], 'defensive reads suppress unsafe stored profile links');
    $check(($profile['entries'][0]['external_url'] ?? null) === '', 'defensive reads suppress unsafe stored entry links');

    $GLOBALS['pdo'] = $pdo;
    jyp_uninstall('jyavani-people');
    $check((string)$pdo->query("SELECT `value` FROM settings WHERE `key`='jypXforeign'")->fetchColumn() === 'keep'
        && $pdo->query("SELECT COUNT(*) FROM settings WHERE `key`='jyp_owned'")->fetchColumn() == 0,
        'complete uninstall removes only explicitly prefixed settings');
    $tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'jyp=%' ESCAPE '='")->fetchColumn();
    $check($tables === 0, 'complete uninstall removes every plugin-owned table');
} finally {
    foreach (['jyp_entry_texts','jyp_entries','jyp_links','jyp_profile_terms','jyp_terms','jyp_profile_texts','jyp_profiles','settings'] as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " MySQL contract check(s) failed.\n");
    exit(1);
}
echo "Jyavani People MySQL contract passed.\n";
