<?php
declare(strict_types=1);

function jyp_request_path(): string
{
    if (isset($GLOBALS['jyp_resolved_path']) && is_string($GLOBALS['jyp_resolved_path'])) return trim($GLOBALS['jyp_resolved_path'], '/');
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return trim(rawurldecode(is_string($path) ? $path : '/'), '/');
}

function jyp_capture_resolved_path(string $path): string
{
    $GLOBALS['jyp_resolved_path'] = trim($path, '/');
    return $path;
}

function jyp_site_origin(): string
{
    $pdo = $GLOBALS['pdo'] ?? null;
    $configured = $pdo instanceof PDO && function_exists('settings_get') ? trim((string)settings_get($pdo, 'site_url', '')) : '';
    $parts = $configured !== '' ? parse_url($configured) : false;
    if (is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])) {
        return rtrim($configured, '/');
    }
    $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    return $scheme . '://' . $host;
}

function jyp_path_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    return function_exists('localized_path_url') ? localized_path_url($path) : $path;
}

function jyp_frontend_assets(): void
{
    if (empty($GLOBALS['jyp_rendering'])) return;
    $version = rawurlencode(JYP_VERSION);
    echo '<link rel="stylesheet" href="/static/plugins/jyavani-people/frontend.css?v=' . $version . '">' . PHP_EOL;
    echo '<script src="/static/plugins/jyavani-people/frontend.js?v=' . $version . '" defer></script>' . PHP_EOL;
}

function jyp_template(string $name): string
{
    $fallback = dirname(__DIR__) . '/templates/' . $name . '.php';
    if (!preg_match('/^[a-z][a-z0-9_-]*$/', $name)) return $fallback;
    if (function_exists('get_active_theme_folder') && defined('PUBLIC_PATH')) {
        $folder = get_active_theme_folder($GLOBALS['pdo'] ?? null);
        if (preg_match('/^[A-Za-z0-9._-]{1,100}$/', $folder) === 1) {
            $candidate = rtrim((string)PUBLIC_PATH, '/') . '/views/themes/' . $folder . '/plugins/jyavani-people/' . $name . '.php';
            $publicReal = realpath((string)PUBLIC_PATH);
            $candidateReal = realpath($candidate);
            if (is_string($publicReal) && is_string($candidateReal) && str_starts_with($candidateReal, $publicReal . DIRECTORY_SEPARATOR) && is_file($candidateReal) && !is_link($candidate)) return $candidateReal;
        }
    }
    return $fallback;
}

function jyp_render_template(string $name, array $variables): string
{
    extract($variables, EXTR_SKIP);
    ob_start();
    require jyp_template($name);
    return (string)ob_get_clean();
}

function jyp_render_document(string $content, string $title, string $description, string $canonical, string $context): void
{
    $GLOBALS['jyp_rendering'] = true;
    add_filter('document_meta_description', static fn(string $current): string => $description, PHP_INT_MAX);
    $content_html = $content;
    $page_title = $title;
    $metaDesc = $description;
    $canonical_url = $canonical;
    $context_for_layout = $context;
    $layout_full_width = true;
    $enable_sidebar = false;
    $post = null;
    require dirname(__DIR__, 3) . '/app/layout.php';
}

