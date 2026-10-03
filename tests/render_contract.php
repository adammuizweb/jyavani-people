<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/validation.php';
function jyp_h(mixed $value): string { return str_replace(['[', ']'], ['&#91;', '&#93;'], htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')); }
function jyp_t(string $source, mixed ...$arguments): string { return $arguments === [] ? $source : sprintf($source, ...$arguments); }
function jyp_path_url(string $path): string { return $path; }

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$condition) $failures[] = $message;
};
$render = static function (string $template, array $variables) use ($root): string {
    extract($variables, EXTR_SKIP);
    ob_start();
    require $root . '/templates/' . $template . '.php';
    return (string)ob_get_clean();
};

$profile = [
    'id' => 1, 'slug' => 'sample-person', 'display_name' => 'Sample [widget:test]', 'credentials' => '',
    'position_title' => 'Research Lead', 'organization_unit' => '', 'headline' => '', 'biography' => 'Safe [video id="1"]',
    'photo_url' => null, 'public_email' => 'sample@example.test', 'staff_identifier' => '',
    'terms' => [['taxonomy' => 'expertise', 'slug' => 'systems', 'name' => 'Systems']],
    'links' => [['link_type' => 'website', 'label' => 'Website', 'url' => 'https://example.test']],
    'entries' => [[
        'entry_type' => 'publication', 'year' => 2026, 'started_on' => null, 'ended_on' => null,
        'external_url' => 'https://example.test/publication', 'title' => 'A fictional publication', 'summary' => 'Summary',
    ]],
];
$result = ['rows' => [$profile], 'total' => 1, 'page' => 1, 'pages' => 1, 'per_page' => 18];
$list = $render('list', ['result' => $result, 'terms' => [], 'filters' => ['search' => '', 'taxonomy' => '', 'term' => ''], 'base' => 'people']);
$check(!str_contains($list, '<main') && str_contains($list, 'jyp-card__portrait') && !str_contains($list, 'src=""'), 'directory output avoids nested main landmarks and empty image requests');
$check(!str_contains($list, '[widget:test]') && str_contains($list, '&#91;widget:test&#93;'), 'directory text cannot reach Core shortcode expansion');
$check(str_contains($list, '>1 person<') && !str_contains($list, '1 people'), 'directory result count uses a singular label');
$check(substr_count($list, 'href="/people/sample-person/"') === 1, 'directory cards expose one keyboard-focusable profile link');
$check(str_contains($list, 'filter=expertise%3Asystems') && !str_contains($list, 'target="_blank"'), 'directory badges are useful filters and links do not force a new window');
$paginated = $render('list', ['result' => array_merge($result, ['total' => 180, 'page' => 1, 'pages' => 10]), 'terms' => [], 'filters' => ['search' => '', 'taxonomy' => '', 'term' => ''], 'base' => 'people']);
$check(str_contains($paginated, 'rel="next"') && str_contains($paginated, 'aria-current="page"') && str_contains($paginated, '<span aria-hidden="true">...</span>'), 'directory pagination renders current, next, and skipped-page semantics');

$single = $render('single', ['profile' => $profile, 'entryTypes' => jyp_entry_types(), 'base' => 'people']);
$check(!str_contains($single, '<main') && !str_contains($single, 'role="tab"') && str_contains($single, 'data-jyp-panel'), 'single profile remains semantically readable without JavaScript');
$check(str_contains($single, 'data-jyp-share') && str_contains($single, ' hidden'), 'JavaScript-only sharing is hidden before enhancement');
$check(!str_contains($single, '[video id=') && str_contains($single, '&#91;video id='), 'biography text cannot invoke Core shortcodes');
$check(!preg_match('/<h2>\s*<\/h2>/', $single), 'optional profile fields do not emit empty headings');
$check(str_contains($single, 'aria-labelledby="jyp-connect-heading"') && str_contains($single, 'class="jyp-profile__position"'), 'profile metadata and connect actions have correct heading semantics');
$check(str_contains($single, 'data-layout="citation-list"') && str_contains($single, 'datetime="2026"'), 'professional records expose their renderer layout and machine-readable year');
$check(!str_contains($single, 'target="_blank"'), 'profile links do not unexpectedly force a new window');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " render contract check(s) failed.\n");
    exit(1);
}
echo "Jyavani People render contract passed.\n";
