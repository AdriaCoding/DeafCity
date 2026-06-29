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

if (!function_exists('preview_edition_year_from_id')) {
    function preview_edition_year_from_id($editionId)
    {
        if (preg_match('/^(\d{4})-/', (string) $editionId, $m)) {
            return $m[1];
        }

        return '';
    }
}

if (!function_exists('preview_edition_full_label')) {
    /**
     * Compose full edition label from city name + year extracted from edition id.
     * Year suffix vs prefix follows the English studio-config label pattern.
     */
    function preview_edition_full_label($editionId, $cityName, $englishFullLabel)
    {
        $year = preview_edition_year_from_id($editionId);
        $city = trim((string) $cityName);
        if ($year === '' || $city === '') {
            return $city;
        }

        $reference = trim((string) $englishFullLabel);
        if ($reference !== '' && preg_match('/\s\d{4}$/', $reference)) {
            return $city . ' ' . $year;
        }

        return $year . ' ' . $city;
    }
}

if (!function_exists('preview_typology_caps_label')) {
    /** ALL CAPS display form for typology dropdown labels. */
    function preview_typology_caps_label($canonical)
    {
        return mb_strtoupper((string) $canonical, 'UTF-8');
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

        if ($contentType === 'typology' && $preview_i18n instanceof PreviewI18n) {
            $localized = array();
            foreach ($options as $opt) {
                if (!isset($opt['value'])) {
                    $localized[] = $opt;
                    continue;
                }

                $id = (string) $opt['value'];
                $key = 'content.typology.' . $id;
                if ($preview_i18n->getLang() === 'en') {
                    $canonical = $preview_i18n->t($key);
                    if ($canonical !== $key) {
                        $opt['short_label'] = $canonical;
                        $opt['label'] = preview_typology_caps_label($canonical);
                    }
                } else {
                    $canonical = $preview_i18n->tActive($key);
                    if ($canonical !== '') {
                        $opt['short_label'] = $canonical;
                        $opt['label'] = preview_typology_caps_label($canonical);
                    }
                }

                $localized[] = $opt;
            }

            return $localized;
        }

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

            if ($contentType === 'edition') {
                $englishLabel = isset($opt['label']) ? (string) $opt['label'] : '';
                $city = $preview_i18n->tActive('content.edition.' . $id);
                if ($city !== '') {
                    $opt['short_label'] = $city;
                    $opt['label'] = preview_edition_full_label($id, $city, $englishLabel);
                }
                $localized[] = $opt;
                continue;
            }

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
