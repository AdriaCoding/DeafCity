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
);
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
    <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
</nav>
