<?php
require_once dirname(__DIR__) . '/lib/site_nav_builder.php';

$current = isset($currentRoute) ? $currentRoute : '';
$placement = isset($navPlacement) ? $navPlacement : 'chrome';
if ($placement === 'page') {
    $placement = 'navbar';
}
$activeCollections = (isset($activeCollections) && is_array($activeCollections)) ? $activeCollections : array();
$links = preview_build_site_nav_links($current, $placement, $activeCollections);

if (count($links) === 0) {
    return;
}

$navClass = 'preview-site-nav preview-site-nav--' . $placement;
?>
<nav class="<?= htmlspecialchars($navClass, ENT_QUOTES, 'UTF-8') ?>" aria-label="Site">
<?php foreach ($links as $link): ?>
    <?php
    $collectionAttr = '';
    if ($link['data_collection'] !== '') {
        $collectionAttr = ' data-collection="' . htmlspecialchars($link['data_collection'], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-generic-label="' . htmlspecialchars($link['data_generic_label'], ENT_QUOTES, 'UTF-8') . '"';
    }
    $ariaCurrent = $link['aria_current'] !== ''
        ? ' aria-current="' . htmlspecialchars($link['aria_current'], ENT_QUOTES, 'UTF-8') . '"'
        : '';
    ?>
    <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($link['class'], ENT_QUOTES, 'UTF-8') ?>"<?= $collectionAttr ?><?= $ariaCurrent ?>><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
</nav>
