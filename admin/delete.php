<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$redirect = static function (string $type, string $message) use ($jypBase): never {
    if (function_exists('adiwira_redirect_with_flash')) adiwira_redirect_with_flash($jypBase, $type, $message, 303);
    header('Location: ' . $jypBase, true, 303);
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { adiwira_render_404(); return; }
if (!function_exists('csrf_check') || !csrf_check((string)($_POST['csrf_token'] ?? ''))) $redirect('error', jyp_t('The security token is invalid.'));
$id = max(0, (int)($_POST['id'] ?? 0));
try {
    if ($id <= 0) throw new DomainException(jyp_t('Profile not found.'));
    $pdo->beginTransaction();
    if (!function_exists('authorization_lock_actor_permissions') || !authorization_lock_actor_permissions($pdo, $jypUserId)) throw new RuntimeException('Unable to lock actor permissions.');
    if (!function_exists('user_can') || !user_can($pdo, $jypUserId, JYP_DELETE_PERMISSION)) throw new RuntimeException('Profile delete permission changed.');
    if ((string)($_POST['confirm_delete'] ?? '') !== '1') throw new DomainException(jyp_t('Confirm permanent profile deletion.'));
    $version = max(1, (int)($_POST['version'] ?? 0));
    $lock = $pdo->prepare('SELECT version FROM `' . JYP_PROFILES_TABLE . '` WHERE id=? FOR UPDATE');
    $lock->execute([$id]);
    $currentVersion = $lock->fetchColumn();
    if ($currentVersion === false) throw new DomainException(jyp_t('Profile not found.'));
    if ((int)$currentVersion !== $version) throw new DomainException(jyp_t('This profile changed after it was loaded. Reload before deleting.'));
    $pdo->prepare('DELETE FROM `' . JYP_PROFILES_TABLE . '` WHERE id=?')->execute([$id]);
    if (!function_exists('authorization_audit') || !authorization_audit($pdo, 'jyp.profile.deleted', $jypUserId, null, 'jyavani-people', (string)$id)) throw new RuntimeException('Unable to write audit event.');
    $pdo->commit();
    $redirect('success', jyp_t('Profile deleted.'));
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!$error instanceof DomainException) error_log('[jyavani-people] delete failed: ' . $error->getMessage());
    $redirect('error', $error instanceof DomainException ? $error->getMessage() : jyp_t('The profile could not be deleted.'));
}