function jyp_frontend_route(PDO $pdo): void
{
    if (!jyp_schema_ready($pdo)) {
        http_response_code(503);
        header('Retry-After: 300');
        echo jyp_h(jyp_t('People directory storage is unavailable.'));
        return;
    }
    $base = jyp_base_path($pdo);
    $path = jyp_request_path();
    if ($path === $base) {
        $filter = is_scalar($_GET['filter'] ?? null) ? (string)$_GET['filter'] : '';
        [$filterTaxonomy, $filterTerm] = array_pad(explode(':', $filter, 2), 2, '');
        $filters = [
            'search' => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8'),
            'taxonomy' => $filterTaxonomy,
            'term' => $filterTerm,
        ];
        $result = jyp_profile_page($pdo, $filters, max(1, (int)($_GET['page_number'] ?? 1)));
        $terms = jyp_filter_terms($pdo);
        $content = jyp_render_template('list', compact('result', 'terms', 'filters', 'base'));
        jyp_render_document($content, jyp_t('People'), jyp_t('Browse professional profiles.'), jyp_site_origin() . jyp_path_url('/' . $base . '/'), 'jyavani-people.list');
        return;
    }
    $suffix = substr($path, strlen($base) + 1);
    if ($suffix === '' || str_contains($suffix, '/')) {
        require dirname(__DIR__, 3) . '/app/frontend_404.php';
        return;
    }
    $slug = jyp_normalize_slug($suffix);
    $profile = $slug !== null ? jyp_profile_by_slug($pdo, $slug) : null;
    if (!is_array($profile)) {
        require dirname(__DIR__, 3) . '/app/frontend_404.php';
        return;
    }
    $entryTypes = jyp_entry_types();
    $content = jyp_render_template('single', compact('profile', 'entryTypes', 'base'));
    $title = trim((string)$profile['display_name'] . ' ' . (string)$profile['credentials']);
    $description = trim((string)($profile['headline'] ?: $profile['position_title'] ?: jyp_t('Professional profile')));
    $canonical = jyp_site_origin() . jyp_path_url('/' . $base . '/' . rawurlencode((string)$profile['slug']) . '/');
    $GLOBALS['jyp_current_profile'] = $profile;
    add_action('jy_head', static function () use ($profile, $canonical): void {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => trim((string)$profile['display_name'] . ' ' . (string)$profile['credentials']),
            'url' => $canonical,
        ];
        if (!empty($profile['photo_url'])) {
            $photo = jyp_normalize_url((string)$profile['photo_url']);
            $schema['image'] = $photo !== null && str_starts_with($photo, '/') ? jyp_site_origin() . $photo : $photo;
        }
        if (!empty($profile['position_title'])) $schema['jobTitle'] = (string)$profile['position_title'];
        if (!empty($profile['organization_unit'])) $schema['affiliation'] = ['@type' => 'Organization', 'name' => (string)$profile['organization_unit']];
        $schema = array_filter($schema, static fn(mixed $value): bool => $value !== null && $value !== '');
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>' . PHP_EOL;
    });
    jyp_render_document($content, $title, $description, $canonical, 'jyavani-people.single');
}

function jyp_sitemap_index_entries(array $entries, PDO $pdo, string $domain, int $limit): array
{
    if (!jyp_schema_ready($pdo)) return $entries;
    $pages = jyp_sitemap_pages($pdo, JYP_SITEMAP_LIMIT);
    for ($page = 1; $page <= $pages; $page++) $entries[] = ['loc' => rtrim($domain, '/') . '/sitemaps/people/' . $page . '.xml'];
    return $entries;
}

function jyp_render_sitemap(PDO $pdo): void
{
    $path = jyp_request_path();
    if (preg_match('#^sitemaps/people/([1-9][0-9]*)\.xml$#', $path, $match) !== 1) {
        http_response_code(404);
        return;
    }
    $page = (int)$match[1];
    if (!jyp_schema_ready($pdo) || $page > jyp_sitemap_pages($pdo, JYP_SITEMAP_LIMIT)) {
        http_response_code(404);
        return;
    }
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    if (jyp_schema_ready($pdo)) {
        $origin = jyp_site_origin();
        $base = jyp_base_path($pdo);
        foreach (jyp_public_profiles_for_sitemap($pdo, $page, JYP_SITEMAP_LIMIT) as $profile) {
            $loc = $origin . '/' . $base . '/' . rawurlencode((string)$profile['slug']) . '/';
            echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc><lastmod>' . htmlspecialchars(date('c', strtotime((string)$profile['changed_at'])), ENT_XML1, 'UTF-8') . '</lastmod></url>' . PHP_EOL;
        }
    }
    echo '</urlset>';
}
