<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisió de traducció — Studio</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            overflow: hidden;
        }
        body {
            background: #0a0a0a;
            font-family: system-ui, sans-serif;
            color: #e0e0e0;
            display: flex;
            flex-direction: column;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #1e1e1e;
            flex-shrink: 0;
        }
        h1 {
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #888;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        a.nav-link {
            font-size: 0.8rem;
            color: #555;
            text-decoration: none;
            letter-spacing: 0.05em;
        }
        a.nav-link:hover { color: #999; }

        /* ── Toolbar ───────────────────────────────────────────────── */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid #1a1a1a;
            flex-shrink: 0;
            background: #0d0d0d;
        }
        #save-btn {
            padding: 0.55rem 1.25rem;
            background: #e0e0e0;
            color: #0a0a0a;
            border: none;
            border-radius: 4px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
        }
        #save-btn:hover:not(:disabled) { background: #fff; }
        #save-btn:disabled { opacity: 0.45; cursor: not-allowed; }
        #save-error {
            font-size: 0.82rem;
            color: #e05555;
            white-space: pre-line;
            background: #1a1010;
            border: 1px solid #3a2020;
            border-radius: 4px;
            padding: 0.4rem 0.65rem;
        }
        #save-error[hidden] { display: none; }
        #download-vtt-btn, #download-srt-btn {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.55rem 1rem;
            background: transparent;
            color: #555;
            border: 1px solid #2a2a2a;
            border-radius: 4px;
            font-size: 0.82rem;
            cursor: pointer;
        }
        #download-vtt-btn:hover, #download-srt-btn:hover { color: #aaa; border-color: #555; }
        #download-vtt-btn .material-icons, #download-srt-btn .material-icons { font-size: 1rem; }
        .cue-count {
            font-size: 0.78rem;
            color: #444;
            margin-left: auto;
        }

        /* ── Cue list ──────────────────────────────────────────────── */
        .cue-pane {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 0;
        }
        .pair-columns {
            display: grid;
            grid-template-columns: 2.5rem 1fr 1fr;
            gap: 0 1.25rem;
            padding: 0 1.5rem;
            align-items: center;
        }
        .pair-columns-header {
            position: sticky;
            top: 0;
            z-index: 1;
            padding-top: 1rem;
            padding-bottom: 0.75rem;
            background: #0a0a0a;
            border-bottom: 1px solid #1e1e1e;
        }
        .col-header {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #999;
        }
        .col-header-trans {
            border-left: 1px solid #1e1e1e;
            padding-left: 1.25rem;
        }
        #cue-list {
            display: flex;
            flex-direction: column;
        }

        /* ── Pair row ──────────────────────────────────────────────── */
        .pair-card {
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid #181818;
            transition: background 0.15s, border-color 0.15s;
        }
        .pair-card:hover { background: #0f0f0f; }
        .pair-card.cue-overlap { background: #1a1111; border-color: #7c4a4a; }
        .pair-card.cue-error   { background: #1a0d0d; border-color: #e05555; }

        .pair-index {
            font-size: 0.72rem;
            color: #999;
            text-align: right;
            padding-top: 0.15rem;
        }

        /* ── Original block (read-only) ────────────────────────────── */
        .orig-block {
            min-width: 0;
        }
        .orig-ts {
            display: block;
            font-size: 0.72rem;
            font-family: monospace;
            color: #aaa;
            margin-bottom: 0.3rem;
        }
        .orig-text {
            font-size: 0.88rem;
            color: #e0e0e0;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .orig-empty {
            font-size: 0.88rem;
            color: #888;
        }

        /* ── Translated block (editable) ───────────────────────────── */
        .trans-block {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            min-width: 0;
            border-left: 1px solid #1e1e1e;
            padding-left: 1.25rem;
        }
        .ts-row {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .ts-wrap {
            display: flex;
            align-items: center;
        }
        .cue-ts {
            width: 9rem;
            padding: 0.3rem 0.4rem;
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 3px;
            color: #e0e0e0;
            font-size: 0.8rem;
            font-family: monospace;
        }
        .cue-ts:focus { outline: none; border-color: #555; }
        .cue-arrow {
            font-size: 0.75rem;
            color: #888;
        }
        .cue-text {
            width: 100%;
            padding: 0.4rem 0.5rem;
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 3px;
            color: #e0e0e0;
            font-size: 0.9rem;
            resize: vertical;
            font-family: inherit;
        }
        .cue-text:focus { outline: none; border-color: #555; }
    </style>
</head>
<body>
    <header>
        <h1>Revisió — <?= htmlspecialchars($langLabel) ?></h1>
        <div class="header-actions">
            <a class="nav-link" href="?action=translation">← Traduccions</a>
            <a class="nav-link" href="?action=logout">Tanca la sessió</a>
        </div>
    </header>

    <div class="toolbar">
        <button id="save-btn" type="button">Desa i tanca</button>
        <pre id="save-error" hidden></pre>
        <button id="download-vtt-btn" type="button"><span class="material-icons">download</span>Descarrega VTT</button>
        <button id="download-srt-btn" type="button"><span class="material-icons">download</span>Descarrega SRT</button>
        <span class="cue-count"><?= count($translatedCues) ?> subtítols</span>
    </div>

    <div class="cue-pane">
        <div class="pair-columns pair-columns-header">
            <span></span>
            <span class="col-header">Original</span>
            <span class="col-header col-header-trans">Traducció</span>
        </div>
        <div id="cue-list"></div>
    </div>

    <script>
        window.__masterCues     = <?= json_encode($masterCues, JSON_UNESCAPED_UNICODE) ?>;
        window.__translatedCues = <?= json_encode($translatedCues, JSON_UNESCAPED_UNICODE) ?>;
        window.__vimeoId        = <?= json_encode($vimeoId) ?>;
        window.__lang           = <?= json_encode($lang) ?>;
    </script>
    <script src="js/translation-review.js?v=<?= filemtime(__DIR__ . '/../js/translation-review.js') ?>"></script>
</body>
</html>
