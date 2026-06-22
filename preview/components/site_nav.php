<?php
$current = isset($currentRoute) ? $currentRoute : '';
$placement = isset($navPlacement) ? $navPlacement : 'chrome';
$navClass = 'preview-site-nav preview-site-nav--' . $placement;
$items = array(
    array(
        'route' => 'home',
        'href' => '/preview/',
        'chrome_label' => 'Player',
        'page_label' => 'go back to player',
    ),
    array(
        'route' => 'about',
        'href' => '/preview/about',
        'chrome_label' => 'About',
        'page_label' => 'About',
    ),
    array(
        'route' => 'participants',
        'href' => '/preview/participants',
        'chrome_label' => 'Participants',
        'page_label' => 'Participants',
        'collection' => 'participants',
    ),
    // Tags page (next sprint): same data-collection + is-active pattern as participants.
);
$activeCollections = (isset($activeCollections) && is_array($activeCollections)) ? $activeCollections : array();
$links = array();
foreach ($items as $item) {
    if ($current !== $item['route']) {
        $links[] = $item;
    }
}
if (count($links) === 0) {
    return;
}
$useLinkStyle = ($placement === 'page');
$linkClass = $useLinkStyle ? 'preview-site-nav__link' : 'preview-site-nav__btn';
?>
<nav class="<?= htmlspecialchars($navClass, ENT_QUOTES, 'UTF-8') ?>" aria-label="Site">
<?php foreach ($links as $item): ?>
    <?php
    $text = ($useLinkStyle && isset($item['page_label']))
        ? $item['page_label']
        : $item['chrome_label'];
    ?>
    <?php
    $collectionKey = isset($item['collection']) ? (string) $item['collection'] : '';
    $collectionActive = ($collectionKey !== '' && !$useLinkStyle
        && isset($activeCollections[$collectionKey])
        && trim((string) $activeCollections[$collectionKey]) !== '');
    $btnClass = $linkClass . ($collectionActive ? ' is-active' : '');
    if ($collectionActive) {
        $text = trim((string) $activeCollections[$collectionKey]);
    }
    $collectionAttr = ($collectionKey !== '' && !$useLinkStyle)
        ? ' data-collection="' . htmlspecialchars($collectionKey, ENT_QUOTES, 'UTF-8') . '"'
          . ' data-generic-label="' . htmlspecialchars($item['chrome_label'], ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $ariaCurrent = $collectionActive ? ' aria-current="true"' : '';
    ?>
    <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8') ?>"<?= $collectionAttr ?><?= $ariaCurrent ?>><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
</nav>
