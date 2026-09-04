<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$condition) $failures[] = $message;
};

$check(($manifest['name'] ?? '') === 'jyavani-people' && ($manifest['version'] ?? '') === '0.1.0', 'manifest has stable plugin identity and version');
$check(($manifest['requires']['jyavani'] ?? '') === '>=2.3.102', 'manifest requires the migration-capable Core baseline');
$permissionKeys = array_column($manifest['permissions'] ?? [], 'key');
$check($permissionKeys === ['plugin.jyavani-people.profiles.edit', 'plugin.jyavani-people.profiles.publish', 'plugin.jyavani-people.profiles.delete'], 'manifest separates edit, publish, and delete permissions');
$routes = array_column($manifest['admin']['pages'] ?? [], 'route');
$check(count($routes) === 4 && count(array_unique($routes)) === 4, 'admin routes are unique and complete');
$check(count($manifest['static']['copy'] ?? []) === 3, 'manifest declares bounded static assets');

$entrypoint = (string)file_get_contents($root . '/plugin.php');
$runtime = (string)file_get_contents($root . '/includes/runtime.php');
$save = (string)file_get_contents($root . '/admin/save.php');
$check(str_contains($entrypoint, 'register_frontend_route') && str_contains($runtime, 'jyp_frontend_route'), 'plugin registers a deterministic public route');
$check(str_contains($runtime, '$layout_full_width = true;') && str_contains($runtime, '$enable_sidebar = false;'), 'dedicated People documents bypass the generic container and sidebar');
$check(str_contains($runtime, "'@type' => 'Person'") && str_contains($runtime, 'sitemaps/people'), 'public profiles expose structured data and sitemap integration');
$check(str_contains($save, 'authorization_lock_actor_permissions') && str_contains($save, 'user_can(') && str_contains($save, 'csrf_check('), 'profile mutations reauthorize under CSRF-protected transactions');
$check(str_contains($save, 'FOR UPDATE') && str_contains($save, 'version=version+1'), 'profile saves enforce optimistic state under a row lock');
$check(str_contains($save, 'SELECT version,status') && str_contains($save, "['status'] !== \$status") && str_contains($save, 'JYP_PUBLISH_PERMISSION'), 'publication state transitions require publishing permission under the row lock');
$check(str_contains((string)file_get_contents($root . '/plugin.php'), '&#91;') && str_contains((string)file_get_contents($root . '/includes/repository.php'), "pt.translation_status='published'"), 'public text blocks shortcode execution and excludes draft source representations');
$check(!str_contains($runtime, 'content-translation') && !str_contains($entrypoint, 'content-translation'), 'runtime has no hard dependency on a translation plugin');

require_once $root . '/includes/validation.php';
$check(jyp_normalize_slug('Example Person') === 'example-person' && jyp_normalize_slug('../') === null, 'profile slugs normalize safely');
$check(jyp_normalize_locale('en_US') === 'en-us' && jyp_normalize_locale('bad/value') === null, 'source locales use bounded normalized identifiers');
$check(jyp_normalize_url('https://example.test/profile') !== null && jyp_normalize_url('javascript:alert(1)') === null, 'public links reject executable URL schemes');
$check(count(jyp_entry_types()) === 8 && count(jyp_link_types()) === 8, 'default tabs and quick links are general and bounded');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " contract check(s) failed.\n");
    exit(1);
}
echo "Jyavani People contract passed.\n";
