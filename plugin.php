<?php
declare(strict_types=1);

if (!defined('BACKEND_PATH')) return;

const JYP_VERSION = '0.1.0';
const JYP_EDIT_PERMISSION = 'plugin.jyavani-people.profiles.edit';
const JYP_PUBLISH_PERMISSION = 'plugin.jyavani-people.profiles.publish';
const JYP_DELETE_PERMISSION = 'plugin.jyavani-people.profiles.delete';
const JYP_PROFILES_TABLE = 'jyp_profiles';
const JYP_PROFILE_TEXTS_TABLE = 'jyp_profile_texts';
const JYP_TERMS_TABLE = 'jyp_terms';
const JYP_PROFILE_TERMS_TABLE = 'jyp_profile_terms';
const JYP_LINKS_TABLE = 'jyp_links';
const JYP_ENTRIES_TABLE = 'jyp_entries';
const JYP_ENTRY_TEXTS_TABLE = 'jyp_entry_texts';
const JYP_SITEMAP_LIMIT = 1000;

function jyp_h(mixed $value): string
{
    $escaped = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    return str_replace(['[', ']'], ['&#91;', '&#93;'], $escaped);
}

function jyp_t(string $source, mixed ...$arguments): string
{
    $translated = function_exists('__') ? __($source) : $source;
    return $arguments === [] ? $translated : sprintf($translated, ...$arguments);
}

require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/lifecycle.php';

function jyp_is_admin_request(): bool
{
    $page = trim((string)($_GET['page'] ?? ''), '/');
    return $page === 'admin/tools/jyavani-people' || str_starts_with($page, 'admin/tools/jyavani-people/');
}

function jyp_admin_assets(): void
{
    if (!jyp_is_admin_request()) return;
    echo '<link rel="stylesheet" href="/static/plugins/jyavani-people/admin.css?v=' . rawurlencode(JYP_VERSION) . '">' . PHP_EOL;
}

add_action('admin_head', 'jyp_admin_assets');
add_action('jy_head', 'jyp_frontend_assets');
add_action('plugin_uninstall', 'jyp_uninstall');
add_filter('sitemap_index_entries', 'jyp_sitemap_index_entries', 10);
add_filter('router_path', 'jyp_capture_resolved_path', PHP_INT_MAX);
$jypPdo = $GLOBALS['pdo'] ?? null;
$jypBasePath = jyp_base_path($jypPdo instanceof PDO ? $jypPdo : null);
if ($jypPdo instanceof PDO) jyp_assert_base_path_available($jypPdo, $jypBasePath);
if (!register_frontend_route($jypBasePath, 'jyp_frontend_route', ['match' => 'prefix', 'methods' => ['GET']])) {
    throw new RuntimeException('Jyavani People public route could not be registered.');
}
if (!register_frontend_route('sitemaps/people', 'jyp_render_sitemap', ['match' => 'prefix', 'methods' => ['GET']])) {
    throw new RuntimeException('Jyavani People sitemap route could not be registered.');
}
unset($jypBasePath, $jypPdo);
