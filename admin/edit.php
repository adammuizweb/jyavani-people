<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$id = max(0, (int)($_GET['id'] ?? 0));
$profile = $id > 0 ? jyp_profile($pdo, $id) : null;
if ($id > 0 && !is_array($profile)) {
    adiwira_render_404();
    return;
}
$profile ??= [
    'id' => 0, 'version' => 0, 'slug' => '', 'source_locale' => 'en', 'status' => 'draft',
    'photo_url' => '', 'public_email' => '', 'staff_identifier' => '', 'display_order' => 100,
    'display_name' => '', 'credentials' => '', 'position_title' => '', 'organization_unit' => '',
    'headline' => '', 'biography' => '', 'terms' => [], 'links' => [],
];
$termValues = ['group' => [], 'role' => [], 'expertise' => [], 'location' => []];
foreach ($profile['terms'] as $term) if (isset($termValues[$term['taxonomy']])) $termValues[$term['taxonomy']][] = $term['name'];
$links = array_values($profile['links']);
while (count($links) < 5) $links[] = ['link_type' => 'website', 'label' => '', 'url' => '', 'is_public' => 1];
$linkTypes = jyp_link_types();
$canPublish = function_exists('user_can') && user_can($pdo, $jypUserId, JYP_PUBLISH_PERMISSION);
$canDelete = $id > 0 && function_exists('user_can') && user_can($pdo, $jypUserId, JYP_DELETE_PERMISSION);
?>
<section class="jyp-admin">
  <header class="jyp-admin__head"><div><span><?=jyp_h(jyp_t('Professional directory'))?></span><h1><?=jyp_h($id > 0 ? jyp_t('Edit person') : jyp_t('Add person'))?></h1><p><?=jyp_h(jyp_t('Keep identity, public presentation, classifications, and professional links structured.'))?></p></div><a class="adam-button ghost" href="<?=jyp_h($jypBase)?>"><?=jyp_h(jyp_t('Back to people'))?></a></header>
  <form method="post" action="<?=jyp_h($jypBase . '/save')?>" class="jyp-editor">
    <input type="hidden" name="action" value="save"><input type="hidden" name="csrf_token" value="<?=jyp_h(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$profile['id']?>"><input type="hidden" name="version" value="<?=(int)$profile['version']?>">
    <div class="jyp-editor__main">
      <fieldset><legend><?=jyp_h(jyp_t('Public identity'))?></legend><div class="jyp-grid two">
        <label><span><?=jyp_h(jyp_t('Display name'))?></span><input class="adam-input" name="display_name" maxlength="191" required value="<?=jyp_h($profile['display_name'])?>"></label>
        <label><span><?=jyp_h(jyp_t('Credentials'))?></span><input class="adam-input" name="credentials" maxlength="191" value="<?=jyp_h($profile['credentials'])?>"></label>
        <label><span><?=jyp_h(jyp_t('Profile slug'))?></span><input class="adam-input" name="slug" maxlength="191" required value="<?=jyp_h($profile['slug'])?>"></label>
        <label><span><?=jyp_h(jyp_t('Source locale'))?></span><input class="adam-input" name="source_locale" maxlength="12" required value="<?=jyp_h($profile['source_locale'])?>"></label>
        <label><span><?=jyp_h(jyp_t('Position title'))?></span><input class="adam-input" name="position_title" maxlength="191" value="<?=jyp_h($profile['position_title'])?>"></label>
        <label><span><?=jyp_h(jyp_t('Organization unit'))?></span><input class="adam-input" name="organization_unit" maxlength="191" value="<?=jyp_h($profile['organization_unit'])?>"></label>
        <label class="wide"><span><?=jyp_h(jyp_t('Professional headline'))?></span><input class="adam-input" name="headline" maxlength="500" value="<?=jyp_h($profile['headline'])?>"></label>
        <label class="wide"><span><?=jyp_h(jyp_t('Biography'))?></span><textarea class="adam-input" name="biography" rows="9" maxlength="20000"><?=jyp_h($profile['biography'])?></textarea></label>
      </div></fieldset>
      <fieldset><legend><?=jyp_h(jyp_t('Directory filters'))?></legend><div class="jyp-grid two"><?php foreach ($termValues as $taxonomy => $values): ?><label><span><?=jyp_h(jyp_t(ucfirst($taxonomy)))?></span><input class="adam-input" name="terms[<?=jyp_h($taxonomy)?>]" value="<?=jyp_h(implode(', ', $values))?>"><small><?=jyp_h(jyp_t('Separate multiple values with commas.'))?></small></label><?php endforeach; ?></div></fieldset>
      <fieldset><legend><?=jyp_h(jyp_t('Professional links'))?></legend><div class="jyp-links"><?php foreach ($links as $index => $link): ?><fieldset class="jyp-link-row"><legend><?=jyp_h(jyp_t('Link %d', $index + 1))?></legend><label><span><?=jyp_h(jyp_t('Type'))?></span><select class="adam-input" name="links[<?=$index?>][type]"><?php foreach ($linkTypes as $type => $label): ?><option value="<?=jyp_h($type)?>" <?=$link['link_type']===$type?'selected':''?>><?=jyp_h(jyp_t($label))?></option><?php endforeach; ?></select></label><label><span><?=jyp_h(jyp_t('Label'))?></span><input class="adam-input" name="links[<?=$index?>][label]" maxlength="100" value="<?=jyp_h($link['label'])?>"></label><label><span><?=jyp_h(jyp_t('URL'))?></span><input class="adam-input" name="links[<?=$index?>][url]" maxlength="2048" placeholder="https://" value="<?=jyp_h($link['url'])?>"></label><label class="jyp-check"><input type="checkbox" name="links[<?=$index?>][public]" value="1" <?=!empty($link['is_public'])?'checked':''?>> <?=jyp_h(jyp_t('Public'))?></label></fieldset><?php endforeach; ?></div></fieldset>
    </div>
    <aside class="jyp-editor__side"><fieldset><legend><?=jyp_h(jyp_t('Publishing'))?></legend><?php if ($canPublish): ?><label><span><?=jyp_h(jyp_t('Status'))?></span><select class="adam-input" name="status"><option value="draft" <?=$profile['status']==='draft'?'selected':''?>><?=jyp_h(jyp_t('Draft'))?></option><option value="published" <?=$profile['status']==='published'?'selected':''?>><?=jyp_h(jyp_t('Published'))?></option></select></label><?php else: ?><input type="hidden" name="status" value="<?=jyp_h($profile['status'])?>"><p><strong><?=jyp_h(jyp_t('Status'))?>:</strong> <?=jyp_h(jyp_t(ucfirst((string)$profile['status'])))?></p><?php endif; ?><label><span><?=jyp_h(jyp_t('Display order'))?></span><input class="adam-input" type="number" name="display_order" min="0" max="100000" value="<?=(int)$profile['display_order']?>"></label><label><span><?=jyp_h(jyp_t('Photo URL'))?></span><input class="adam-input" name="photo_url" maxlength="2048" value="<?=jyp_h($profile['photo_url'])?>"></label><label><span><?=jyp_h(jyp_t('Public email'))?></span><input class="adam-input" type="email" name="public_email" maxlength="254" value="<?=jyp_h($profile['public_email'])?>"></label><label><span><?=jyp_h(jyp_t('Public identifier'))?></span><input class="adam-input" name="staff_identifier" maxlength="100" value="<?=jyp_h($profile['staff_identifier'])?>"></label><button class="adam-button" type="submit"><?=jyp_h(jyp_t('Save profile'))?></button></fieldset><?php if ($canDelete): ?><fieldset class="jyp-delete-box" id="delete-profile"><legend><?=jyp_h(jyp_t('Delete profile'))?></legend><p><?=jyp_h(jyp_t('This permanently removes the profile and all dependent records.'))?></p><label class="jyp-check"><input type="checkbox" name="delete_acknowledgement" value="1" form="jyp-delete-form" required> <?=jyp_h(jyp_t('I understand this cannot be undone.'))?></label><button class="adam-button danger" type="submit" form="jyp-delete-form"><?=jyp_h(jyp_t('Delete permanently'))?></button></fieldset><?php endif; ?></aside>
  </form>
  <?php if ($canDelete): ?><form id="jyp-delete-form" method="post" action="<?=jyp_h($jypBase . '/delete')?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf_token" value="<?=jyp_h(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$profile['id']?>"><input type="hidden" name="version" value="<?=(int)$profile['version']?>"><input type="hidden" name="confirm_delete" value="1"></form><?php endif; ?>
</section>
