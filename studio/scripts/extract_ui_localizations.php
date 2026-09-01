<?php

// CLI only. Refuse to run under a web server even if the directory deny rule
// is ever lost: these scripts spend API budget and mutate the catalog.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}
/**
 * Generate data/ui-localizations.json from the English string manifest + studio-config content labels.
 *
 * Run: php studio/scripts/extract_ui_localizations.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Studio\ContentLocalizationSync;

$dataDir = dirname(__DIR__, 2) . '/data';
$configPath = $dataDir . '/studio-config.json';
$outPath = $dataDir . '/ui-localizations.json';

$manifest = [
    // Player chrome
    ['key' => 'player.nav.player', 'section' => 'player', 'context' => 'Site nav link to home player', 'en' => 'Player'],
    ['key' => 'player.nav.about', 'section' => 'player', 'context' => 'Site nav link to About page — Toni design decision: visible label is always "?" in every locale (see ADR-0012)', 'en' => '?'],
    ['key' => 'player.nav.participants', 'section' => 'player', 'context' => 'Site nav link to Participants page', 'en' => 'Participants'],
    ['key' => 'player.nav.language', 'section' => 'player', 'context' => 'Language switcher aria-label on About/Participants navbar', 'en' => 'Language'],
    ['key' => 'player.filter.all_sign_languages', 'section' => 'player', 'context' => 'Default option in Sign language dropdown', 'en' => 'All sign languages'],
    ['key' => 'player.filter.all_cities', 'section' => 'player', 'context' => 'Default option in City/Edition dropdown', 'en' => 'All cities'],
    ['key' => 'player.filter.all_typologies', 'section' => 'player', 'context' => 'Default option in Typology dropdown', 'en' => 'All typologies'],
    ['key' => 'player.filter.sign_language', 'section' => 'player', 'context' => 'Sign language filter generic label', 'en' => 'Sign language'],
    ['key' => 'player.filter.city_edition', 'section' => 'player', 'context' => 'City/Edition filter generic label', 'en' => 'City / Edition'],
    ['key' => 'player.filter.typology', 'section' => 'player', 'context' => 'Typology filter generic label', 'en' => 'Typology'],
    ['key' => 'player.filter.deaf_hearing', 'section' => 'player', 'context' => 'Accessible name for DEAF+HEARING toggle (not the glyph label)', 'en' => 'Deaf and hearing crossover videos'],
    ['key' => 'player.spoken_language.label', 'section' => 'player', 'context' => 'Spoken Language picker label', 'en' => 'Spoken Language'],
    ['key' => 'player.spoken_language.no_subtitles', 'section' => 'player', 'context' => 'Spoken Language disabled state', 'en' => 'No subtitles'],
    ['key' => 'player.transport.play', 'section' => 'player', 'context' => 'Play button aria-label', 'en' => 'Play video'],
    ['key' => 'player.transport.pause', 'section' => 'player', 'context' => 'Pause button aria-label', 'en' => 'Pause video'],
    ['key' => 'player.transport.play_or_pause', 'section' => 'player', 'context' => 'Video hitarea aria-label', 'en' => 'Play or pause video'],
    ['key' => 'player.transport.shuffle', 'section' => 'player', 'context' => 'Shuffle button aria-label', 'en' => 'Shuffle playlist'],
    ['key' => 'player.transport.prev', 'section' => 'player', 'context' => 'Previous button aria-label', 'en' => 'Previous video in playlist'],
    ['key' => 'player.transport.next', 'section' => 'player', 'context' => 'Next button aria-label', 'en' => 'Next video in playlist'],
    ['key' => 'player.transport.reset', 'section' => 'player', 'context' => 'Reset button aria-label', 'en' => 'Reset filters and playlist'],
    ['key' => 'player.transport.reset_short', 'section' => 'player', 'context' => 'Reset button visible short label', 'en' => 'Reset'],
    ['key' => 'player.transport.space_key', 'section' => 'player', 'context' => 'Name of the spacebar key, used in the Play/Pause keyboard-shortcut hint appended to its title/aria-label, e.g. "Play video (Space)"', 'en' => 'Space'],
    ['key' => 'player.aria.filters', 'section' => 'player', 'context' => 'Filters group aria-label', 'en' => 'Filters'],
    ['key' => 'player.aria.playback', 'section' => 'player', 'context' => 'Playback group aria-label', 'en' => 'Playback'],
    ['key' => 'player.error.no_playlist', 'section' => 'player', 'context' => 'Empty catalog message', 'en' => 'No playlist loaded. Check that data/catalog.json exists.'],

    // About page
    ['key' => 'about.page_title', 'section' => 'about', 'context' => 'About page <title>', 'en' => 'About — DEAF.city'],
    ['key' => 'about.block.deaf_city.title', 'section' => 'about', 'context' => 'About section heading', 'en' => 'DEAF.city'],
    ['key' => 'about.block.deaf_city.p1', 'section' => 'about', 'context' => 'About intro paragraph', 'en' => 'The Deaf community lives in a world that often overlooks its natural way of communicating: sign language. While many Deaf people rely on lip-reading, most hearing individuals don\'t understand sign languages, resulting in isolation and invisibility. For many, the rare appearance of a sign-language interpreter on television is the only acknowledgment of Deaf existence.'],
    ['key' => 'about.block.breaking_silence.title', 'section' => 'about', 'context' => 'About section heading', 'en' => 'Breaking Silence'],
    ['key' => 'about.block.breaking_silence.p1', 'section' => 'about', 'context' => 'About paragraph', 'en' => 'DEAF.city uses visual-gestural humor to challenge hearing indifference, to celebrate Deaf culture and connect communities. Participants share stories and jokes, turning everyday misunderstandings into moments of laughter and reflection. Humor becomes a bridge between Deaf and hearing audiences, empowering storytellers to reclaim visibility and promote inclusion.'],
    ['key' => 'about.block.dissemination.title', 'section' => 'about', 'context' => 'About section heading', 'en' => 'Dissemination'],
    ['key' => 'about.block.dissemination.p1', 'section' => 'about', 'context' => 'About paragraph', 'en' => 'The project spreads across an open-access video repository with multi-screen installations in museums, art centers and public spaces, using TVs, LED panels, or projections. The videos—paired with participant-generated sounds such as vocalizations, claps, and finger snaps—create a rich, engaging soundscape.'],
    ['key' => 'about.block.timeline.title', 'section' => 'about', 'context' => 'About section heading', 'en' => 'Timeline'],
    ['key' => 'about.block.timeline.p1', 'section' => 'about', 'context' => 'About paragraph', 'en' => 'Since its launch in Valencia in 2020, DEAF.city has expanded to Mexico City in 2021, and in 2023 to Bilbao and São Paulo. Twenty-six participants have shared humorous monologues in Spanish, Mexican, and Brazilian Sign Languages. In 2026, the project will grow to Marseille, Rome, Athens, Istanbul, Tunis, Algiers, and Barcelona, incorporating French, Italian, Greek, Turkish, Tunisian, Algerian, and Catalan sign languages.'],
    ['key' => 'about.block.silent_eloquence.title', 'section' => 'about', 'context' => 'About section heading', 'en' => 'Silent Eloquence'],
    ['key' => 'about.block.silent_eloquence.p1', 'section' => 'about', 'context' => 'About paragraph', 'en' => 'By blending humor, art, and activism, DEAF.city makes Deaf culture visible, fosters understanding, and builds bridges between Deaf and hearing communities worldwide.'],

    // Credits — only the reusable rubric labels are translatable. Cities/editions
    // live in content.edition.*, sign languages in content.sign_language.*, and
    // people/institution names + links are not translated (see views/about/credits_i18n.php).
    ['key' => 'about.credits.label.supported_by', 'section' => 'about', 'context' => 'Credits rubric label (keep leading underscore)', 'en' => '_SUPPORTED BY'],
    ['key' => 'about.credits.label.participants', 'section' => 'about', 'context' => 'Credits rubric label (keep leading underscore)', 'en' => '_PARTICIPANTS'],
    ['key' => 'about.credits.label.interpreter', 'section' => 'about', 'context' => 'Credits rubric label (keep leading underscore)', 'en' => '_INTERPRETER'],
    ['key' => 'about.credits.label.interpreters', 'section' => 'about', 'context' => 'Credits rubric label, plural (keep leading underscore)', 'en' => '_INTERPRETERS'],
    ['key' => 'about.credits.label.coordination', 'section' => 'about', 'context' => 'Credits rubric label (keep leading underscore)', 'en' => '_COORDINATION'],
    ['key' => 'about.credits.label.collaboration', 'section' => 'about', 'context' => 'Credits rubric label (keep leading underscore)', 'en' => '_COLLABORATION'],
    ['key' => 'about.credits.label.thanks_to', 'section' => 'about', 'context' => 'Credits rubric label (keep leading underscore)', 'en' => '_THANKS TO'],
    ['key' => 'about.credits.project_by', 'section' => 'about', 'context' => 'Credits: project attribution line', 'en' => 'DEAF.city is a project by'],
    ['key' => 'about.credits.contact', 'section' => 'about', 'context' => 'Credits: contact link text', 'en' => 'Contact'],

    // Participants page
    ['key' => 'participants.page_title', 'section' => 'participants', 'context' => 'Participants page <title>', 'en' => 'Participants — DEAF.city'],
    ['key' => 'participants.heading', 'section' => 'participants', 'context' => 'Participants page h1', 'en' => 'Participants'],

    // About gallery captions (issue #06)
    ['key' => 'gallery.caption.01', 'section' => 'about', 'context' => 'Gallery image 1 caption', 'en' => 'Double-sided display of 10 Sign Languages on TVs'],
    ['key' => 'gallery.caption.02', 'section' => 'about', 'context' => 'Gallery image 2 caption', 'en' => 'Double-sided display of 10 Sign Languages on TVs'],
    ['key' => 'gallery.caption.03', 'section' => 'about', 'context' => 'Gallery image 3 caption', 'en' => 'Double-sided display of 10 Sign Languages on TVs'],
    ['key' => 'gallery.caption.04', 'section' => 'about', 'context' => 'Gallery image 4 caption', 'en' => 'TVs on the wall displaying 8 Sign Languages'],
    ['key' => 'gallery.caption.05', 'section' => 'about', 'context' => 'Gallery image 5 caption', 'en' => 'Projections displaying 4 Sign Languages'],
    ['key' => 'gallery.caption.06', 'section' => 'about', 'context' => 'Gallery image 6 caption', 'en' => 'TVs on the wall displaying 15 Sign Languages'],
];

$store = [];
foreach ($manifest as $row) {
    $store[$row['key']] = [
        'section' => $row['section'],
        'context' => $row['context'],
        'translations' => ['en' => $row['en']],
    ];
}

$cfgRaw = file_get_contents($configPath);
$cfg = $cfgRaw !== false ? json_decode($cfgRaw, true) : null;
$existingStore = is_readable($outPath) ? json_decode((string) file_get_contents($outPath), true) : null;
if (!is_array($existingStore)) {
    $existingStore = [];
}

if (is_array($cfg)) {
    foreach ($cfg['sign_languages'] ?? [] as $item) {
        $id = $item['id'] ?? '';
        if ($id === '') {
            continue;
        }
        if (!empty($item['label'])) {
            $store[ContentLocalizationSync::signLanguageLabelKey($id)] = [
                'section' => 'content',
                'context' => ContentLocalizationSync::signLanguageLabelContext($id),
                'translations' => ['en' => $item['label']],
            ];
        }
        if (!empty($item['short_label'])) {
            $store[ContentLocalizationSync::signLanguageShortLabelKey($id)] = [
                'section' => 'content',
                'context' => ContentLocalizationSync::signLanguageShortLabelContext($id),
                'translations' => ['en' => $item['short_label']],
            ];
        }
    }

    // Editions: one city-name key; full label composes year from edition id at render time.
    foreach ($cfg['editions'] ?? [] as $item) {
        $id = $item['id'] ?? '';
        if ($id === '') {
            continue;
        }
        $city = !empty($item['short_label']) ? $item['short_label'] : ($item['label'] ?? '');
        if ($city === '') {
            continue;
        }
        $key = ContentLocalizationSync::editionKey($id);
        $store[$key] = [
            'section' => 'content',
            'context' => ContentLocalizationSync::editionContext($id),
            'translations' => ['en' => $city],
        ];

        $oldShort = $existingStore["content.edition.$id.short_label"] ?? null;
        $oldLabel = $existingStore["content.edition.$id.label"] ?? null;

        if (is_array($oldLabel)) {
            foreach ($oldLabel['translations'] ?? [] as $lang => $text) {
                if ($lang !== 'en' && trim((string) $text) !== '') {
                    $store[$key]['translations'][$lang] = $text;
                }
            }
            if (is_array($oldLabel['seeded'] ?? null)) {
                foreach ($oldLabel['seeded'] as $lang => $flag) {
                    if ($flag && $lang !== 'en') {
                        $store[$key]['seeded'][$lang] = true;
                    }
                }
            }
        }
        if (is_array($oldShort)) {
            foreach ($oldShort['translations'] ?? [] as $lang => $text) {
                if ($lang !== 'en' && trim((string) $text) !== '') {
                    $store[$key]['translations'][$lang] = $text;
                }
            }
            if (is_array($oldShort['seeded'] ?? null)) {
                foreach ($oldShort['seeded'] as $lang => $flag) {
                    if ($flag && $lang !== 'en') {
                        $store[$key]['seeded'][$lang] = true;
                    }
                }
            }
        }
    }

    // Typologies: one canonical key (short_label form); label display is ALL CAPS at render time.
    // English defaults are proper translations — studio-config short_label is Catalan.
    $typologyEnDefaults = [
        'acudits' => 'Jokes',
        'anecdotes' => 'Anecdotes',
        'malentesos' => 'Misunderstandings',
        'endevinalles' => 'Riddles',
        'memories' => 'Memories',
        'pensaments' => 'Thoughts',
    ];
    foreach ($cfg['typologies'] ?? [] as $item) {
        $id = $item['id'] ?? '';
        if ($id === '') {
            continue;
        }
        $fallback = !empty($item['short_label']) ? $item['short_label'] : ($item['label'] ?? '');
        if ($fallback === '') {
            continue;
        }
        $enLabel = $typologyEnDefaults[$id] ?? $fallback;
        $key = ContentLocalizationSync::typologyKey($id);
        $store[$key] = [
            'section' => 'content',
            'context' => ContentLocalizationSync::typologyContext($id),
            'translations' => ['en' => $enLabel],
        ];

        $oldShort = $existingStore["content.typology.$id.short_label"] ?? null;
        $oldLabel = $existingStore["content.typology.$id.label"] ?? null;

        if (is_array($oldLabel)) {
            foreach ($oldLabel['translations'] ?? [] as $lang => $text) {
                if ($lang !== 'en' && trim((string) $text) !== '') {
                    $store[$key]['translations'][$lang] = $text;
                }
            }
            if (is_array($oldLabel['seeded'] ?? null)) {
                foreach ($oldLabel['seeded'] as $lang => $flag) {
                    if ($flag && $lang !== 'en') {
                        $store[$key]['seeded'][$lang] = true;
                    }
                }
            }
        }
        if (is_array($oldShort)) {
            foreach ($oldShort['translations'] ?? [] as $lang => $text) {
                if ($lang !== 'en' && trim((string) $text) !== '') {
                    $store[$key]['translations'][$lang] = $text;
                }
            }
            if (is_array($oldShort['seeded'] ?? null)) {
                foreach ($oldShort['seeded'] as $lang => $flag) {
                    if ($flag && $lang !== 'en') {
                        $store[$key]['seeded'][$lang] = true;
                    }
                }
            }
        }
    }
}

// Preserve existing non-en translations + seeded flags for any key that still
// exists, so re-running this script never wipes human/seeded translations.
// (Edition/typology key renames are handled above; this covers same-key chrome.)
foreach ($store as $key => $entry) {
    $old = $existingStore[$key] ?? null;
    if (!is_array($old)) {
        continue;
    }
    foreach ($old['translations'] ?? [] as $lang => $text) {
        if ($lang !== 'en' && trim((string) $text) !== '' && !isset($store[$key]['translations'][$lang])) {
            $store[$key]['translations'][$lang] = $text;
        }
    }
    foreach ($old['seeded'] ?? [] as $lang => $flag) {
        if ($flag && $lang !== 'en') {
            $store[$key]['seeded'][$lang] = true;
        }
    }
}

ksort($store);
file_put_contents($outPath, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    @chown($outPath, 'www-data');
    @chgrp($outPath, 'www-data');
}
echo "Wrote " . count($store) . " keys to $outPath\n";
