<?php
declare(strict_types=1);

function jyp_base_path(?PDO $pdo = null): string
{
    $configured = $pdo instanceof PDO && function_exists('settings_get')
        ? (string)settings_get($pdo, 'jyp_base_path', 'people')
        : 'people';
    return jyp_normalize_slug($configured) ?? 'people';
}

function jyp_assert_base_path_available(PDO $pdo, string $base): void
{
    if (function_exists('content_route_reserved_conflict') && content_route_reserved_conflict($pdo, $base) !== null) {
        throw new RuntimeException('Jyavani People base path conflicts with a protected route.');
    }
    $statement = $pdo->prepare("SELECT 1 FROM content_routes WHERE path=? OR path LIKE ? ESCAPE '=' LIMIT 1");
    $statement->execute([$base, str_replace(['=', '%', '_'], ['==', '=%', '=_'], $base) . '/%']);
    if ($statement->fetchColumn()) throw new RuntimeException('Jyavani People base path conflicts with an existing content route.');
    $statement = $pdo->prepare("SELECT 1 FROM posts WHERE slug=? AND is_deleted=0 AND type IN ('article','page','theme') LIMIT 1");
    $statement->execute([$base]);
    if ($statement->fetchColumn()) throw new RuntimeException('Jyavani People base path conflicts with existing content.');
}

function jyp_schema_ready(PDO $pdo): bool
{
    static $ready = [];
    $connection = spl_object_id($pdo);
    if (($ready[$connection] ?? false) === true) return true;
    try {
        $required = [
            JYP_PROFILES_TABLE => ['id', 'slug', 'source_locale', 'status', 'version'],
            JYP_PROFILE_TEXTS_TABLE => ['profile_id', 'locale', 'display_name', 'translation_status'],
            JYP_TERMS_TABLE => ['id', 'taxonomy', 'slug', 'name'],
            JYP_PROFILE_TERMS_TABLE => ['profile_id', 'term_id'],
            JYP_LINKS_TABLE => ['id', 'profile_id', 'url', 'is_public'],
            JYP_ENTRIES_TABLE => ['id', 'profile_id', 'entry_type', 'external_url', 'status'],
            JYP_ENTRY_TEXTS_TABLE => ['entry_id', 'locale', 'title', 'translation_status'],
        ];
        $statement = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        foreach ($required as $table => $columns) {
            $statement->execute([$table]);
            $available = array_fill_keys(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
            foreach ($columns as $column) if (!isset($available[$column])) return false;
        }
        $ready[$connection] = true;
        return true;
    } catch (Throwable $error) {
        error_log('[jyavani-people] schema check failed: ' . $error->getMessage());
        return false;
    }
}

function jyp_like_value(string $value): string
{
    return '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $value) . '%';
}

function jyp_profile_page(PDO $pdo, array $filters, int $page = 1, int $perPage = 18, bool $publicOnly = true): array
{
    $page = max(1, $page);
    $perPage = max(1, min(48, $perPage));
    $where = [];
    $parameters = [];
    if ($publicOnly) $where[] = "p.status = 'published'";
    if ($publicOnly) $where[] = "pt.translation_status = 'published'";
    $search = mb_substr(trim((string)($filters['search'] ?? '')), 0, 100, 'UTF-8');
    if ($search !== '') {
        $where[] = "(pt.display_name LIKE :search_name ESCAPE '=' OR pt.credentials LIKE :search_credentials ESCAPE '=' OR pt.position_title LIKE :search_position ESCAPE '=' OR pt.organization_unit LIKE :search_unit ESCAPE '=' OR pt.headline LIKE :search_headline ESCAPE '=' OR EXISTS (SELECT 1 FROM `" . JYP_PROFILE_TERMS_TABLE . "` ps JOIN `" . JYP_TERMS_TABLE . "` ts ON ts.id=ps.term_id WHERE ps.profile_id=p.id AND ts.name LIKE :search_term ESCAPE '='))";
        foreach ([':search_name', ':search_credentials', ':search_position', ':search_unit', ':search_headline', ':search_term'] as $placeholder) {
            $parameters[$placeholder] = jyp_like_value($search);
        }
    }
    $term = jyp_normalize_slug((string)($filters['term'] ?? ''));
    if ($term !== null) {
        $taxonomy = (string)($filters['taxonomy'] ?? '');
        if (!in_array($taxonomy, ['group', 'role', 'expertise', 'location'], true)) $taxonomy = '';
        $where[] = 'EXISTS (SELECT 1 FROM `' . JYP_PROFILE_TERMS_TABLE . '` ptr JOIN `' . JYP_TERMS_TABLE . '` tr ON tr.id = ptr.term_id WHERE ptr.profile_id = p.id AND tr.slug = :term' . ($taxonomy !== '' ? ' AND tr.taxonomy = :taxonomy' : '') . ')';
        $parameters[':term'] = $term;
        if ($taxonomy !== '') $parameters[':taxonomy'] = $taxonomy;
    }
    $status = (string)($filters['status'] ?? '');
    if (!$publicOnly && in_array($status, ['draft', 'published'], true)) {
        $where[] = 'p.status = :status';
        $parameters[':status'] = $status;
    }
    $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
    $join = ' FROM `' . JYP_PROFILES_TABLE . '` p JOIN `' . JYP_PROFILE_TEXTS_TABLE . '` pt ON pt.profile_id = p.id AND pt.locale = p.source_locale';
    $count = $pdo->prepare('SELECT COUNT(*)' . $join . ' WHERE ' . $whereSql);
    $count->execute($parameters);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $query = $pdo->prepare(
        'SELECT p.*, pt.display_name, pt.credentials, pt.position_title, pt.organization_unit, pt.headline, pt.biography'
        . $join . ' WHERE ' . $whereSql . ' ORDER BY p.display_order ASC, pt.display_name ASC, p.id ASC LIMIT :limit OFFSET :offset'
    );
    foreach ($parameters as $key => $value) $query->bindValue($key, $value);
    $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $query->execute();
    $rows = $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $termMap = $publicOnly ? jyp_profiles_terms_map($pdo, array_column($rows, 'id')) : [];
    $linkMap = $publicOnly ? jyp_profiles_links_map($pdo, array_column($rows, 'id')) : [];
    foreach ($rows as &$row) {
        $id = (int)$row['id'];
        jyp_normalize_public_profile($row);
        $row['terms'] = $termMap[$id] ?? [];
        $row['links'] = $linkMap[$id] ?? [];
    }
    unset($row);
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}

function jyp_profile(PDO $pdo, int $id, bool $publicOnly = false): ?array
{
    $statement = $pdo->prepare(
        'SELECT p.*, pt.display_name, pt.credentials, pt.position_title, pt.organization_unit, pt.headline, pt.biography'
        . ' FROM `' . JYP_PROFILES_TABLE . '` p JOIN `' . JYP_PROFILE_TEXTS_TABLE . '` pt ON pt.profile_id=p.id AND pt.locale=p.source_locale'
        . ' WHERE p.id=?' . ($publicOnly ? " AND p.status='published' AND pt.translation_status='published'" : '') . ' LIMIT 1'
    );
    $statement->execute([$id]);
    $profile = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($profile)) return null;
    return jyp_hydrate_profile($pdo, $profile, $publicOnly);
}

