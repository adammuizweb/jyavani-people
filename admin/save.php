<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$redirect = static function (string $type, string $message, string $suffix = '') use ($jypBase): never {
    $location = $jypBase . $suffix;
    if (function_exists('adiwira_redirect_with_flash')) adiwira_redirect_with_flash($location, $type, $message, 303);
    header('Location: ' . $location, true, 303);
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { adiwira_render_404(); return; }
if (!function_exists('csrf_check') || !csrf_check((string)($_POST['csrf_token'] ?? ''))) $redirect('error', jyp_t('The security token is invalid.'));

$id = max(0, (int)($_POST['id'] ?? 0));
try {
    $displayName = mb_substr(trim((string)($_POST['display_name'] ?? '')), 0, 191, 'UTF-8');
    if ($displayName === '') throw new DomainException(jyp_t('Display name is required.'));
    $slug = jyp_normalize_slug((string)($_POST['slug'] ?? ''));
    if ($slug === null) throw new DomainException(jyp_t('Enter a valid profile slug.'));
    $locale = jyp_normalize_locale((string)($_POST['source_locale'] ?? 'en'));
    if ($locale === null) throw new DomainException(jyp_t('Enter a valid source locale.'));
    $status = (string)($_POST['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published'], true)) throw new DomainException(jyp_t('Select a valid publication status.'));
    $photo = jyp_normalize_url((string)($_POST['photo_url'] ?? ''));
    if ($photo === null) throw new DomainException(jyp_t('Enter a valid root-relative or HTTP(S) photo URL.'));
    $email = trim((string)($_POST['public_email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new DomainException(jyp_t('Enter a valid public email address.'));
    $displayOrder = filter_var($_POST['display_order'] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
    if ($displayOrder === false) throw new DomainException(jyp_t('Display order must be between 0 and 100000.'));
    $version = max(0, (int)($_POST['version'] ?? 0));
    $text = [
        'credentials' => mb_substr(trim((string)($_POST['credentials'] ?? '')), 0, 191, 'UTF-8'),
        'position_title' => mb_substr(trim((string)($_POST['position_title'] ?? '')), 0, 191, 'UTF-8'),
        'organization_unit' => mb_substr(trim((string)($_POST['organization_unit'] ?? '')), 0, 191, 'UTF-8'),
        'headline' => mb_substr(trim((string)($_POST['headline'] ?? '')), 0, 500, 'UTF-8'),
        'biography' => mb_substr(trim((string)($_POST['biography'] ?? '')), 0, 20000, 'UTF-8'),
    ];
    $links = [];
    foreach (is_array($_POST['links'] ?? null) ? $_POST['links'] : [] as $row) {
        if (!is_array($row)) continue;
        $type = (string)($row['type'] ?? 'website');
        $url = jyp_normalize_url((string)($row['url'] ?? ''), $type === 'email');
        if ($url === '') continue;
        if ($url === null || !isset(jyp_link_types()[$type])) throw new DomainException(jyp_t('One of the professional links is invalid.'));
        $label = mb_substr(trim((string)($row['label'] ?? '')), 0, 100, 'UTF-8');
        $links[] = [$type, $label !== '' ? $label : jyp_link_types()[$type], $url, count($links) * 10 + 10, !empty($row['public']) ? 1 : 0];
        if (count($links) >= 20) break;
    }
    $pdo->beginTransaction();
    if (!function_exists('authorization_lock_actor_permissions') || !authorization_lock_actor_permissions($pdo, $jypUserId)) throw new RuntimeException('Unable to lock actor permissions.');
    if (!function_exists('user_can') || !user_can($pdo, $jypUserId, JYP_EDIT_PERMISSION)) throw new RuntimeException('Profile edit permission changed.');
    if ($id > 0) {
        $current = $pdo->prepare('SELECT version,status FROM `' . JYP_PROFILES_TABLE . '` WHERE id=? FOR UPDATE');
        $current->execute([$id]);
        $currentProfile = $current->fetch(PDO::FETCH_ASSOC);
        if (!is_array($currentProfile) || (int)$currentProfile['version'] !== $version) throw new DomainException(jyp_t('This profile changed after it was loaded. Reload before saving.'));
        if ((string)$currentProfile['status'] !== $status && (!function_exists('user_can') || !user_can($pdo, $jypUserId, JYP_PUBLISH_PERMISSION))) throw new DomainException(jyp_t('Changing publication status requires publishing permission.'));
        $statement = $pdo->prepare('UPDATE `' . JYP_PROFILES_TABLE . '` SET slug=?,source_locale=?,status=?,photo_url=?,public_email=?,staff_identifier=?,display_order=?,version=version+1,updated_by=? WHERE id=? AND version=?');
        $statement->execute([$slug, $locale, $status, $photo ?: null, $email ?: null, mb_substr(trim((string)($_POST['staff_identifier'] ?? '')), 0, 100, 'UTF-8') ?: null, $displayOrder, $jypUserId, $id, $version]);
        $event = 'jyp.profile.updated';
    } else {
        if ($status === 'published' && (!function_exists('user_can') || !user_can($pdo, $jypUserId, JYP_PUBLISH_PERMISSION))) throw new DomainException(jyp_t('Publishing permission is required.'));
        $statement = $pdo->prepare('INSERT INTO `' . JYP_PROFILES_TABLE . '` (slug,source_locale,status,photo_url,public_email,staff_identifier,display_order,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?)');
        $statement->execute([$slug, $locale, $status, $photo ?: null, $email ?: null, mb_substr(trim((string)($_POST['staff_identifier'] ?? '')), 0, 100, 'UTF-8') ?: null, $displayOrder, $jypUserId, $jypUserId]);
        $id = (int)$pdo->lastInsertId();
        $event = 'jyp.profile.created';
    }
    $statement = $pdo->prepare('INSERT INTO `' . JYP_PROFILE_TEXTS_TABLE . '` (profile_id,locale,display_name,credentials,position_title,organization_unit,headline,biography,translation_status) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),credentials=VALUES(credentials),position_title=VALUES(position_title),organization_unit=VALUES(organization_unit),headline=VALUES(headline),biography=VALUES(biography),translation_status=VALUES(translation_status)');
    $statement->execute([$id, $locale, $displayName, $text['credentials'] ?: null, $text['position_title'] ?: null, $text['organization_unit'] ?: null, $text['headline'] ?: null, $text['biography'] ?: null, $status]);
    $pdo->prepare('DELETE FROM `' . JYP_PROFILE_TERMS_TABLE . '` WHERE profile_id=?')->execute([$id]);
    $termInput = is_array($_POST['terms'] ?? null) ? $_POST['terms'] : [];
    foreach (['group', 'role', 'expertise', 'location'] as $taxonomy) {
        $values = explode(',', is_scalar($termInput[$taxonomy] ?? null) ? (string)$termInput[$taxonomy] : '');
        foreach (array_slice($values, 0, 20) as $name) {
            $name = mb_substr(trim($name), 0, 191, 'UTF-8');
            $termSlug = jyp_normalize_slug($name);
            if ($name === '' || $termSlug === null) continue;
            $pdo->prepare('INSERT INTO `' . JYP_TERMS_TABLE . '` (taxonomy,slug,name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)')->execute([$taxonomy, $termSlug, $name]);
            $termId = $pdo->prepare('SELECT id FROM `' . JYP_TERMS_TABLE . '` WHERE taxonomy=? AND slug=?');
            $termId->execute([$taxonomy, $termSlug]);
            $pdo->prepare('INSERT IGNORE INTO `' . JYP_PROFILE_TERMS_TABLE . '` (profile_id,term_id) VALUES (?,?)')->execute([$id, (int)$termId->fetchColumn()]);
        }
    }
    $pdo->prepare('DELETE FROM `' . JYP_LINKS_TABLE . '` WHERE profile_id=?')->execute([$id]);
    $linkStatement = $pdo->prepare('INSERT INTO `' . JYP_LINKS_TABLE . '` (profile_id,link_type,label,url,display_order,is_public) VALUES (?,?,?,?,?,?)');
    foreach ($links as $link) $linkStatement->execute(array_merge([$id], $link));
    if (!function_exists('authorization_audit') || !authorization_audit($pdo, $event, $jypUserId, null, 'jyavani-people', (string)$id, ['status' => $status])) throw new RuntimeException('Unable to write audit event.');
    $pdo->commit();
    $redirect('success', jyp_t('Profile saved.'), '/edit&id=' . $id);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = $error instanceof DomainException ? $error->getMessage() : jyp_t('The profile could not be saved.');
    if (!$error instanceof DomainException) error_log('[jyavani-people] save failed: ' . $error->getMessage());
    $redirect('error', $message, $id > 0 ? '/edit&id=' . $id : '/edit');
}
