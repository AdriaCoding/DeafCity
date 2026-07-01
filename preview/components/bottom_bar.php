<?php
/**
 * Unified bottom bar — site nav + language picker (+ transport/filters in player mode).
 *
 * Pass configuration as $bottomBar before including:
 *
 *   $bottomBar = array(
 *     'mode' => 'nav',              // 'nav' | 'player'
 *     'current_route' => 'about',   // 'home' | 'about' | 'participants'
 *     'lang' => 'en',
 *     'active_collections' => array(), // optional, player participant context
 *   );
 *
 * Player mode additionally requires keys under $bottomBar['player'] — see vimeo_caption_player.php.
 */
require_once dirname(__DIR__) . '/lib/site_nav_builder.php';

if (!isset($bottomBar) || !is_array($bottomBar)) {
    trigger_error('$bottomBar array is required before including bottom_bar.php', E_USER_WARNING);
    return;
}

if (!function_exists('preview_t')) {
    require_once dirname(__DIR__) . '/lib/preview_locale.php';
}

$mode = isset($bottomBar['mode']) ? (string) $bottomBar['mode'] : 'nav';
$currentRoute = isset($bottomBar['current_route']) ? (string) $bottomBar['current_route'] : '';
$navLang = isset($bottomBar['lang'])
    ? (string) $bottomBar['lang']
    : (isset($preview_lang) ? (string) $preview_lang : 'en');
$activeCollections = (isset($bottomBar['active_collections']) && is_array($bottomBar['active_collections']))
    ? $bottomBar['active_collections']
    : array();

$links = preview_build_site_nav_links($currentRoute, 'bottom', $activeCollections, $navLang);

$langOptions = preview_build_language_switcher_options($currentRoute, $navLang);
$langLabel = preview_t('player.nav.language');
$currentLangLabel = $langLabel;
foreach ($langOptions as $opt) {
    if (!empty($opt['selected'])) {
        $currentLangLabel = $opt['label'];
        break;
    }
}
$langActive = ($navLang !== 'en');
$langPickerId = 'preview-lang-picker';
$langPickerBtnId = 'preview-lang-picker-btn';
$langDropdownId = 'preview-lang-dropdown';

$barClass = 'vpc-bottom-bar vpc-bottom-bar--' . ($mode === 'player' ? 'player' : 'nav');

if (!function_exists('preview_render_nav_link')) {
    function preview_render_nav_link($link)
    {
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
        <?php
    }
}

if (!function_exists('preview_render_lang_picker')) {
    function preview_render_lang_picker($langOptions, $langLabel, $currentLangLabel, $langActive, $langPickerId, $langPickerBtnId, $langDropdownId)
    {
        if (count($langOptions) <= 1) {
            return;
        }
        ?>
        <div
            class="vpc-picker preview-lang-picker"
            id="<?= htmlspecialchars($langPickerId, ENT_QUOTES, 'UTF-8') ?>"
            data-picker="language"
            data-active="<?= $langActive ? 'true' : 'false' ?>"
        >
            <button
                type="button"
                id="<?= htmlspecialchars($langPickerBtnId, ENT_QUOTES, 'UTF-8') ?>"
                class="vpc-picker-btn"
                aria-haspopup="listbox"
                aria-expanded="false"
                aria-controls="<?= htmlspecialchars($langDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                data-generic-label="<?= htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8') ?>"
            ><?= htmlspecialchars($currentLangLabel, ENT_QUOTES, 'UTF-8') ?></button>
            <ul
                role="listbox"
                id="<?= htmlspecialchars($langDropdownId, ENT_QUOTES, 'UTF-8') ?>"
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
        <?php
    }
}

if (!function_exists('preview_nav_links_for_routes')) {
    /**
     * @param array<int, array<string, mixed>> $links
     * @param array<int, string> $routes
     * @return array<int, array<string, mixed>>
     */
    function preview_nav_links_for_routes($links, $routes)
    {
        $byRoute = array();
        foreach ($links as $link) {
            $byRoute[$link['route']] = $link;
        }
        $out = array();
        foreach ($routes as $route) {
            if (isset($byRoute[$route])) {
                $out[] = $byRoute[$route];
            }
        }
        return $out;
    }
}