function jyp_profile_by_slug(PDO $pdo, string $slug): ?array
{
    $statement = $pdo->prepare(
        'SELECT p.*, pt.display_name, pt.credentials, pt.position_title, pt.organization_unit, pt.headline, pt.biography'
        . ' FROM `' . JYP_PROFILES_TABLE . '` p JOIN `' . JYP_PROFILE_TEXTS_TABLE . '` pt ON pt.profile_id=p.id AND pt.locale=p.source_locale'
        . " WHERE p.slug=? AND p.status='published' AND pt.translation_status='published' LIMIT 1"
    );
    $statement->execute([$slug]);
    $profile = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($profile) ? jyp_hydrate_profile($pdo, $profile, true) : null;
}

function jyp_hydrate_profile(PDO $pdo, array $profile, bool $publicOnly): array
{
    $id = (int)$profile['id'];
    if ($publicOnly) jyp_normalize_public_profile($profile);
    $profile['terms'] = jyp_profile_terms($pdo, $id);
    $profile['links'] = jyp_profile_links($pdo, $id, $publicOnly);
    $profile['entries'] = jyp_profile_entries($pdo, $id, (string)$profile['source_locale'], $publicOnly);
    return $profile;
}

function jyp_normalize_public_profile(array &$profile): void
{
    $photo = jyp_normalize_url((string)($profile['photo_url'] ?? ''));
    $profile['photo_url'] = $photo === null ? '' : $photo;
    $email = trim((string)($profile['public_email'] ?? ''));
    $profile['public_email'] = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
}

