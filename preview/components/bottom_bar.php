<?php
/**
 * Unified bottom bar — site nav + language picker + transport/filters (player chrome).
 *
 * Pass configuration as $bottomBar before including:
 *
 *   $bottomBar = array(
 *     'mode' => 'player',
 *     'current_route' => 'about',   // 'home' | 'about' | 'participants'
 *     'lang' => 'en',
 *     'active_collections' => array(), // optional, player participant context
 *     'player' => array(...),          // see preview/lib/bottom_bar_player_config.php
 *   );
 */
require_once dirname(__DIR__) . '/lib/site_nav_builder.php';
require_once dirname(__DIR__) . '/lib/asset_version.php';

if (!isset($bottomBar) || !is_array($bottomBar)) {
    trigger_error('$bottomBar array is required before including bottom_bar.php', E_USER_WARNING);
    return;
}

if (!function_exists('preview_t')) {
    require_once dirname(__DIR__) . '/lib/preview_locale.php';
}

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

$playerCfg = isset($bottomBar['player']) && is_array($bottomBar['player']) ? $bottomBar['player'] : array();
$isSecondaryPage = !empty($playerCfg['secondary_page']);
$barClass = 'vpc-bottom-bar vpc-bottom-bar--player';
$barAttrs = $isSecondaryPage ? ' data-secondary-page="true"' : '';

$transportId = isset($playerCfg['transport_id']) ? (string) $playerCfg['transport_id'] : '';
$iframeId = isset($playerCfg['iframe_id']) ? (string) $playerCfg['iframe_id'] : '';
// On About/Participants there is no in-page iframe (the player only exists on the
// home page) — transport controls there navigate to it instead of controlling it
// in place, so aria-controls must not reference an id that is absent from the page.
$transportAriaControlsAttr = (!$isSecondaryPage && $iframeId !== '')
    ? ' aria-controls="' . htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8') . '"'
    : '';
$navHiddenClass = isset($playerCfg['nav_hidden_class']) ? (string) $playerCfg['nav_hidden_class'] : '';
$transportPrevDisabled = !empty($playerCfg['transport_prev_disabled']);
$transportNextDisabled = !empty($playerCfg['transport_next_disabled']);
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
$initialSignLangReadout = isset($playerCfg['initial_sign_lang_readout'])
    ? $playerCfg['initial_sign_lang_readout'] : '';
$initialEditionReadout = isset($playerCfg['initial_edition_readout'])
    ? $playerCfg['initial_edition_readout'] : '';
$initialTypologyReadout = isset($playerCfg['initial_typology_readout'])
    ? $playerCfg['initial_typology_readout'] : '';
$deafHearingEnabled = !isset($playerCfg['deaf_hearing_enabled']) || !empty($playerCfg['deaf_hearing_enabled']);
$deafHearingAria = preview_t('player.filter.deaf_hearing');
// Keyboard-shortcut hints appended to title/aria-label for discoverability (Space/←/→/R/D).
$deafHearingAriaWithHint = $deafHearingAria . ' (D)';
$resetAriaWithHint = preview_t('player.transport.reset') . ' (R)';
$prevAriaWithHint = preview_t('player.transport.prev') . ' (←)';
$nextAriaWithHint = preview_t('player.transport.next') . ' (→)';
$playAriaWithHint = preview_t('player.transport.play') . ' (' . preview_t('player.transport.space_key') . ')';

if (!function_exists('preview_chrome_btn_face')) {
    /**
     * Normalize a chrome face readout to full + short strings.
     *
     * @param string|array{label?: string, short_label?: string} $readout
     * @return array{0: string, 1: string}
     */
    function preview_chrome_btn_face($readout)
    {
        if (is_array($readout)) {
            $full = isset($readout['label']) ? (string) $readout['label'] : '';
            $short = isset($readout['short_label']) ? (string) $readout['short_label'] : $full;
            return array($full, $short);
        }
        $text = (string) $readout;
        return array($text, $text);
    }
}