/**
 * Core nav cluster present on every page: Player, About, Participants, Language.
 */
if (!function_exists('preview_render_bottom_bar_chrome')) {
    function preview_render_bottom_bar_chrome($links, $langOptions, $langLabel, $currentLangLabel, $langActive, $langPickerId, $langPickerBtnId, $langDropdownId)
    {
        $chromeLinks = preview_nav_links_for_routes($links, array('home', 'about', 'participants'));
        ?>
        <div class="vpc-bar-zone vpc-bar-zone--chrome">
            <nav class="preview-site-nav vpc-site-nav-wrap" aria-label="Site">
            <?php foreach ($chromeLinks as $link): ?>
                <?php preview_render_nav_link($link); ?>
            <?php endforeach; ?>
            </nav>
            <?php preview_render_lang_picker($langOptions, $langLabel, $currentLangLabel, $langActive, $langPickerId, $langPickerBtnId, $langDropdownId); ?>
        </div>
        <?php
    }
}

/**
 * Render nav links + unified language picker (legacy wrapper — nav mode uses chrome zone).
 */
if (!function_exists('preview_render_bottom_bar_nav')) {
    function preview_render_bottom_bar_nav($links, $langOptions, $langLabel, $currentLangLabel, $langActive, $langPickerId, $langPickerBtnId, $langDropdownId)
    {
        preview_render_bottom_bar_chrome($links, $langOptions, $langLabel, $currentLangLabel, $langActive, $langPickerId, $langPickerBtnId, $langDropdownId);
    }
}

if ($mode === 'player'):
    $playerCfg = isset($bottomBar['player']) && is_array($bottomBar['player']) ? $bottomBar['player'] : array();
    $transportId = isset($playerCfg['transport_id']) ? (string) $playerCfg['transport_id'] : '';
    $iframeId = isset($playerCfg['iframe_id']) ? (string) $playerCfg['iframe_id'] : '';
    $navHiddenClass = isset($playerCfg['nav_hidden_class']) ? (string) $playerCfg['nav_hidden_class'] : '';
    $showR2FilterRow = !empty($playerCfg['show_r2_filter_row']);
    $signLangPickerId = isset($playerCfg['sign_lang_picker_id']) ? (string) $playerCfg['sign_lang_picker_id'] : '';
    $signLangDropdownId = isset($playerCfg['sign_lang_dropdown_id']) ? (string) $playerCfg['sign_lang_dropdown_id'] : '';
    $signLangPickerBtnId = isset($playerCfg['sign_lang_picker_btn_id']) ? (string) $playerCfg['sign_lang_picker_btn_id'] : '';
    $editionPickerId = isset($playerCfg['edition_picker_id']) ? (string) $playerCfg['edition_picker_id'] : '';
    $editionDropdownId = isset($playerCfg['edition_dropdown_id']) ? (string) $playerCfg['edition_dropdown_id'] : '';
    $editionPickerBtnId = isset($playerCfg['edition_picker_btn_id']) ? (string) $playerCfg['edition_picker_btn_id'] : '';
    $typologyPickerId = isset($playerCfg['typology_picker_id']) ? (string) $playerCfg['typology_picker_id'] : '';
    $typologyDropdownId = isset($playerCfg['typology_dropdown_id']) ? (string) $playerCfg['typology_dropdown_id'] : '';
    $typologyPickerBtnId = isset($playerCfg['typology_picker_btn_id']) ? (string) $playerCfg['typology_picker_btn_id'] : '';
    $useSignLanguageFilter = !empty($playerCfg['use_sign_language_filter']);
    $useEditionFilter = !empty($playerCfg['use_edition_filter']);
    $useTypologyFilter = !empty($playerCfg['use_typology_filter']);
    $signLangOptionsList = isset($playerCfg['sign_lang_options']) && is_array($playerCfg['sign_lang_options'])
        ? $playerCfg['sign_lang_options'] : array();
    $editionOptionsList = isset($playerCfg['edition_options']) && is_array($playerCfg['edition_options'])
        ? $playerCfg['edition_options'] : array();
    $typologyOptionsList = isset($playerCfg['typology_options']) && is_array($playerCfg['typology_options'])
        ? $playerCfg['typology_options'] : array();
    $initialSignLangReadout = isset($playerCfg['initial_sign_lang_readout']) ? (string) $playerCfg['initial_sign_lang_readout'] : '';
    $initialEditionReadout = isset($playerCfg['initial_edition_readout']) ? (string) $playerCfg['initial_edition_readout'] : '';
    $initialTypologyReadout = isset($playerCfg['initial_typology_readout']) ? (string) $playerCfg['initial_typology_readout'] : '';
    $hasFilters = $useSignLanguageFilter || $useEditionFilter || $useTypologyFilter;
    ?>
