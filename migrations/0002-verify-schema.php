<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $required = [
        'jyp_profiles' => ['id', 'slug', 'source_locale', 'status', 'photo_url', 'public_email', 'staff_identifier', 'display_order', 'version', 'created_by', 'updated_by', 'created_at', 'updated_at'],
        'jyp_profile_texts' => ['profile_id', 'locale', 'display_name', 'credentials', 'position_title', 'organization_unit', 'headline', 'biography', 'translation_status', 'updated_at'],
        'jyp_terms' => ['id', 'taxonomy', 'slug', 'name', 'display_order'],
        'jyp_profile_terms' => ['profile_id', 'term_id'],
        'jyp_links' => ['id', 'profile_id', 'link_type', 'label', 'url', 'display_order', 'is_public'],
        'jyp_entries' => ['id', 'profile_id', 'entry_type', 'year', 'started_on', 'ended_on', 'external_url', 'display_order', 'status'],
        'jyp_entry_texts' => ['entry_id', 'locale', 'title', 'summary', 'translation_status'],
    ];
    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    foreach ($required as $table => $columns) {
        $statement->execute([$table]);
        $available = array_fill_keys(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
        foreach ($columns as $column) {
            if (!isset($available[$column])) throw new RuntimeException('Jyavani People schema is incompatible: ' . $table . '.' . $column);
        }
    }
    $tables = implode(',', array_fill(0, count($required), '?'));
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $tables . ") AND ENGINE='InnoDB'");
    $statement->execute(array_keys($required));
    if ((int)$statement->fetchColumn() !== count($required)) throw new RuntimeException('Jyavani People tables must use InnoDB.');

    foreach ([
        ['jyp_profiles', 'jyp_profiles_slug_unique', 0],
        ['jyp_profile_texts', 'PRIMARY', 0],
        ['jyp_terms', 'jyp_terms_taxonomy_slug_unique', 0],
        ['jyp_profile_terms', 'PRIMARY', 0],
        ['jyp_entry_texts', 'PRIMARY', 0],
    ] as [$table, $index, $nonUnique]) {
        $statement = $pdo->prepare('SELECT NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1');
        $statement->execute([$table, $index]);
        $actual = $statement->fetchColumn();
        if ($actual === false || (int)$actual !== $nonUnique) throw new RuntimeException('Jyavani People unique index is missing: ' . $table . '.' . $index);
    }

    $constraints = [
        'jyp_profile_texts_profile_fk', 'jyp_profile_terms_profile_fk',
        'jyp_profile_terms_term_fk', 'jyp_links_profile_fk',
        'jyp_entries_profile_fk', 'jyp_entry_texts_entry_fk',
    ];
    $placeholders = implode(',', array_fill(0, count($constraints), '?'));
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN (' . $placeholders . ") AND DELETE_RULE='CASCADE'");
    $statement->execute($constraints);
    if ((int)$statement->fetchColumn() !== count($constraints)) throw new RuntimeException('Jyavani People cascade ownership constraints are incomplete.');
};