function jyp_profile_terms(PDO $pdo, int $profileId): array
{
    $statement = $pdo->prepare(
        'SELECT t.id,t.taxonomy,t.slug,t.name FROM `' . JYP_TERMS_TABLE . '` t JOIN `' . JYP_PROFILE_TERMS_TABLE . '` pt ON pt.term_id=t.id WHERE pt.profile_id=? ORDER BY t.taxonomy,t.display_order,t.name'
    );
    $statement->execute([$profileId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function jyp_profile_links(PDO $pdo, int $profileId, bool $publicOnly = true): array
{
    $statement = $pdo->prepare(
        'SELECT id,link_type,label,url,display_order,is_public FROM `' . JYP_LINKS_TABLE . '` WHERE profile_id=?'
        . ($publicOnly ? ' AND is_public=1' : '') . ' ORDER BY display_order,id'
    );
    $statement->execute([$profileId]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$publicOnly) return $rows;
    return array_values(array_filter(array_map(static function (array $row): ?array {
        $url = jyp_normalize_url((string)$row['url'], (string)$row['link_type'] === 'email');
        if ($url === null || $url === '') return null;
        $row['url'] = $url;
        return $row;
    }, $rows)));
}

function jyp_profiles_terms_map(PDO $pdo, array $profileIds): array
{
    $ids = array_values(array_filter(array_map('intval', $profileIds), static fn(int $id): bool => $id > 0));
    if ($ids === []) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare('SELECT pt.profile_id,t.id,t.taxonomy,t.slug,t.name FROM `' . JYP_PROFILE_TERMS_TABLE . '` pt JOIN `' . JYP_TERMS_TABLE . '` t ON t.id=pt.term_id WHERE pt.profile_id IN (' . $placeholders . ') ORDER BY t.taxonomy,t.display_order,t.name');
    $statement->execute($ids);
    $map = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $map[(int)$row['profile_id']][] = $row;
    }
    return $map;
}

function jyp_profiles_links_map(PDO $pdo, array $profileIds): array
{
    $ids = array_values(array_filter(array_map('intval', $profileIds), static fn(int $id): bool => $id > 0));
    if ($ids === []) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare('SELECT id,profile_id,link_type,label,url,display_order,is_public FROM `' . JYP_LINKS_TABLE . '` WHERE profile_id IN (' . $placeholders . ') AND is_public=1 ORDER BY display_order,id');
    $statement->execute($ids);
    $map = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $url = jyp_normalize_url((string)$row['url'], (string)$row['link_type'] === 'email');
        if ($url === null || $url === '') continue;
        $row['url'] = $url;
        $map[(int)$row['profile_id']][] = $row;
    }
    return $map;
}

function jyp_profile_entries(PDO $pdo, int $profileId, string $locale, bool $publicOnly = true): array
{
    $statement = $pdo->prepare(
        'SELECT e.*,et.title,et.summary FROM `' . JYP_ENTRIES_TABLE . '` e JOIN `' . JYP_ENTRY_TEXTS_TABLE . '` et ON et.entry_id=e.id AND et.locale=?'
        . ' WHERE e.profile_id=?' . ($publicOnly ? " AND e.status='published' AND et.translation_status='published'" : '')
        . ' ORDER BY e.entry_type,e.year DESC,e.display_order,e.id'
    );
    $statement->execute([$locale, $profileId]);
    $entries = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($entries as &$entry) {
        $url = jyp_normalize_url((string)($entry['external_url'] ?? ''));
        $entry['external_url'] = $url === null ? '' : $url;
    }
    unset($entry);
    return $entries;
}

function jyp_filter_terms(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT t.taxonomy,t.slug,t.name,COUNT(pt.profile_id) profile_count FROM `' . JYP_TERMS_TABLE . '` t JOIN `' . JYP_PROFILE_TERMS_TABLE . '` pt ON pt.term_id=t.id JOIN `' . JYP_PROFILES_TABLE . "` p ON p.id=pt.profile_id AND p.status='published' JOIN `" . JYP_PROFILE_TEXTS_TABLE . "` tx ON tx.profile_id=p.id AND tx.locale=p.source_locale AND tx.translation_status='published' GROUP BY t.id,t.taxonomy,t.slug,t.name,t.display_order ORDER BY t.taxonomy,t.display_order,t.name"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $rows;
}

function jyp_public_profiles_for_sitemap(PDO $pdo, int $page = 1, int $limit = 30): array
{
    $limit = max(1, min(1000, $limit));
    $page = max(1, $page);
    $statement = $pdo->prepare('SELECT p.slug,COALESCE(p.updated_at,p.created_at) changed_at FROM `' . JYP_PROFILES_TABLE . '` p JOIN `' . JYP_PROFILE_TEXTS_TABLE . "` pt ON pt.profile_id=p.id AND pt.locale=p.source_locale AND pt.translation_status='published' WHERE p.status='published' ORDER BY p.id LIMIT :limit OFFSET :offset");
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function jyp_sitemap_pages(?PDO $pdo, int $limit): int
{
    if (!$pdo instanceof PDO || !jyp_schema_ready($pdo)) return 0;
    $count = (int)$pdo->query('SELECT COUNT(*) FROM `' . JYP_PROFILES_TABLE . '` p JOIN `' . JYP_PROFILE_TEXTS_TABLE . "` pt ON pt.profile_id=p.id AND pt.locale=p.source_locale AND pt.translation_status='published' WHERE p.status='published'")->fetchColumn();
    return max(0, (int)ceil($count / max(1, $limit)));
}
