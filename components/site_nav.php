<?php
require_once dirname(__DIR__) . '/lib/site_nav_builder.php';

$current = isset($currentRoute) ? $currentRoute : '';
$placement = isset($navPlacement) ? $navPlacement : 'chrome';
if ($placement === 'page') {
    $placement = 'navbar';
}
$activeCollections = (isset($activeCollections) && is_array($activeCollections)) ? $activeCollections : array();
$navLang = isset($preview_lang) ? (string) $preview_lang : 'en';
$links = preview_build_site_nav_links($current, $placement, $activeCollections, $navLang);

if (count($links) === 0 && $placement !== 'navbar') {
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
<?php if ($placement === 'navbar'): ?>
    <?php
    if (!function_exists('preview_t')) {
        require_once dirname(__DIR__) . '/lib/preview_locale.php';
    }
    $langOptions = preview_build_language_switcher_options($current, $navLang);
    if (count($langOptions) > 1):
        $langLabel = preview_t('player.nav.language');
        $currentLangLabel = $langLabel;
        foreach ($langOptions as $opt) {
            if (!empty($opt['selected'])) {
                $currentLangLabel = $opt['label'];
                break;
            }
        }
        $langActive = ($navLang !== 'en');
    ?>
    <div
        class="vpc-picker preview-lang-picker"
        id="preview-lang-picker"
        data-picker="language"
        data-active="<?= $langActive ? 'true' : 'false' ?>"
    >
        <button
            type="button"
            id="preview-lang-picker-btn"
            class="vpc-picker-btn"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-controls="preview-lang-dropdown"
            data-generic-label="<?= htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8') ?>"
        ><?= htmlspecialchars($currentLangLabel, ENT_QUOTES, 'UTF-8') ?></button>
        <ul
            role="listbox"
            id="preview-lang-dropdown"
            class="vpc-picker-dropdown"
            aria-label="<?= htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8') ?>"
            hidden
        >
            <?php foreach ($langOptions as $opt): ?>
            <li
                role="option"
                class="vpc-picker-option"
                data-href="<?= htmlspecialchars($opt['href'], ENT_QUOTES, 'UTF-8') ?>"
                aria-selected="<?= !empty($opt['selected']) ? 'true' : 'false' ?>"
            ><?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
<?php endif; ?>
</nav>
<?php if ($placement === 'navbar' && isset($langOptions) && count($langOptions) > 1): ?>
<script>
(function () {
    'use strict';

    var picker = document.getElementById('preview-lang-picker');
    if (!picker) { return; }

    var btn = document.getElementById('preview-lang-picker-btn');
    var dropdown = document.getElementById('preview-lang-dropdown');
    if (!btn || !dropdown) { return; }

    function closePicker() {
        dropdown.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = !dropdown.hidden;
        closePicker();
        if (!isOpen) {
            dropdown.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
        }
    });

    dropdown.addEventListener('click', function (e) {
        var target = e.target;
        if (!target || !target.classList || !target.classList.contains('vpc-picker-option')) { return; }
        e.stopPropagation();
        var href = target.getAttribute('data-href');
        if (href) { window.location.href = href; }
    });

    dropdown.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePicker();
            btn.focus();
        }
    });

    document.addEventListener('click', closePicker);
}());
</script>
<?php endif; ?>
