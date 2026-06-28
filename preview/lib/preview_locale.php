<?php

/**
 * Bootstrap preview locale: resolve language, load i18n store.
 * PHP 5.6 compatible.
 */

require_once __DIR__ . '/language_resolver.php';
require_once __DIR__ . '/i18n.php';

if (!function_exists('preview_resolve_data_dir')) {
    function preview_resolve_data_dir()
    {
        $dataDir = dirname(dirname(__DIR__)) . '/data';
        if (!is_readable($dataDir . '/catalog.json')) {
            $dataDir = '/srv/www/deaf.city/public_html/data';
        }

        return $dataDir;
    }
}

if (!function_exists('preview_language_ids_from_config')) {
    /** @return list<string> */
    function preview_language_ids_from_config($configPath)
    {
        if (!is_readable($configPath)) {
            return array('en');
        }

        $raw = file_get_contents($configPath);
        $cfg = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($cfg) || !isset($cfg['subtitle_languages']) || !is_array($cfg['subtitle_languages'])) {
            return array('en');
        }

        $ids = array();
        foreach ($cfg['subtitle_languages'] as $item) {
            if (!empty($item['id'])) {
                $ids[] = (string) $item['id'];
            }
        }

        return $ids !== array() ? $ids : array('en');
    }
}

if (!function_exists('preview_arabic_language_order')) {
    /** @return list<string> */
    function preview_arabic_language_order(array $languageIds)
    {
        $arabic = array();
        foreach ($languageIds as $id) {
            if ($id === 'arq' || $id === 'aeb') {
                $arabic[] = $id;
            }
        }

        return $arabic;
    }
}

if (!function_exists('preview_bootstrap_locale')) {
    /**
     * @return array{lang: string, dir: string, i18n: PreviewI18n, languageIds: list<string>, completeness: array<string, bool>}
     */
    function preview_bootstrap_locale()
    {
        $dataDir = preview_resolve_data_dir();
        $storePath = $dataDir . '/ui-localizations.json';
        $configPath = $dataDir . '/studio-config.json';

        $entries = preview_i18n_load_store($storePath);
        $languageIds = preview_language_ids_from_config($configPath);
        $completeness = preview_i18n_compute_completeness($entries, $languageIds);
        $arabicOrder = preview_arabic_language_order($languageIds);

        $acceptLang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $langOverride = isset($_GET['lang']) ? trim((string) $_GET['lang']) : null;
        if ($langOverride === '') {
            $langOverride = null;
        }

        $resolvedLang = vpc_resolve_language(
            $acceptLang,
            $langOverride,
            $languageIds,
            $completeness,
            $arabicOrder
        );

        $i18n = new PreviewI18n($entries, $resolvedLang);
        $dir = ($resolvedLang === 'arq' || $resolvedLang === 'aeb') ? 'rtl' : 'ltr';

        return array(
            'lang' => $resolvedLang,
            'dir' => $dir,
            'i18n' => $i18n,
            'languageIds' => $languageIds,
            'completeness' => $completeness,
        );
    }
}

/** @var PreviewI18n|null */
$preview_i18n = null;

if (!function_exists('preview_t')) {
    function preview_t($key)
    {
        global $preview_i18n;
        if ($preview_i18n instanceof PreviewI18n) {
            return $preview_i18n->t($key);
        }

        return $key;
    }
}

if (!function_exists('preview_localize_filter_options')) {
    /**
     * @param array<int, array<string, mixed>> $options
     * @param string $contentType sign_language|edition|typology
     * @return array<int, array<string, mixed>>
     */
    function preview_localize_filter_options(array $options, $contentType)
    {
        global $preview_i18n;
        if (!($preview_i18n instanceof PreviewI18n) || $preview_i18n->getLang() === 'en') {
            return $options;
        }

        $localized = array();
        foreach ($options as $opt) {
            if (!isset($opt['value'])) {
                $localized[] = $opt;
                continue;
            }

            $id = (string) $opt['value'];
            $labelKey = 'content.' . $contentType . '.' . $id . '.label';
            $shortKey = 'content.' . $contentType . '.' . $id . '.short_label';

            $label = $preview_i18n->tActive($labelKey);
            if ($label !== '') {
                $opt['label'] = $label;
            }

            if (isset($opt['short_label'])) {
                $short = $preview_i18n->tActive($shortKey);
                if ($short !== '') {
                    $opt['short_label'] = $short;
                }
            }

            $localized[] = $opt;
        }

        return $localized;
    }
}
