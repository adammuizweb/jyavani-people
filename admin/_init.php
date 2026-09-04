<?php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;
$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    adiwira_render_404();
    return;
}
$jypRoute = trim((string)($_GET['page'] ?? ''), '/');
$jypPermission = $jypRoute === 'admin/tools/jyavani-people/delete' ? JYP_DELETE_PERMISSION : JYP_EDIT_PERMISSION;
[$jypUserId] = adiwira_require_permission($pdo, $jypPermission, false);
if (!jyp_schema_ready($pdo)) throw new RuntimeException(jyp_t('People directory storage is unavailable.'));
$jypBase = rtrim((string)ADMIN_BASE_PATH, '/') . '/?page=admin/tools/jyavani-people';

if (!function_exists('adiwira_redirect_with_flash') && defined('DASH_PATH')) {
    require_once rtrim((string)DASH_PATH, DIRECTORY_SEPARATOR) . '/admin/_notify.php';
}
