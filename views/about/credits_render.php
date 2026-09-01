<?php
/**
 * Substitutes the {{token}} vocabulary of credits_body.html.
 *
 * Kept apart from credits_i18n.php so the substitution can be unit-tested
 * without bootstrapping a locale or rendering the About page.
 *
 * Only tokens in the fixed vocabulary below are replaced. Anything else the
 * body happens to contain is emitted verbatim, so a typo shows up as a visible
 * {{typo}} on the page rather than silently vanishing.
 */

if (!function_exists('credits_render_counts')) {
    /**
     * @return array{participants: int, videos: int, deaf_hearing_pct: int}
     */
    function credits_render_counts($catalogPath)
    {
        $catalog = vpc_load_videos_catalog($catalogPath);
        $videos = 0;
        $participants = 0;
        if ($catalog) {
            foreach ($catalog['videos'] as $video) {
                if (is_array($video) && vpc_catalog_entry_is_visible($video)) {
                    $videos++;
                }
            }
            $participants = count(vpc_participants_from_catalog($catalog));
        }
        $deafHearing = $catalog ? vpc_catalog_deaf_hearing_tag_count($catalog) : 0;

        return array(
            'participants' => $participants,
            'videos' => $videos,
            'deaf_hearing_pct' => $videos > 0 ? (int) round($deafHearing / $videos * 100) : 0,
        );
    }
}

if (!function_exists('credits_render_substitutions')) {
    /**
     * The complete token vocabulary. The Studio editor validates edited bodies
     * against these same keys, so a body can never reference a token this
     * renderer does not know how to fill.
     *
     * @param array{participants: int, videos: int, deaf_hearing_pct: int} $counts
     * @return array<string, string>
     */
    function credits_render_substitutions(array $counts)
    {
        // Rubric labels render bold; the body supplies no markup of its own for
        // them, so a translation can never inject unbalanced tags.
        $labels = array(
            'label.supported_by'  => 'about.credits.label.supported_by',
            'label.participants'  => 'about.credits.label.participants',
            'label.interpreter'   => 'about.credits.label.interpreter',
            'label.interpreters'  => 'about.credits.label.interpreters',
            'label.coordination'  => 'about.credits.label.coordination',
            'label.collaboration' => 'about.credits.label.collaboration',
            'label.thanks_to'     => 'about.credits.label.thanks_to',
        );

        $out = array();
        foreach ($labels as $token => $key) {
            $out[$token] = '<b>' . htmlspecialchars(preview_t($key), ENT_QUOTES, 'UTF-8') . '</b>';
        }
        $out['project_by'] = htmlspecialchars(preview_t('about.credits.project_by'), ENT_QUOTES, 'UTF-8');
        $out['contact'] = htmlspecialchars(preview_t('about.credits.contact'), ENT_QUOTES, 'UTF-8');

        $out['count.participants'] = (string) (int) $counts['participants'];
        $out['count.videos'] = (string) (int) $counts['videos'];
        $out['count.deaf_hearing_pct'] = (string) (int) $counts['deaf_hearing_pct'];

        return $out;
    }
}

if (!function_exists('credits_render_known_tokens')) {
    /**
     * The token vocabulary as literal names, with no locale or catalog needed.
     * The Studio credits editor validates against this from a context where
     * preview_t() and the catalog helpers are not loaded, so this list must
     * stay callable on its own.
     *
     * @return string[]
     */
    function credits_render_known_tokens()
    {
        return array(
            'label.supported_by',
            'label.participants',
            'label.interpreter',
            'label.interpreters',
            'label.coordination',
            'label.collaboration',
            'label.thanks_to',
            'project_by',
            'contact',
            'count.participants',
            'count.videos',
            'count.deaf_hearing_pct',
        );
    }
}

if (!function_exists('credits_render_apply')) {
    /**
     * @param array<string, string> $substitutions
     * @return string
     */
    function credits_render_apply($body, array $substitutions)
    {
        $search = array();
        $replace = array();
        foreach ($substitutions as $token => $value) {
            $search[] = '{{' . $token . '}}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $body);
    }
}

if (!function_exists('credits_render_body')) {
    /** @return string */
    function credits_render_body($bodyPath, $catalogPath)
    {
        if (!is_readable($bodyPath)) {
            trigger_error('credits: body file not readable', E_USER_WARNING);
            return '';
        }
        $body = file_get_contents($bodyPath);
        if ($body === false) {
            trigger_error('credits: body file unreadable', E_USER_WARNING);
            return '';
        }

        return credits_render_apply($body, credits_render_substitutions(credits_render_counts($catalogPath)));
    }
}

if (!function_exists('credits_render_assert_vocabulary_in_sync')) {
    /**
     * Guard for the test suite: the literal list above and the substitution
     * map must describe the same vocabulary, or the editor would accept a
     * token the renderer cannot fill (or reject one it can).
     *
     * @return bool
     */
    function credits_render_assert_vocabulary_in_sync()
    {
        $fromMap = array_keys(credits_render_substitutions(array(
            'participants' => 0,
            'videos' => 0,
            'deaf_hearing_pct' => 0,
        )));
        $literal = credits_render_known_tokens();
        sort($fromMap);
        sort($literal);

        return $fromMap === $literal;
    }
}
