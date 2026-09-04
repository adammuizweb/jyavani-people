<?php
declare(strict_types=1);
$taxonomyLabels = ['group' => jyp_t('Group'), 'role' => jyp_t('Role'), 'expertise' => jyp_t('Expertise'), 'location' => jyp_t('Location')];
$listUrl = jyp_path_url('/' . $base . '/');
$queryForPage = static function (int $page) use ($filters, $base): string {
    $query = array_filter(['q' => $filters['search'], 'filter' => $filters['term'] !== '' ? $filters['taxonomy'] . ':' . $filters['term'] : ''], static fn(string $value): bool => $value !== '');
    if ($page > 1) $query['page_number'] = $page;
    $path = jyp_path_url('/' . $base . '/');
    return $path . ($query === [] ? '' : '?' . http_build_query($query));
};
?>
<div class="jyp-directory">
  <header class="jyp-directory__hero">
    <p class="jyp-eyebrow"><?=jyp_h(jyp_t('People directory'))?></p>
    <h1><?=jyp_h(jyp_t('Meet the people behind the work'))?></h1>
    <p><?=jyp_h(jyp_t('Explore professional profiles, expertise, and contributions.'))?></p>
  </header>
  <form class="jyp-directory__filters" method="get" action="<?=jyp_h($listUrl)?>">
    <label><span><?=jyp_h(jyp_t('Search people'))?></span><input type="search" name="q" maxlength="100" value="<?=jyp_h($filters['search'])?>" placeholder="<?=jyp_h(jyp_t('Name, position, or expertise'))?>"></label>
    <label><span><?=jyp_h(jyp_t('Filter directory'))?></span><select name="filter"><option value=""><?=jyp_h(jyp_t('Everyone'))?></option><?php $lastTaxonomy=''; foreach ($terms as $term): ?><?php if ($lastTaxonomy !== $term['taxonomy']): ?><?php if ($lastTaxonomy !== ''): ?></optgroup><?php endif; ?><optgroup label="<?=jyp_h($taxonomyLabels[$term['taxonomy']] ?? ucfirst((string)$term['taxonomy']))?>"><?php $lastTaxonomy=$term['taxonomy']; endif; ?><option value="<?=jyp_h($term['taxonomy'] . ':' . $term['slug'])?>" <?=$filters['taxonomy']===$term['taxonomy']&&$filters['term']===$term['slug']?'selected':''?>><?=jyp_h($term['name'])?> (<?=(int)$term['profile_count']?>)</option><?php endforeach; ?><?php if ($lastTaxonomy !== ''): ?></optgroup><?php endif; ?></select></label>
    <button type="submit"><?=jyp_h(jyp_t('Explore'))?></button>
  </form>
  <div class="jyp-directory__summary"><strong><?=jyp_h(jyp_t('%d people', (int)$result['total']))?></strong><?php if ($filters['search'] !== '' || $filters['term'] !== ''): ?><a href="<?=jyp_h($listUrl)?>"><?=jyp_h(jyp_t('Clear filters'))?></a><?php endif; ?></div>
  <?php if ($result['rows'] === []): ?>
    <section class="jyp-empty"><h2><?=jyp_h(jyp_t('No matching profiles'))?></h2><p><?=jyp_h(jyp_t('Try another name or directory filter.'))?></p></section>
  <?php else: ?>
    <section class="jyp-card-grid" aria-label="<?=jyp_h(jyp_t('People'))?>">
      <?php foreach ($result['rows'] as $profile): $name=trim((string)$profile['display_name']); $initial=mb_strtoupper(mb_substr($name,0,1,'UTF-8'),'UTF-8'); ?>
        <article class="jyp-card">
          <?php $profileUrl=jyp_path_url('/' . $base . '/' . rawurlencode((string)$profile['slug']) . '/'); ?><a class="jyp-card__portrait" href="<?=jyp_h($profileUrl)?>" aria-label="<?=jyp_h(jyp_t('View profile for %s', $name))?>"><?php if (is_string($profile['photo_url']) && $profile['photo_url'] !== ''): ?><img src="<?=jyp_h($profile['photo_url'])?>" alt="" loading="lazy"><?php else: ?><span aria-hidden="true"><?=jyp_h($initial)?></span><?php endif; ?></a>
          <div class="jyp-card__body"><div class="jyp-card__terms"><?php foreach (array_slice($profile['terms'],0,2) as $term): ?><span><?=jyp_h($term['name'])?></span><?php endforeach; ?></div><h2><a href="<?=jyp_h($profileUrl)?>"><?=jyp_h($name)?></a></h2><?php if ($profile['credentials']): ?><p class="jyp-card__credentials"><?=jyp_h($profile['credentials'])?></p><?php endif; ?><?php if ($profile['position_title']): ?><p class="jyp-card__position"><?=jyp_h($profile['position_title'])?></p><?php endif; ?><?php if ($profile['organization_unit']): ?><p class="jyp-card__unit"><?=jyp_h($profile['organization_unit'])?></p><?php endif; ?><div class="jyp-card__links"><?php foreach (array_slice($profile['links'],0,3) as $link): ?><a href="<?=jyp_h($link['url'])?>" target="_blank" rel="noopener noreferrer"><?=jyp_h($link['label'])?></a><?php endforeach; ?></div></div>
        </article>
      <?php endforeach; ?>
    </section>
    <?php if ((int)$result['pages'] > 1): ?><nav class="jyp-pagination" aria-label="<?=jyp_h(jyp_t('Directory pages'))?>"><?php if ((int)$result['page'] > 1): ?><a href="<?=jyp_h($queryForPage((int)$result['page']-1))?>"><?=jyp_h(jyp_t('Previous'))?></a><?php endif; ?><span><?=jyp_h(jyp_t('Page %d of %d', (int)$result['page'], (int)$result['pages']))?></span><?php if ((int)$result['page'] < (int)$result['pages']): ?><a href="<?=jyp_h($queryForPage((int)$result['page']+1))?>"><?=jyp_h(jyp_t('Next'))?></a><?php endif; ?></nav><?php endif; ?>
  <?php endif; ?>
</div>
