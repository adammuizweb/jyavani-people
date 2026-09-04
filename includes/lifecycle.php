<?php
declare(strict_types=1);

function jyp_uninstall(string $name): void
{
    if ($name !== 'jyavani-people') return;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo instanceof PDO) throw new RuntimeException('People directory database is unavailable.');
    foreach ([JYP_ENTRY_TEXTS_TABLE, JYP_ENTRIES_TABLE, JYP_LINKS_TABLE, JYP_PROFILE_TERMS_TABLE, JYP_TERMS_TABLE, JYP_PROFILE_TEXTS_TABLE, JYP_PROFILES_TABLE] as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
    $statement = $pdo->prepare("DELETE FROM settings WHERE `key` LIKE ? ESCAPE '='");
    $statement->execute(['jyp=_%']);
}
