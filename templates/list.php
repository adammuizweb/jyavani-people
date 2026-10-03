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
$termUrl = static function (array $term) use ($base): string {
    $path = jyp_path_url('/' . $base . '/');
    return $path . '?' . http_build_query(['filter' => (string)$term['taxonomy'] . ':' . (string)$term['slug']]);
};
$total = (int)$result['total'];
$resultLabel = $total === 1 ? jyp_t('1 person') : jyp_t('%d people', $total);
$currentPage = (int)$result['page'];
$pageCount = (int)$result['pages'];
$visiblePages = [];
if ($pageCount > 1) {
    $visiblePages = $pageCount <= 7
        ? range(1, $pageCount)
        : array_values(array_unique(array_filter([1, $currentPage - 1, $currentPage, $currentPage + 1, $pageCount], static fn(int $page): bool => $page >= 1 && $page <= $pageCount)));
    sort($visiblePages);
}
?>
<div class="jyp-directory">
  <header class="jyp-directory__hero">
    <div class="jyp-directory__intro">
      <p class="jyp-eyebrow"><?=jyp_h(jyp_t('People directory'))?></p>
      <h1><?=jyp_h(jyp_t('Meet the people behind the work'))?></h1>
      <p><?=jyp_h(jyp_t('Explore professional profiles, expertise, and contributions.'))?></p>
    </div>
  </header>
  <form class="jyp-directory__filters" method="get" action="<?=jyp_h($listUrl)?>" role="search">
    <label class="jyp-directory__search"><span><?=jyp_h(jyp_t('Search people'))?></span><input type="search" name="q" maxlength="100" value="<?=jyp_h($filters['search'])?>" placeholder="<?=jyp_h(jyp_t('Name, position, or expertise'))?>"></label>
    <label><span><?=jyp_h(jyp_t('Filter directory'))?></span><select name="filter"><option value=""><?=jyp_h(jyp_t('Everyone'))?></option><?php $lastTaxonomy = ''; foreach ($terms as $term): ?><?php if ($lastTaxonomy !== $term['taxonomy']): ?><?php if ($lastTaxonomy !== ''): ?></optgroup><?php endif; ?><optgroup label="<?=jyp_h($taxonomyLabels[$term['taxonomy']] ?? ucfirst((string)$term['taxonomy']))?>"><?php $lastTaxonomy = $term['taxonomy']; endif; ?><option value="<?=jyp_h($term['taxonomy'] . ':' . $term['slug'])?>" <?=$filters['taxonomy'] === $term['taxonomy'] && $filters['term'] === $term['slug'] ? 'selected' : ''?>><?=jyp_h($term['name'])?> (<?=(int)$term['profile_count']?>)</option><?php endforeach; ?><?php if ($lastTaxonomy !== ''): ?></optgroup><?php endif; ?></select></label>
    <button type="submit"><?=jyp_h(jyp_t('Explore'))?></button>
  </form>
  <div class="jyp-directory__summary"><strong><?=jyp_h($resultLabel)?></strong><?php if ($filters['search'] !== '' || $filters['term'] !== ''): ?><a href="<?=jyp_h($listUrl)?>"><?=jyp_h(jyp_t('Clear filters'))?></a><?php endif; ?></div>
  <?php if ($result['rows'] === []): ?>
    <section class="jyp-empty"><span class="jyp-empty__mark" aria-hidden="true">0</span><h2><?=jyp_h(jyp_t('No matching profiles'))?></h2><p><?=jyp_h(jyp_t('Try another name or directory filter.'))?></p><?php if ($filters['search'] !== '' || $filters['term'] !== ''): ?><a href="<?=jyp_h($listUrl)?>"><?=jyp_h(jyp_t('Clear filters'))?></a><?php endif; ?></section>
  <?php else: ?>
    <section class="jyp-card-grid" aria-label="<?=jyp_h(jyp_t('People'))?>">
      <?php foreach ($result['rows'] as $profile): $name = trim((string)$profile['display_name']); $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8'); $profileUrl = jyp_path_url('/' . $base . '/' . rawurlencode((string)$profile['slug']) . '/'); ?>
        <article class="jyp-card">
          <div class="jyp-card__portrait"><?php if (is_string($profile['photo_url']) && $profile['photo_url'] !== ''): ?><img src="<?=jyp_h($profile['photo_url'])?>" alt="" width="640" height="760" loading="lazy" decoding="async"><?php else: ?><span aria-hidden="true"><?=jyp_h($initial)?></span><?php endif; ?></div>
          <div class="jyp-card__body">
            <?php if ($profile['terms'] !== []): ?><div class="jyp-card__terms"><?php foreach (array_slice($profile['terms'], 0, 2) as $term): ?><a href="<?=jyp_h($termUrl($term))?>"><?=jyp_h($term['name'])?></a><?php endforeach; ?></div><?php endif; ?>
            <h2><a href="<?=jyp_h($profileUrl)?>"><?=jyp_h($name)?></a></h2>
            <?php if ($profile['credentials']): ?><p class="jyp-card__credentials"><?=jyp_h($profile['credentials'])?></p><?php endif; ?>
            <?php if ($profile['position_title']): ?><p class="jyp-card__position"><?=jyp_h($profile['position_title'])?></p><?php endif; ?>
            <?php if ($profile['organization_unit']): ?><p class="jyp-card__unit"><?=jyp_h($profile['organization_unit'])?></p><?php endif; ?>
            <?php if ($profile['links'] !== []): ?><div class="jyp-card__links" aria-label="<?=jyp_h(jyp_t('Professional links'))?>"><?php foreach (array_slice($profile['links'], 0, 3) as $link): ?><a href="<?=jyp_h($link['url'])?>"><?=jyp_h($link['label'])?></a><?php endforeach; ?></div><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
    <?php if ($pageCount > 1): ?>
      <nav class="jyp-pagination" aria-label="<?=jyp_h(jyp_t('Directory pages'))?>">
        <?php if ($currentPage > 1): ?><a class="jyp-pagination__direction" href="<?=jyp_h($queryForPage($currentPage - 1))?>" rel="prev"><?=jyp_h(jyp_t('Previous'))?></a><?php endif; ?>
        <div class="jyp-pagination__pages"><?php $previousPage = 0; foreach ($visiblePages as $page): ?><?php if ($previousPage > 0 && $page - $previousPage > 1): ?><span aria-hidden="true">...</span><?php endif; ?><?php if ($page === $currentPage): ?><span aria-current="page" aria-label="<?=jyp_h(jyp_t('Page %d of %d', $page, $pageCount))?>"><?=$page?></span><?php else: ?><a href="<?=jyp_h($queryForPage($page))?>" aria-label="<?=jyp_h(jyp_t('Page %d of %d', $page, $pageCount))?>"><?=$page?></a><?php endif; ?><?php $previousPage = $page; endforeach; ?></div>
        <?php if ($currentPage < $pageCount): ?><a class="jyp-pagination__direction" href="<?=jyp_h($queryForPage($currentPage + 1))?>" rel="next"><?=jyp_h(jyp_t('Next'))?></a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
