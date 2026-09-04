<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$filters = [
    'search' => mb_substr(trim((string)($_GET['search'] ?? '')), 0, 100, 'UTF-8'),
    'status' => in_array((string)($_GET['status'] ?? ''), ['draft', 'published'], true) ? (string)$_GET['status'] : '',
];
$result = jyp_profile_page($pdo, $filters, max(1, (int)($_GET['p'] ?? 1)), 20, false);
$publicBase = '/' . jyp_base_path($pdo) . '/';
$canDelete = function_exists('user_can') && user_can($pdo, $jypUserId, JYP_DELETE_PERMISSION);
$pageUrl = static function (int $page) use ($jypBase, $filters): string {
    $query = array_filter($filters, static fn(string $value): bool => $value !== '');
    if ($page > 1) $query['p'] = $page;
    return $jypBase . ($query === [] ? '' : '&' . http_build_query($query));
};
?>
<section class="jyp-admin">
  <header class="jyp-admin__head">
    <div><span><?=jyp_h(jyp_t('Professional directory'))?></span><h1><?=jyp_h(jyp_t('People'))?></h1><p><?=jyp_h(jyp_t('Manage public professional profiles, directory groups, and profile links.'))?></p></div>
    <div class="jyp-admin__actions"><a class="adam-button ghost" href="<?=jyp_h($publicBase)?>" target="_blank" rel="noopener"><?=jyp_h(jyp_t('View directory'))?></a><a class="adam-button" href="<?=jyp_h($jypBase . '/edit')?>"><?=jyp_h(jyp_t('Add person'))?></a></div>
  </header>
  <form method="get" action="<?=jyp_h(rtrim((string)ADMIN_BASE_PATH, '/') . '/')?>" class="jyp-admin__filters">
    <input type="hidden" name="page" value="admin/tools/jyavani-people">
    <label><span><?=jyp_h(jyp_t('Search'))?></span><input class="adam-input" type="search" name="search" maxlength="100" value="<?=jyp_h($filters['search'])?>"></label>
    <label><span><?=jyp_h(jyp_t('Status'))?></span><select class="adam-input" name="status"><option value=""><?=jyp_h(jyp_t('All statuses'))?></option><option value="published" <?=$filters['status']==='published'?'selected':''?>><?=jyp_h(jyp_t('Published'))?></option><option value="draft" <?=$filters['status']==='draft'?'selected':''?>><?=jyp_h(jyp_t('Draft'))?></option></select></label>
    <button class="adam-button" type="submit"><?=jyp_h(jyp_t('Filter'))?></button>
  </form>
  <?php if ($result['rows'] === []): ?>
    <div class="jyp-admin__empty"><h2><?=jyp_h(jyp_t('No profiles found'))?></h2><p><?=jyp_h(jyp_t('Create a profile or adjust the current filters.'))?></p></div>
  <?php else: ?>
    <div class="jyp-admin__table"><table><thead><tr><th><?=jyp_h(jyp_t('Person'))?></th><th><?=jyp_h(jyp_t('Position'))?></th><th><?=jyp_h(jyp_t('Status'))?></th><th><?=jyp_h(jyp_t('Order'))?></th><th><?=jyp_h(jyp_t('Actions'))?></th></tr></thead><tbody>
    <?php foreach ($result['rows'] as $profile): ?>
      <tr><td><strong><?=jyp_h($profile['display_name'])?></strong><small>/<?=jyp_h($profile['slug'])?></small></td><td><?=jyp_h($profile['position_title'])?><small><?=jyp_h($profile['organization_unit'])?></small></td><td><span class="jyp-status is-<?=jyp_h($profile['status'])?>"><?=jyp_h(jyp_t(ucfirst((string)$profile['status'])))?></span></td><td><?=(int)$profile['display_order']?></td><td><div class="jyp-row-actions"><a href="<?=jyp_h($jypBase . '/edit&id=' . (int)$profile['id'])?>"><?=jyp_h(jyp_t('Edit'))?></a><?php if ($profile['status']==='published'): ?><a href="<?=jyp_h($publicBase . rawurlencode((string)$profile['slug']) . '/')?>" target="_blank" rel="noopener"><?=jyp_h(jyp_t('View'))?></a><?php endif; ?><?php if ($canDelete): ?><a class="jyp-danger" href="<?=jyp_h($jypBase . '/edit&id=' . (int)$profile['id'] . '#delete-profile')?>"><?=jyp_h(jyp_t('Delete'))?></a><?php endif; ?></div></td></tr>
    <?php endforeach; ?>
    </tbody></table><?php if ((int)$result['pages'] > 1): ?><nav class="jyp-admin__pagination" aria-label="<?=jyp_h(jyp_t('Profile pages'))?>"><?php if ((int)$result['page'] > 1): ?><a class="adam-button ghost" href="<?=jyp_h($pageUrl((int)$result['page']-1))?>"><?=jyp_h(jyp_t('Previous'))?></a><?php endif; ?><span><?=jyp_h(jyp_t('Page %d of %d', (int)$result['page'], (int)$result['pages']))?></span><?php if ((int)$result['page'] < (int)$result['pages']): ?><a class="adam-button ghost" href="<?=jyp_h($pageUrl((int)$result['page']+1))?>"><?=jyp_h(jyp_t('Next'))?></a><?php endif; ?></nav><?php endif; ?></div>
  <?php endif; ?>
</section>
