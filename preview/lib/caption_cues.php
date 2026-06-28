<?php
/**
 * Caption cue helpers for preview playback (PHP 5.6).
 */

if (!function_exists('vpc_caption_target_max_chars')) {
    /** @return int */
    function vpc_caption_target_max_chars() {
        return 60;
    }
}

if (!function_exists('vpc_caption_max_chars_tolerance')) {
    /** @return int */
    function vpc_caption_max_chars_tolerance() {
        return 5;
    }
}

if (!function_exists('vpc_caption_display_max_length')) {
    /** @return int */
    function vpc_caption_display_max_length() {
        return vpc_caption_target_max_chars() + vpc_caption_max_chars_tolerance();
    }
}

if (!function_exists('vpc_caption_text_length')) {
    /**
     * @param string $text
     * @return int
     */
    function vpc_caption_text_length($text) {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($text, 'UTF-8');
        }
        return strlen($text);
    }
}

if (!function_exists('vpc_caption_text_slice')) {
    /**
     * @param string $text
     * @param int $start
     * @param int $length
     * @return string
     */
    function vpc_caption_text_slice($text, $start, $length) {
        if (function_exists('mb_substr')) {
            return mb_substr($text, $start, $length, 'UTF-8');
        }
        return substr($text, $start, $length);
    }
}

if (!function_exists('vpc_normalize_caption_display_text')) {
    /**
     * Single-line caption text for preview (full cue; no truncation).
     *
     * @param string $text
     * @return string
     */
    function vpc_normalize_caption_display_text($text) {
        $text = str_replace(array("\r", "\n"), ' ', (string) $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
