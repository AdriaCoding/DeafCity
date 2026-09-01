<?php

/**
 * Build bottom bar player-mode configuration for About / Participants pages.
 * Filter options are derived from the visible catalog (same as index.php).
 *
 * PHP 5.6 compatible.
 */

require_once __DIR__ . '/videos_catalog.php';

if (!function_exists('preview_filter_readout_for_value')) {
    /**
     * Full + compact face labels for a filter option (desktop / maketa ≤1024).
     *
     * @param array<int, array<string, mixed>> $optionsList
     * @param string $value
     * @param string $fallback
     * @return array{label: string, short_label: string}
     */
    function preview_filter_readout_for_value(array $optionsList, $value, $fallback)
    {
        $value = (string) $value;
        if ($value === '') {
            return array('label' => $fallback, 'short_label' => $fallback);
        }
        foreach ($optionsList as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            if ((string) $opt['value'] !== $value) {
                continue;
            }
            $full = !empty($opt['label']) && is_string($opt['label'])
                ? (string) $opt['label']
                : $value;
            $short = !empty($opt['short_label']) && is_string($opt['short_label'])
                ? (string) $opt['short_label']
                : $full;
            return array('label' => $full, 'short_label' => $short);
        }

        return array('label' => $fallback, 'short_label' => $fallback);
    }
}

if (!function_exists('preview_build_bottom_bar_player_config')) {
    /**
     * @param string $currentRoute about|participants|home
     * @param string $lang
     * @param string $instanceSuffix Unique suffix for element ids on this page
     * @return array<string, mixed>
     */
    function preview_build_bottom_bar_player_config($currentRoute, $lang, $instanceSuffix)
    {
        if (!function_exists('preview_t')) {
            require_once __DIR__ . '/preview_locale.php';
        }

        $dataDir = preview_resolve_data_dir();
        $catalogJsonPath = $dataDir . '/catalog.json';
        $studioConfigPath = $dataDir . '/studio-config.json';
        $catalog = vpc_load_videos_catalog($catalogJsonPath);
        $collection = vpc_catalog_collection($catalog, $studioConfigPath);

        $signLanguageOptions = preview_localize_filter_options($collection['sign_language_options'], 'sign_language');
        $editionOptions = preview_localize_filter_options($collection['edition_options'], 'edition');
        $typologyOptions = preview_localize_filter_options($collection['typology_options'], 'typology');

        $playlist = $collection['playlist'];
        $initialEntry = count($playlist) > 0 ? $playlist[0] : array();
        $deafHearingEnabled = $collection['deaf_hearing_enabled'];

        $idBase = 'preview-secondary-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $instanceSuffix);
        $transportId = $idBase . '__transport';
        $iframeId = $idBase . '__iframe';

        $initialSignLangReadout = preview_filter_readout_for_value(
            $signLanguageOptions,
            isset($initialEntry['sign_language']) ? $initialEntry['sign_language'] : '',
            preview_t('player.filter.sign_language')
        );
        $initialEditionReadout = preview_filter_readout_for_value(
            $editionOptions,
            isset($initialEntry['edition']) ? $initialEntry['edition'] : '',
            preview_t('player.filter.city_edition')
        );
        $initialTypologyReadout = preview_filter_readout_for_value(
            $typologyOptions,
            isset($initialEntry['typology']) ? $initialEntry['typology'] : '',
            preview_t('player.filter.typology')
        );

        return array(
            'mode' => 'player',
            'current_route' => $currentRoute,
            'lang' => $lang,
            'active_collections' => array(),
            'player' => array(
                'transport_id' => $transportId,
                'iframe_id' => $iframeId,
                'nav_hidden_class' => '',
                'show_r2_filter_row' => count($signLanguageOptions) > 0 || count($editionOptions) > 0,
                'sign_lang_picker_id' => $idBase . '__sign-lang-picker',
                'sign_lang_dropdown_id' => $idBase . '__sign-lang-dropdown',
                'sign_lang_picker_btn_id' => $idBase . '__sign-lang-btn',
                'edition_picker_id' => $idBase . '__edition-picker',
                'edition_dropdown_id' => $idBase . '__edition-dropdown',
                'edition_picker_btn_id' => $idBase . '__edition-btn',
                'typology_picker_id' => $idBase . '__typology-picker',
                'typology_dropdown_id' => $idBase . '__typology-dropdown',
                'typology_picker_btn_id' => $idBase . '__typology-btn',
                'use_sign_language_filter' => count($signLanguageOptions) > 0,
                'use_edition_filter' => count($editionOptions) > 0,
                'use_typology_filter' => count($typologyOptions) > 0,
                'sign_lang_options' => $signLanguageOptions,
                'edition_options' => $editionOptions,
                'typology_options' => $typologyOptions,
                'initial_sign_lang_readout' => $initialSignLangReadout,
                'initial_edition_readout' => $initialEditionReadout,
                'initial_typology_readout' => $initialTypologyReadout,
                'deaf_hearing_enabled' => $deafHearingEnabled,
                'secondary_page' => true,
            ),
        );
    }
}