if (!function_exists('preview_chrome_btn_label')) {
    /**
     * @param string|array{label?: string, short_label?: string} $text
     * @param string|null $shortText Optional short face when $text is a plain string
     */
    function preview_chrome_btn_label($text, $shortText = null)
    {
        if ($shortText !== null && !is_array($text)) {
            $full = (string) $text;
            $short = (string) $shortText;
        } else {
            $pair = preview_chrome_btn_face($text);
            $full = $pair[0];
            $short = $pair[1];
        }
        return '<span class="vpc-chrome-btn__label">'
            . '<span class="vpc-chrome-btn__label-full">'
            . htmlspecialchars($full, ENT_QUOTES, 'UTF-8')
            . '</span>'
            . '<span class="vpc-chrome-btn__label-short">'
            . htmlspecialchars($short, ENT_QUOTES, 'UTF-8')
            . '</span>'
            . '</span>';
    }
}

if (!function_exists('preview_nav_link_shortcut_key')) {
    /**
     * Keyboard-shortcut letter for a nav route, or '' when the route has none.
     * @param string $route
     * @return string
     */
    function preview_nav_link_shortcut_key($route)
    {
        $keys = array('about' => 'A', 'participants' => 'P');
        return isset($keys[$route]) ? $keys[$route] : '';
    }
}

