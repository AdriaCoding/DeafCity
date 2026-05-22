<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de subtítols — Studio</title>
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

        /* ── Editor layout ─────────────────────────────────────────── */
        .editor {
            display: flex;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        /* ── Player pane ───────────────────────────────────────────── */
        .player-pane {
            width: 50%;
            flex-shrink: 0;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .player-stack {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        #live-caption {
            min-height: 3.5em;
            padding: 0.65rem 0.75rem;
            box-sizing: border-box;
            color: #7ed87e;
            font-size: clamp(1rem, 2.2vw, 1.45rem);
            line-height: 1.45;
            text-align: center;
            white-space: pre-wrap;
        }
        #live-caption:empty::after {
            content: '\00a0';
        }
        #vimeo-player {
            width: 100%;
            background: #000;
            border-radius: 4px;
            overflow: hidden;
        }
        .save-area {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .save-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        #save-draft-btn, #save-translate-btn {
            padding: 0.65rem 1.4rem;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
        }
        #save-draft-btn {
            background: transparent;
            color: #aaa;
            border: 1px solid #444;
        }
        #save-draft-btn:hover:not(:disabled) { color: #fff; border-color: #666; }
        #save-translate-btn {
            background: #e0e0e0;
            color: #0a0a0a;
        }
        #save-translate-btn:hover:not(:disabled) { background: #fff; }
        #save-draft-btn:disabled, #save-translate-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        #save-btn {
            padding: 0.65rem 1.4rem;
            background: #e0e0e0;
            color: #0a0a0a;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
        }
        #save-btn:hover:not(:disabled) { background: #fff; }
        #save-btn:disabled { opacity: 0.45; cursor: not-allowed; }
        #skip-btn {
            padding: 0.65rem 1rem;
            background: transparent;
            color: #666;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 0.82rem;
            cursor: pointer;
        }
        #skip-btn:hover { color: #aaa; border-color: #555; }
        #save-error {
            font-size: 0.82rem;
            color: #e05555;
            white-space: pre-line;
            background: #1a1010;
            border: 1px solid #3a2020;
            border-radius: 4px;
            padding: 0.6rem 0.75rem;
        }
        #save-error[hidden] { display: none; }

        /* ── Cue list pane ─────────────────────────────────────────── */
        .cue-pane {
            flex: 1;
            min-height: 0;
            min-width: 0;
            overflow-y: auto;
            padding: 1.5rem;
            border-left: 1px solid #1e1e1e;
        }
        .cue-pane-header {
            font-size: 0.75rem;
            color: #555;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        #cue-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        /* ── Cue row ───────────────────────────────────────────────── */
        .cue-row {
            background: #141414;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            display: grid;
            grid-template-columns: 1.5rem auto 1fr auto 1fr auto auto;
            gap: 0.5rem;
            align-items: center;
            transition: border-color 0.15s;
        }
        .cue-row.cue-active {
            border-color: #4a7c59;
            background: #111a14;
        }
        .cue-row.cue-overlap {
            border-color: #7c4a4a;
            background: #1a1111;
        }
        .cue-index {
            font-size: 0.75rem;
            color: #555;
            text-align: right;
        }
        .cue-seek {
            background: transparent;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 0.75rem;
            padding: 0.2rem;
        }
        .cue-seek:hover { color: #aaa; }
        .ts-wrap {
            display: flex;
            align-items: center;
            gap: 0.25rem;
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
        .cue-settime {
            background: transparent;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 0.75rem;
            padding: 0.2rem;
            line-height: 1;
        }
        .cue-settime:hover { color: #aaa; }
        .cue-arrow {
            font-size: 0.75rem;
            color: #444;
        }
        .cue-text {
            grid-column: 1 / -1;
            margin-top: 0.4rem;
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
        .cue-delete {
            background: transparent;
            border: none;
            color: #553;
            cursor: pointer;
            font-size: 0.8rem;
            padding: 0.2rem 0.4rem;
        }
        .cue-delete:hover { color: #e05555; }
        .cue-add {
            background: transparent;
            border: 1px solid #2a2a2a;
            color: #555;
            cursor: pointer;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
        }
        .cue-add:hover { color: #aaa; border-color: #555; }

        /* cue-text spans full row */
        .cue-row { flex-wrap: wrap; }
    </style>
</head>
<body>
    <header>
        <h1><?php if (($editorMode ?? 'master') === 'translation'): ?>Editor de subtítols — <?= htmlspecialchars($langLabel ?? '') ?><?php else: ?>Editor de subtítols<?php endif; ?></h1>
        <div class="header-actions">
            <a class="nav-link" href="./">← Estudi</a>
            <a class="nav-link" href="?action=logout">Tanca la sessió</a>
        </div>
    </header>

    <div class="editor">
        <div class="player-pane">
            <div class="player-stack">
                <div id="live-caption" aria-live="polite"></div>
                <div id="vimeo-player"></div>
            </div>
            <div class="save-area">
                <div class="save-row">
                    <?php if (($editorMode ?? 'master') === 'translation'): ?>
                        <button id="save-btn" type="button">Desa</button>
                    <?php else: ?>
                        <button id="save-draft-btn" type="button">Desa esborrany</button>
                        <button id="save-translate-btn" type="button">Desa i tradueix</button>
                        <button id="skip-btn" type="button">Omet i ves a l'etiquetatge</button>
                    <?php endif; ?>
                </div>
                <pre id="save-error" hidden></pre>
            </div>
        </div>

        <div class="cue-pane">
            <p class="cue-pane-header">Subtítols — <?= count($cues) ?></p>
            <div id="cue-list"></div>
        </div>
    </div>

    <script>
        window.__cues       = <?= json_encode($cues, JSON_UNESCAPED_UNICODE) ?>;
        window.__vimeoId    = <?= json_encode($vimeoId) ?>;
        window.__editorMode = <?= json_encode($editorMode ?? 'master') ?>;
    </script>
    <script src="https://player.vimeo.com/api/player.js"></script>
    <script src="js/subtitle-editor.js?v=<?= filemtime(__DIR__ . '/../js/subtitle-editor.js') ?>"></script>
</body>
</html>
