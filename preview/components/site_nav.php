<?php
$current = isset($currentRoute) ? $currentRoute : '';
$navClass = isset($navClass) ? $navClass : 'preview-site-nav';
?>
<nav class="<?= htmlspecialchars($navClass) ?>" aria-label="Site">
    <a href="/preview/" class="preview-site-nav__link<?= $current === 'home' ? ' is-active' : '' ?>">Player</a>
    <a href="/preview/about" class="preview-site-nav__link<?= $current === 'about' ? ' is-active' : '' ?>">About</a>
</nav>