if (!function_exists('preview_render_nav_link')) {
    function preview_render_nav_link($link)
    {
        $labelKey = isset($link['label_key']) ? (string) $link['label_key'] : '';
        $collectionAttr = '';
        if ($link['data_collection'] !== '') {
            $collectionAttr = ' data-collection="' . htmlspecialchars($link['data_collection'], ENT_QUOTES, 'UTF-8') . '"'
                . ' data-generic-label="' . htmlspecialchars($link['data_generic_label'], ENT_QUOTES, 'UTF-8') . '"';
            if ($labelKey !== '') {
                $collectionAttr .= ' data-i18n-generic="' . htmlspecialchars($labelKey, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        $ariaCurrent = $link['aria_current'] !== ''
            ? ' aria-current="' . htmlspecialchars($link['aria_current'], ENT_QUOTES, 'UTF-8') . '"'
            : '';
        // Shortcut hint on both aria-label and title (this nav label has no fixed-glyph
        // constraint, unlike About — see preview_render_icon_nav_link).
        $shortcutKey = preview_nav_link_shortcut_key($link['route']);
        $hintAttrs = '';
        if ($shortcutKey !== '') {
            $labelWithHint = htmlspecialchars($link['label'] . ' (' . $shortcutKey . ')', ENT_QUOTES, 'UTF-8');
            $hintAttrs = ' aria-label="' . $labelWithHint . '" title="' . $labelWithHint . '"';
            if ($labelKey !== '') {
                $hintAttrs .= ' data-i18n-aria="' . htmlspecialchars($labelKey, ENT_QUOTES, 'UTF-8') . '"'
                    . ' data-i18n-hint="' . htmlspecialchars($shortcutKey, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        ?>
        <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($link['class'], ENT_QUOTES, 'UTF-8') ?>" data-route="<?= htmlspecialchars($link['route'], ENT_QUOTES, 'UTF-8') ?>"<?= $collectionAttr ?><?= $ariaCurrent ?><?= $hintAttrs ?>><?= preview_chrome_btn_label($link['label']) ?></a>
        <?php
    }
}

if (!function_exists('preview_render_icon_nav_link')) {
    function preview_render_icon_nav_link($link, $svgSrc)
    {
        $collectionAttr = '';
        if ($link['data_collection'] !== '') {
            $collectionAttr = ' data-collection="' . htmlspecialchars($link['data_collection'], ENT_QUOTES, 'UTF-8') . '"'
                . ' data-generic-label="' . htmlspecialchars($link['data_generic_label'], ENT_QUOTES, 'UTF-8') . '"';
        }
        $ariaCurrent = $link['aria_current'] !== ''
            ? ' aria-current="' . htmlspecialchars($link['aria_current'], ENT_QUOTES, 'UTF-8') . '"'
            : '';
        $label = htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
        // ADR-0012: the About link's accessible name must stay the bare "?" glyph in every
        // locale — aria-label is untouched. `title` is a mouse-hover-only affordance (not part
        // of the accessible-name computation when aria-label is present), so it's safe to append
        // the shortcut hint there without reintroducing a translated "About" label.
        $shortcutKey = preview_nav_link_shortcut_key($link['route']);
        $titleAttr = $shortcutKey !== ''
            ? ' title="' . htmlspecialchars($link['label'] . ' (' . $shortcutKey . ')', ENT_QUOTES, 'UTF-8') . '"'
            : '';
        ?>
        <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($link['class'], ENT_QUOTES, 'UTF-8') ?> preview-site-nav__btn--icon" data-route="<?= htmlspecialchars($link['route'], ENT_QUOTES, 'UTF-8') ?>"<?= $collectionAttr ?><?= $ariaCurrent ?> aria-label="<?= $label ?>"<?= $titleAttr ?>>
            <img class="vpc-chrome-icon" src="<?= htmlspecialchars($svgSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" width="44" height="44" aria-hidden="true">
            <span class="vpc-chrome-btn__label"><?= $label ?></span>
        </a>
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
                data-i18n-generic="player.nav.language"
            ><?= preview_chrome_btn_label($currentLangLabel) ?></button>
            <ul
                role="listbox"
                id="<?= htmlspecialchars($langDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                class="vpc-picker-dropdown"
                aria-label="<?= htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8') ?>"
                data-i18n-aria="player.nav.language"
                tabindex="-1"
                hidden
            >
                <?php foreach ($langOptions as $optIndex => $opt): ?>
                <li
                    role="option"
                    id="<?= htmlspecialchars($langDropdownId, ENT_QUOTES, 'UTF-8') ?>__opt-<?= (int) $optIndex ?>"
                    class="vpc-picker-option"
                    data-href="<?= htmlspecialchars($opt['href'], ENT_QUOTES, 'UTF-8') ?>"
                    data-lang-id="<?= htmlspecialchars(isset($opt['id']) ? (string) $opt['id'] : '', ENT_QUOTES, 'UTF-8') ?>"
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

$leftNavLinks = preview_nav_links_for_routes($links, array('about'));
$rightNavLinks = preview_nav_links_for_routes($links, array('participants'));

?>
<div class="<?= htmlspecialchars($barClass, ENT_QUOTES, 'UTF-8') ?>"<?= $barAttrs ?>>
    <div
        id="<?= htmlspecialchars($transportId, ENT_QUOTES, 'UTF-8') ?>"
        class="vpc-control-row"
    >
        <div class="vpc-control-secondary">
            <div class="vpc-control-secondary-l">
                <nav class="preview-site-nav vpc-site-nav-wrap vpc-control-zone vpc-control-zone--nav" aria-label="Site">
                <?php foreach ($leftNavLinks as $link): ?>
                    <?php if ($link['route'] === 'about'): ?>
                        <?php preview_render_icon_nav_link($link, '/preview/img/help_80dp_007800.svg'); ?>
                    <?php else: ?>
                        <?php preview_render_nav_link($link); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                </nav>
                <?php preview_render_lang_picker($langOptions, $langLabel, $currentLangLabel, $langActive, $langPickerId, $langPickerBtnId, $langDropdownId); ?>
                <button
                    type="button"
                    class="vpc-chrome-btn vpc-deaf-hearing-btn"
                    aria-pressed="false"
                    aria-label="<?= htmlspecialchars($deafHearingAriaWithHint, ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria="player.filter.deaf_hearing" data-i18n-hint="D"
                    title="<?= htmlspecialchars($deafHearingAriaWithHint, ENT_QUOTES, 'UTF-8') ?>"
                    data-deaf-hearing-tag="<?= htmlspecialchars('DEAF&HEARING', ENT_QUOTES, 'UTF-8') ?>"
                    <?php if (!$deafHearingEnabled): ?>disabled<?php endif; ?>
                >
                    <span class="vpc-chrome-btn__label">DEAF+HEARING</span>
                </button>
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
                        data-i18n-generic="player.filter.typology"
                    ><?= preview_chrome_btn_label($initialTypologyReadout) ?></button>
                    <ul
                        role="listbox"
                        id="<?= htmlspecialchars($typologyDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                        class="vpc-picker-dropdown"
                        aria-label="<?= htmlspecialchars(preview_t('player.filter.typology'), ENT_QUOTES, 'UTF-8') ?>"
                        data-i18n-aria="player.filter.typology"
                        tabindex="-1"
                        hidden
                    >
                        <?php $typologyOptN = 0; ?>
                        <li
                            role="option"
                            id="<?= htmlspecialchars($typologyDropdownId, ENT_QUOTES, 'UTF-8') ?>__opt-<?= $typologyOptN++ ?>"
                            class="vpc-picker-option vpc-picker-clear"
                            data-value=""
                            aria-selected="true"
                        ><?= htmlspecialchars(preview_t('player.filter.all_typologies'), ENT_QUOTES, 'UTF-8') ?></li>
                        <?php foreach ($typologyOptionsList as $opt): ?>
                            <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                        <li
                            role="option"
                            id="<?= htmlspecialchars($typologyDropdownId, ENT_QUOTES, 'UTF-8') ?>__opt-<?= $typologyOptN++ ?>"
                            class="vpc-picker-option"
                            data-value="<?= htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8') ?>"
                            aria-selected="false"
                        ><?= htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <div class="vpc-control-secondary-r">
            <?php if ($useSignLanguageFilter || $useEditionFilter): ?>
            <div class="vpc-r2-filters vpc-control-zone vpc-control-zone--filters" role="group" aria-label="<?= htmlspecialchars(preview_t('player.aria.filters'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria="player.aria.filters">
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
                        data-i18n-generic="player.filter.sign_language"
                    ><?= preview_chrome_btn_label($initialSignLangReadout) ?></button>
                    <ul
                        role="listbox"
                        id="<?= htmlspecialchars($signLangDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                        class="vpc-picker-dropdown"
                        aria-label="<?= htmlspecialchars(preview_t('player.filter.sign_language'), ENT_QUOTES, 'UTF-8') ?>"
                        data-i18n-aria="player.filter.sign_language"
                        tabindex="-1"
                        hidden
                    >
                        <?php $signLangOptN = 0; ?>
                        <li
                            role="option"
                            id="<?= htmlspecialchars($signLangDropdownId, ENT_QUOTES, 'UTF-8') ?>__opt-<?= $signLangOptN++ ?>"
                            class="vpc-picker-option vpc-picker-clear"
                            data-value=""
                            aria-selected="true"
                        ><?= htmlspecialchars(preview_t('player.filter.all_sign_languages'), ENT_QUOTES, 'UTF-8') ?></li>
                        <?php foreach ($signLangOptionsList as $opt): ?>
                            <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                        <li
                            role="option"
                            id="<?= htmlspecialchars($signLangDropdownId, ENT_QUOTES, 'UTF-8') ?>__opt-<?= $signLangOptN++ ?>"
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
                        data-i18n-generic="player.filter.city_edition"
                    ><?= preview_chrome_btn_label($initialEditionReadout) ?></button>
                    <ul
                        role="listbox"
                        id="<?= htmlspecialchars($editionDropdownId, ENT_QUOTES, 'UTF-8') ?>"
                        class="vpc-picker-dropdown"
                        aria-label="<?= htmlspecialchars(preview_t('player.filter.city_edition'), ENT_QUOTES, 'UTF-8') ?>"
                        data-i18n-aria="player.filter.city_edition"
                        tabindex="-1"
                        hidden
                    >
                        <?php $editionOptN = 0; ?>
                        <li
                            role="option"
                            id="<?= htmlspecialchars($editionDropdownId, ENT_QUOTES, 'UTF-8') ?>__opt-<?= $editionOptN++ ?>"
                            class="vpc-picker-option vpc-picker-clear"
                            data-value=""
                            aria-selected="true"
                        ><?= htmlspecialchars(preview_t('player.filter.all_cities'), ENT_QUOTES, 'UTF-8') ?></li>
                        <?php foreach ($editionOptionsList as $opt): ?>
                            <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                        <li
                            role="option"
                            id="<?= htmlspecialchars($editionDropdownId, ENT_QUOTES, 'UTF-8') ?>__opt-<?= $editionOptN++ ?>"
                            class="vpc-picker-option"
                            data-value="<?= htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8') ?>"
                            aria-selected="false"
                        ><?= htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <nav class="preview-site-nav vpc-control-zone vpc-control-zone--nav-r" aria-label="Collections">
                <?php foreach ($rightNavLinks as $link): ?>
                    <?php preview_render_nav_link($link); ?>
                <?php endforeach; ?>
            </nav>
            <button
                type="button"
                class="vpc-reset-btn"
<?= $transportAriaControlsAttr ?>
                aria-label="<?= htmlspecialchars($resetAriaWithHint, ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria="player.transport.reset" data-i18n-hint="R"
                title="<?= htmlspecialchars($resetAriaWithHint, ENT_QUOTES, 'UTF-8') ?>"
            ><img class="vpc-chrome-icon" src="/preview/img/replay_circle_filled_80dp_007800.svg" alt="" width="44" height="44" aria-hidden="true"><span class="vpc-reset-btn__text" data-i18n-text="player.transport.reset_short"><?= htmlspecialchars(preview_t('player.transport.reset_short'), ENT_QUOTES, 'UTF-8') ?></span></button>
            </div>
        </div>
        <div
            class="vpc-control-transport-cluster"
            role="group"
            aria-label="<?= htmlspecialchars(preview_t('player.aria.playback'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria="player.aria.playback"
        ><button
                type="button"
                class="vpc-prev-btn<?= htmlspecialchars($navHiddenClass, ENT_QUOTES, 'UTF-8') ?>"
<?= $transportAriaControlsAttr ?>
                aria-label="<?= htmlspecialchars($prevAriaWithHint, ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria="player.transport.prev" data-i18n-hint="←"
                title="<?= htmlspecialchars($prevAriaWithHint, ENT_QUOTES, 'UTF-8') ?>"
                <?= $transportPrevDisabled ? 'disabled' : '' ?>
            ><img class="vpc-chrome-icon" src="/preview/img/skip_previous_80dp_007800.svg?v=2" alt="" width="44" height="44" aria-hidden="true"></button><div class="vpc-control-center"><button
                    type="button"
                    class="vpc-play-pause-btn"
    <?= $transportAriaControlsAttr ?>
                    aria-label="<?= htmlspecialchars($playAriaWithHint, ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria="player.transport.play" data-i18n-hint-key="player.transport.space_key"
                    title="<?= htmlspecialchars($playAriaWithHint, ENT_QUOTES, 'UTF-8') ?>"
                    data-icon-play="/preview/img/play_circle_80dp_007800.svg"
                    data-icon-pause="/preview/img/pause_circle_80dp_007800.svg"
                ><img class="vpc-chrome-icon" src="/preview/img/play_circle_80dp_007800.svg" alt="" width="48" height="48" aria-hidden="true"><img class="vpc-play-pause-btn__hourglass" src="/preview/img/hourglass_empty_80dp_ffffff.svg" alt="" width="45" height="45" aria-hidden="true"></button></div><button
                type="button"
                class="vpc-next-btn<?= htmlspecialchars($navHiddenClass, ENT_QUOTES, 'UTF-8') ?>"
<?= $transportAriaControlsAttr ?>
                aria-label="<?= htmlspecialchars($nextAriaWithHint, ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria="player.transport.next" data-i18n-hint="→"
                title="<?= htmlspecialchars($nextAriaWithHint, ENT_QUOTES, 'UTF-8') ?>"
                <?= $transportNextDisabled ? 'disabled' : '' ?>
            ><img class="vpc-chrome-icon" src="/preview/img/skip_next_80dp_007800.svg?v=3" alt="" width="44" height="44" aria-hidden="true"></button></div>
    </div>
</div>
<?php

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
    var navIntentKey = 'vpc-nav-intent';
    var playPauseBtn = document.querySelector('.vpc-play-pause-btn');

    function shouldResumeAfterLanguageSwitch() {
        var state = window.__vpcTransportState;
        if (state && typeof state === 'object') {
            if (!!state.playing && !state.loading) {
                return true;
            }
            if (!state.loading && typeof state.currentTimeSec === 'number' && state.currentTimeSec > 0.25) {
                return true;
            }
            return false;
        }
        if (!playPauseBtn) { return false; }
        if (playPauseBtn.getAttribute('data-loading') === 'true') { return false; }
        var icon = playPauseBtn.querySelector('.vpc-chrome-icon');
        if (!icon) { return false; }
        var src = icon.getAttribute('src') || '';
        return src.indexOf('pause_circle') >= 0;
    }

    // ── Keyboard navigation (Up/Down/Home/End move, Enter/Space select, Escape closes) ──
    var options = Array.prototype.slice.call(dropdown.querySelectorAll('.vpc-picker-option'));
    var activeIndex = -1;

    function vpcLogic() {
        return typeof window.VpcPlaylistLogic !== 'undefined' ? window.VpcPlaylistLogic : null;
    }

    function clearActiveOption() {
        options.forEach(function (o) { o.classList.remove('vpc-picker-option--active'); });
    }

    function setActiveIndex(index) {
        if (index < 0 || index >= options.length) return;
        clearActiveOption();
        activeIndex = index;
        var el = options[activeIndex];
        el.classList.add('vpc-picker-option--active');
        dropdown.setAttribute('aria-activedescendant', el.id);
        if (typeof el.scrollIntoView === 'function') el.scrollIntoView({ block: 'nearest' });
    }

    function selectedOptionIndex() {
        for (var i = 0; i < options.length; i++) {
            if (options[i].getAttribute('aria-selected') === 'true') return i;
        }
        return 0;
    }

    function closePicker() {
        dropdown.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
        dropdown.removeAttribute('aria-activedescendant');
        clearActiveOption();
        activeIndex = -1;
    }

    function openPicker() {
        dropdown.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        setActiveIndex(selectedOptionIndex());
        dropdown.focus();
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = !dropdown.hidden;
        closePicker();
        if (!isOpen) openPicker();
    });

    btn.addEventListener('keydown', function (e) {
        var L = vpcLogic();
        if (!L) return;
        var action = L.resolvePickerListboxKeyAction({
            key: e.key,
            activeIndex: activeIndex,
            optionCount: options.length,
            isOpen: !dropdown.hidden,
        });
        if (action.action === 'open') {
            e.preventDefault();
            openPicker();
        }
    });

    /**
     * Legacy path: leave the page to change Website language. Still used on routes
     * with no player of their own, and as the fallback when an in-session switch
     * cannot be performed. Carries resume intent per ADR-0013.
     */
    function navigateToLanguage(href) {
        var shouldResume = shouldResumeAfterLanguageSwitch();
        if (typeof sessionStorage !== 'undefined') {
            try {
                if (shouldResume) {
                    sessionStorage.setItem(navIntentKey, 'play');
                } else {
                    sessionStorage.removeItem(navIntentKey);
                }
            } catch (err) {}
        }
        if (typeof window.__vpcSavePlaybackSession === 'function') {
            window.__vpcSavePlaybackSession(function () {
                window.location.href = href;
            });
            return;
        }
        window.location.href = href;
    }

    function activateOption(target) {
        var href = target.getAttribute('data-href');
        var langId = target.getAttribute('data-lang-id');
        if (!href) { return; }

        closePicker();

        // When a player owns this page the switch happens in session: the Vimeo iframe
        // is never destroyed, so there is no playback to save and no resume intent to
        // carry. Any failure falls back to the navigation path below, so the page can
        // never be left half-translated.
        if (langId && typeof window.__vpcApplyWebsiteLanguage === 'function') {
            window.__vpcApplyWebsiteLanguage(langId)['catch'](function () {
                navigateToLanguage(href);
            });
            return;
        }

        navigateToLanguage(href);
    }

    dropdown.addEventListener('click', function (e) {
        var target = e.target;
        if (!target || !target.classList || !target.classList.contains('vpc-picker-option')) { return; }
        e.stopPropagation();
        activateOption(target);
    });

    dropdown.addEventListener('keydown', function (e) {
        var L = vpcLogic();
        var action = L
            ? L.resolvePickerListboxKeyAction({
                  key: e.key,
                  activeIndex: activeIndex,
                  optionCount: options.length,
                  isOpen: true,
              })
            : (e.key === 'Escape' ? { action: 'close' } : { action: 'none' });

        if (action.action === 'move') {
            e.preventDefault();
            setActiveIndex(action.index);
        } else if (action.action === 'close') {
            e.preventDefault();
            closePicker();
            btn.focus();
        } else if (action.action === 'select') {
            e.preventDefault();
            var el = options[action.index];
            if (el) activateOption(el);
        }
    });

    document.addEventListener('click', closePicker);
}());
</script>
<?php endif; ?>
<script src="<?= htmlspecialchars(preview_asset_url('js/chrome_button_widths.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
