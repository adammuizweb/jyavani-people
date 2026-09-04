<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sql = (string)file_get_contents($root . '/migrations/0001-people-directory.sql');
$verification = (string)file_get_contents($root . '/migrations/0002-verify-schema.php');
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$condition) $failures[] = $message;
};

foreach (['jyp_profiles', 'jyp_profile_texts', 'jyp_terms', 'jyp_profile_terms', 'jyp_links', 'jyp_entries', 'jyp_entry_texts'] as $table) {
    $check(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `' . $table . '`'), 'migration creates ' . $table);
}
$check(str_contains($sql, '`source_locale`') && str_contains($sql, '`locale`'), 'schema is translation-ready from the first migration');
$check(str_contains($sql, '`year` SMALLINT UNSIGNED') && str_contains($sql, '`entry_type`'), 'typed profile entries support bounded year filtering');
$check(substr_count($sql, 'ON DELETE CASCADE') === 6, 'dependent profile data has explicit cleanup ownership');
$check(!preg_match('/\b(?:DROP|TRUNCATE|ALTER|RENAME|USE|LOCK|UNLOCK|SET)\b/i', $sql), 'initial migration contains no destructive or session-level SQL');
$check(!str_contains($sql, '\\'), 'migration contains no raw backslashes');
$check(str_contains($verification, 'information_schema.COLUMNS') && str_contains($verification, 'Jyavani People schema is incompatible'), 'follow-up migration rejects incompatible pre-existing tables');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " schema contract check(s) failed.\n");
    exit(1);
}
echo "Jyavani People schema contract passed.\n";
