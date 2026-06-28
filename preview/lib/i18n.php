<?php

/**
 * Read-only preview i18n loader. PHP 5.6 compatible.
 */

if (!function_exists('preview_i18n_chrome_sections')) {
    /** @return list<string> */
    function preview_i18n_chrome_sections()
    {
        return array('player', 'about', 'participants');
    }
}

if (!function_exists('preview_i18n_load_store')) {
    /**
     * @return array<string, array<string, mixed>>
     */
    function preview_i18n_load_store($path)
    {
        if (!is_string($path) || $path === '' || !is_readable($path)) {
            return array();
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return array();
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }
}

if (!function_exists('preview_i18n_compute_completeness')) {
    /**
     * @param array<string, array<string, mixed>> $entries
     * @param list<string> $languageIds
     * @return array<string, bool>
     */
    function preview_i18n_compute_completeness(array $entries, array $languageIds)
    {
        $chromeSections = preview_i18n_chrome_sections();
        $chromeKeys = array();
        foreach ($entries as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $section = isset($entry['section']) ? (string) $entry['section'] : '';
            if (in_array($section, $chromeSections, true)) {
                $chromeKeys[] = $key;
            }
        }

        $result = array();
        foreach ($languageIds as $langId) {
            $complete = true;
            foreach ($chromeKeys as $key) {
                $entry = $entries[$key];
                $translations = isset($entry['translations']) && is_array($entry['translations'])
                    ? $entry['translations']
                    : array();
                $value = isset($translations[$langId]) ? trim((string) $translations[$langId]) : '';
                if ($value === '') {
                    $complete = false;
                    break;
                }
            }
            $result[$langId] = $complete;
        }

        return $result;
    }
}

if (!class_exists('PreviewI18n')) {
    class PreviewI18n
    {
        /** @var array<string, array<string, mixed>> */
        private $entries;

        /** @var string */
        private $lang;

        /**
         * @param array<string, array<string, mixed>> $entries
         */
        public function __construct(array $entries, $lang)
        {
            $this->entries = $entries;
            $this->lang = (string) $lang;
        }

        public function getLang()
        {
            return $this->lang;
        }

        public function t($key)
        {
            $key = (string) $key;
            if (!isset($this->entries[$key]) || !is_array($this->entries[$key])) {
                return $key;
            }

            $translations = isset($this->entries[$key]['translations']) && is_array($this->entries[$key]['translations'])
                ? $this->entries[$key]['translations']
                : array();

            if (isset($translations[$this->lang])) {
                $value = trim((string) $translations[$this->lang]);
                if ($value !== '') {
                    return $value;
                }
            }

            if (isset($translations['en'])) {
                $value = trim((string) $translations['en']);
                if ($value !== '') {
                    return $value;
                }
            }

            return $key;
        }

        /**
         * Active-language translation only — no en fallback (for content labels
         * where studio-config supplies the live base label).
         */
        public function tActive($key)
        {
            $key = (string) $key;
            if (!isset($this->entries[$key]) || !is_array($this->entries[$key])) {
                return '';
            }

            $translations = isset($this->entries[$key]['translations']) && is_array($this->entries[$key]['translations'])
                ? $this->entries[$key]['translations']
                : array();

            if (isset($translations[$this->lang])) {
                return trim((string) $translations[$this->lang]);
            }

            return '';
        }

        /**
         * Resolved chrome map for vpc-config strings injection.
         *
         * @return array<string, string>
         */
        public function chromeMap()
        {
            $map = array();
            $chromeSections = preview_i18n_chrome_sections();

            foreach ($this->entries as $key => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $section = isset($entry['section']) ? (string) $entry['section'] : '';
                if (!in_array($section, $chromeSections, true)) {
                    continue;
                }
                $map[$key] = $this->t($key);
            }

            return $map;
        }
    }
}
