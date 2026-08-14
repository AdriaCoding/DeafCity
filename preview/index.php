<?php
require __DIR__ . '/lib/videos_catalog.php';
require __DIR__ . '/lib/preview_locale.php';

$locale = preview_bootstrap_locale();
$preview_i18n = $locale['i18n'];
$preview_lang = $locale['lang'];
$preview_dir = $locale['dir'];

// Resolve data paths: use the worktree-local data dir when available,
// otherwise fall back to the canonical public_html data dir (for worktree environments).
$_dataDir = preview_resolve_data_dir();
$catalogJsonPath  = $_dataDir . '/catalog.json';
$studioConfigPath = $_dataDir . '/studio-config.json';
$catalog  = vpc_load_videos_catalog($catalogJsonPath);
// D2 + D12: shuffle server-side so item[0] is both the paused poster and the queue head.
// Every reload yields a fresh random order; no sign-language pre-filter (D7).
$collection = vpc_catalog_collection($catalog, $studioConfigPath);
$playlist = $collection['playlist'];
$catalogPlaylist = $collection['catalog_playlist'];
$signLanguageOptions  = preview_localize_filter_options($collection['sign_language_options'], 'sign_language');
$editionOptions       = preview_localize_filter_options($collection['edition_options'], 'edition');
$typologyOptions      = preview_localize_filter_options($collection['typology_options'], 'typology');
$subtitleLanguages    = $collection['subtitle_languages'];
$vpc = null;
if (count($playlist) > 0) {
    $vpc = [
        'instance_id'    => 'preview-playlist-demo',
        'playlist'       => $playlist,
        'catalog_playlist' => $catalogPlaylist,
        // Tell JS the server already placed the chosen poster at index 0.
        'playlist_index' => 0,
    ];
    // R2 sign language picker: populated-from-present only (D17), no default (D7).
    if (count($signLanguageOptions) > 0) {
        $vpc['sign_language_filter'] = ['options' => $signLanguageOptions];
    }
    // R2 Spoken Language track selector (D16′): always present; JS fills per-video options.
    if (count($subtitleLanguages) > 0) {
        $vpc['subtitle_languages'] = $subtitleLanguages;
    }
    // R2 City/Edition picker: populated-from-present only (D17).
    if (count($editionOptions) > 0) {
        $vpc['edition_filter'] = ['options' => $editionOptions];
    }
    // R2 Typology picker: populated-from-present only (D17).
    if (count($typologyOptions) > 0) {
        $vpc['typology_filter'] = ['options' => $typologyOptions];
    }
}

// D18: Participant page-pick — RESET: load participant playlist, clear R2 filters.
$participantName = isset($_GET['participant']) ? trim((string)$_GET['participant']) : '';
if ($participantName !== '' && $vpc !== null && $catalog !== null) {
    $participantPlaylist = vpc_participant_playlist_from_catalog($catalog, $participantName);
    // Always enter Participant mode for the query name (even when unknown / empty).
    $vpc['participant_name'] = $participantName;
    if (count($participantPlaylist) > 0) {
        $vpc['playlist'] = $participantPlaylist;
        $vpc['catalog_playlist'] = $catalogPlaylist;
        $vpc['playlist_index'] = 0;
        // Participant playlists are sequence-sorted server-side (natural order, no shuffle)
    }
    // Empty/unknown: keep catalog master + a technical SSR playlist; JS applies empty filter.
}

$deafHearingEnabled = $collection['deaf_hearing_enabled'];
if ($vpc !== null) {
    $vpc['deaf_hearing_enabled'] = $deafHearingEnabled;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($preview_lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($preview_dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DEAF.city</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/preview/components/vimeo_caption_player.css?v=69">
    <link rel="stylesheet" href="/preview/css/bottom-bar.css?v=7">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            overflow: hidden;
            background: #fff;
            font-family: Roboto, Arial, sans-serif;
        }
        .preview-block { height: 100%; }
    </style>
</head>
<body>

<div class="preview-block">
<?php if ($vpc !== null): ?>
    <?php require __DIR__ . '/components/vimeo_caption_player.php'; ?>
<?php else: ?>
    <p style="font-family:sans-serif;padding:1rem;color:#666;">
        <?= htmlspecialchars(preview_t('player.error.no_playlist'), ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>
</div>

<script src="/preview/js/vimeo_playlist_logic.js?v=22"></script>
<script src="/preview/js/vimeo_caption_player.js?v=64" defer></script>
</body>
</html>
