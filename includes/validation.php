<?php
declare(strict_types=1);

function jyp_normalize_slug(string $value): ?string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' && strlen($value) <= 191 ? $value : null;
}

function jyp_normalize_locale(string $value): ?string
{
    $value = strtolower(str_replace('_', '-', trim($value)));
    return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $value) === 1 ? $value : null;
}

function jyp_normalize_url(string $value, bool $allowMailto = false): ?string
{
    $value = trim($value);
    if ($value === '') return '';
    $decoded = rawurldecode($value);
    if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) return null;
    if (str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($decoded, '\\')) return $value;
    $parts = parse_url($value);
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) return null;
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if ($allowMailto && $scheme === 'mailto') return filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL) !== false ? $value : null;
    return in_array($scheme, ['https', 'http'], true) && !empty($parts['host']) && filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
}

function jyp_link_types(): array
{
    $types = [
        'website' => 'Website',
        'email' => 'Email',
        'linkedin' => 'LinkedIn',
        'scholar' => 'Google Scholar',
        'scopus' => 'Scopus',
        'orcid' => 'ORCID',
        'repository' => 'Research profile',
        'social' => 'Social profile',
    ];
    $filtered = function_exists('apply_filters') ? apply_filters('jyp_link_types', $types) : $types;
    if (!is_array($filtered)) return $types;
    $result = [];
    foreach ($filtered as $key => $label) {
        if (is_string($key) && preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $key) === 1 && is_string($label) && trim($label) !== '') {
            $result[$key] = mb_substr(trim($label), 0, 100, 'UTF-8');
        }
    }
    return $result ?: $types;
}

function jyp_entry_types(): array
{
    $types = [
        'teaching' => ['label' => 'Teaching', 'layout' => 'year-list'],
        'research' => ['label' => 'Research', 'layout' => 'citation-list'],
        'publication' => ['label' => 'Publications', 'layout' => 'citation-list'],
        'service' => ['label' => 'Community Services', 'layout' => 'timeline'],
        'development' => ['label' => 'Personal Development', 'layout' => 'timeline'],
        'certification' => ['label' => 'Certifications', 'layout' => 'cards'],
        'industry' => ['label' => 'Industry Experience', 'layout' => 'timeline'],
        'achievement' => ['label' => 'Achievements', 'layout' => 'cards'],
    ];
    $filtered = function_exists('apply_filters') ? apply_filters('jyp_entry_types', $types) : $types;
    if (!is_array($filtered)) return $types;
    $result = [];
    foreach ($filtered as $key => $definition) {
        if (!is_string($key) || preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $key) !== 1 || !is_array($definition)) continue;
        $label = trim((string)($definition['label'] ?? ''));
        $layout = (string)($definition['layout'] ?? 'year-list');
        if ($label === '' || !in_array($layout, ['year-list', 'citation-list', 'timeline', 'cards'], true)) continue;
        $result[$key] = ['label' => mb_substr($label, 0, 100, 'UTF-8'), 'layout' => $layout];
    }
    return $result ?: $types;
}