<div class="<?= htmlspecialchars($barClass, ENT_QUOTES, 'UTF-8') ?>">
    <div
        id="<?= htmlspecialchars($transportId, ENT_QUOTES, 'UTF-8') ?>"
        class="vpc-control-row"
    >
        <?php preview_render_bottom_bar_chrome($links, $langOptions, $langLabel, $currentLangLabel, $langActive, $langPickerId, $langPickerBtnId, $langDropdownId); ?>
        <div
            class="vpc-bar-zone vpc-bar-zone--transport vpc-control-transport-cluster"
            role="group"
            aria-label="<?= htmlspecialchars(preview_t('player.aria.playback'), ENT_QUOTES, 'UTF-8') ?>"
        >
            <button
                type="button"
                class="vpc-prev-btn<?= htmlspecialchars($navHiddenClass, ENT_QUOTES, 'UTF-8') ?>"
                aria-controls="<?= htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8') ?>"
                aria-label="<?= htmlspecialchars(preview_t('player.transport.prev'), ENT_QUOTES, 'UTF-8') ?>"
            ><span class="material-icons" aria-hidden="true">skip_previous</span></button>
            <div class="vpc-control-center">
                <button
                    type="button"
                    class="vpc-play-pause-btn"
                    aria-controls="<?= htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="<?= htmlspecialchars(preview_t('player.transport.play'), ENT_QUOTES, 'UTF-8') ?>"
                ><span class="material-icons" aria-hidden="true">play_arrow</span></button>
            </div>
            <button
                type="button"
                class="vpc-next-btn<?= htmlspecialchars($navHiddenClass, ENT_QUOTES, 'UTF-8') ?>"
                aria-controls="<?= htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8') ?>"
                aria-label="<?= htmlspecialchars(preview_t('player.transport.next'), ENT_QUOTES, 'UTF-8') ?>"
            ><span class="material-icons" aria-hidden="true">skip_next</span></button>
        </div>
        <div class="vpc-bar-zone vpc-bar-zone--filters vpc-r2-filters" role="group" aria-label="<?= htmlspecialchars(preview_t('player.aria.filters'), ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($useTypologyFilter): ?>
            <div
                class="vpc-picker"
                id="<?= htmlspecialchars($typologyPickerId, ENT_QUOTES, 'UTF-8') ?>"
                data-picker="typology"
                data-active="false"
            >
                <button
                    type="button"
                    id="<?= htmlspecialchars($typologyPickerBtnId, ENT_QUOTES, 'UTF-8') ?>"
                    class="vpc-picker-btn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="<?= htmlspecialchars($typologyDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                    data-generic-label="<?= htmlspecialchars(preview_t('player.filter.typology'), ENT_QUOTES, 'UTF-8') ?>"
                ><?= htmlspecialchars($initialTypologyReadout, ENT_QUOTES, 'UTF-8') ?></button>
                <ul
                    role="listbox"
                    id="<?= htmlspecialchars($typologyDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                    class="vpc-picker-dropdown"
                    aria-label="<?= htmlspecialchars(preview_t('player.filter.typology'), ENT_QUOTES, 'UTF-8') ?>"
                    hidden
                >
                    <li
                        role="option"
                        class="vpc-picker-option vpc-picker-clear"
                        data-value=""
                        aria-selected="true"
                    ><?= htmlspecialchars(preview_t('player.filter.all_typologies'), ENT_QUOTES, 'UTF-8') ?></li>
                    <?php foreach ($typologyOptionsList as $opt): ?>
                        <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                    <li
                        role="option"
                        class="vpc-picker-option"
                        data-value="<?= htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-selected="false"
                    ><?= htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($useSignLanguageFilter): ?>
            <div
                class="vpc-picker"
                id="<?= htmlspecialchars($signLangPickerId, ENT_QUOTES, 'UTF-8') ?>"
                data-picker="sign_language"
                data-active="false"
            >
                <button
                    type="button"
                    id="<?= htmlspecialchars($signLangPickerBtnId, ENT_QUOTES, 'UTF-8') ?>"
                    class="vpc-picker-btn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="<?= htmlspecialchars($signLangDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                    data-generic-label="<?= htmlspecialchars(preview_t('player.filter.sign_language'), ENT_QUOTES, 'UTF-8') ?>"
                ><?= htmlspecialchars($initialSignLangReadout, ENT_QUOTES, 'UTF-8') ?></button>
                <ul
                    role="listbox"
                    id="<?= htmlspecialchars($signLangDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                    class="vpc-picker-dropdown"
                    aria-label="<?= htmlspecialchars(preview_t('player.filter.sign_language'), ENT_QUOTES, 'UTF-8') ?>"
                    hidden
                >
                    <li
                        role="option"
                        class="vpc-picker-option vpc-picker-clear"
                        data-value=""
                        aria-selected="true"
                    ><?= htmlspecialchars(preview_t('player.filter.all_sign_languages'), ENT_QUOTES, 'UTF-8') ?></li>
                    <?php foreach ($signLangOptionsList as $opt): ?>
                        <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                    <li
                        role="option"
                        class="vpc-picker-option"
                        data-value="<?= htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-selected="false"
                    ><?= htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($useEditionFilter): ?>
            <div
                class="vpc-picker"
                id="<?= htmlspecialchars($editionPickerId, ENT_QUOTES, 'UTF-8') ?>"
                data-picker="edition"
                data-active="false"
            >
                <button
                    type="button"
                    id="<?= htmlspecialchars($editionPickerBtnId, ENT_QUOTES, 'UTF-8') ?>"
                    class="vpc-picker-btn"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="<?= htmlspecialchars($editionDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                    data-generic-label="<?= htmlspecialchars(preview_t('player.filter.city_edition'), ENT_QUOTES, 'UTF-8') ?>"
                ><?= htmlspecialchars($initialEditionReadout, ENT_QUOTES, 'UTF-8') ?></button>
                <ul
                    role="listbox"
                    id="<?= htmlspecialchars($editionDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                    class="vpc-picker-dropdown"
                    aria-label="<?= htmlspecialchars(preview_t('player.filter.city_edition'), ENT_QUOTES, 'UTF-8') ?>"
                    hidden
                >
                    <li
                        role="option"
                        class="vpc-picker-option vpc-picker-clear"
                        data-value=""
                        aria-selected="true"
                    ><?= htmlspecialchars(preview_t('player.filter.all_cities'), ENT_QUOTES, 'UTF-8') ?></li>
                    <?php foreach ($editionOptionsList as $opt): ?>
                        <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                    <li
                        role="option"
                        class="vpc-picker-option"
                        data-value="<?= htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-selected="false"
                    ><?= htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <button
                type="button"
                class="vpc-reset-btn"
                aria-controls="<?= htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8') ?>"
                aria-label="<?= htmlspecialchars(preview_t('player.transport.reset'), ENT_QUOTES, 'UTF-8') ?>"
            ><span class="material-icons" aria-hidden="true">replay</span></button>
        </div>
    </div>
</div>
    <?php
else:
    ?>
<div class="<?= htmlspecialchars($barClass, ENT_QUOTES, 'UTF-8') ?>">
    <?php preview_render_bottom_bar_nav($links, $langOptions, $langLabel, $currentLangLabel, $langActive, $langPickerId, $langPickerBtnId, $langDropdownId); ?>
</div>
    <?php
endif;

if (count($langOptions) > 1):
?>
<script>
(function () {
    'use strict';

    var picker = document.getElementById('<?= htmlspecialchars($langPickerId, ENT_QUOTES, 'UTF-8') ?>');
    if (!picker) { return; }

    var btn = document.getElementById('<?= htmlspecialchars($langPickerBtnId, ENT_QUOTES, 'UTF-8') ?>');
    var dropdown = document.getElementById('<?= htmlspecialchars($langDropdownId, ENT_QUOTES, 'UTF-8') ?>');
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
