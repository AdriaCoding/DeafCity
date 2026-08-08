<!DOCTYPE html>
<html lang="ca">
<head>
<?php
$colorsPath = __DIR__ . '/../css/colors.css';
$colorsVersion = is_file($colorsPath) ? (string) filemtime($colorsPath) : '1';
?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/colors.css?v=<?= htmlspecialchars($colorsVersion, ENT_QUOTES) ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($langLabel) ?> — Subtítols</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            overflow: hidden;
        }
        body {
            background: var(--studio-bg);
            font-family: var(--studio-font);
            color: var(--studio-text);
            display: flex;
            flex-direction: column;
        }
        .toolbar {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid var(--studio-border-subtle);
            flex-shrink: 0;
            background: var(--studio-surface-raised);
        }
        .toolbar-title {
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--studio-text-muted);
            margin-right: 0.5rem;
        }
        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        body.editor-busy #editor-cue-pane {
            opacity: 0.55;
            pointer-events: none;
        }
        body.editor-busy #editor-toolbar button:not(.is-loading) {
            opacity: 0.45;
            pointer-events: none;
        }
        #save-btn, #cancel-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 5.5rem;
        }
        #save-btn.is-loading .btn-label,
        #cancel-btn.is-loading .btn-label {
            visibility: hidden;
        }
        #save-btn.is-loading::after,
        #cancel-btn.is-loading::after {
            content: '';
            position: absolute;
            width: 0.9rem;
            height: 0.9rem;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: editor-spin 0.7s linear infinite;
        }
        #save-btn.is-loading::after { border-color: var(--studio-btn-solid-text); border-top-color: transparent; }
        @keyframes editor-spin { to { transform: rotate(360deg); } }
        #save-btn {
            padding: 0.55rem 1.25rem;
            background: var(--studio-btn-solid-bg);
            color: var(--studio-btn-solid-text);
            border: none;
            border-radius: 4px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
        }
        #save-btn:hover:not(:disabled) { background: var(--studio-btn-solid-bg-hover); }
        #save-btn:disabled { opacity: 0.45; cursor: not-allowed; }
        #save-btn.is-loading { cursor: wait; }
        #cancel-btn {
            padding: 0.55rem 1rem;
            background: transparent;
            color: var(--studio-text-muted);
            border: 1px solid var(--studio-border);
            border-radius: 4px;
            font-size: 0.88rem;
            cursor: pointer;
        }
        #cancel-btn:hover:not(:disabled) { color: var(--studio-text-secondary); border-color: var(--studio-text-muted); }
        #cancel-btn:disabled { opacity: 0.45; cursor: not-allowed; }
        #cancel-btn.is-loading { cursor: wait; }
        #save-error {
            font-size: 0.82rem;
            color: var(--studio-danger);
            white-space: pre-line;
            background: var(--studio-danger-bg);
            border: 1px solid var(--studio-danger-border);
            border-radius: 4px;
            padding: 0.4rem 0.65rem;
        }
        #save-error[hidden] { display: none; }
        #download-srt-btn {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.55rem 1rem;
            background: transparent;
            color: var(--studio-text-muted);
            border: 1px solid var(--studio-border);
            border-radius: 4px;
            font-size: 0.82rem;
            cursor: pointer;
        }
        #download-srt-btn:hover { color: var(--studio-text-secondary); border-color: var(--studio-text-muted); }
        #download-srt-btn .material-icons { font-size: 1rem; }
        .cue-count {
            font-size: 0.78rem;
            color: var(--studio-text-secondary);
            margin-left: auto;
        }
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
            background: var(--studio-bg);
            border-bottom: 1px solid var(--studio-border-subtle);
        }
        .col-header {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--studio-text-muted);
        }
        .col-header-trans {
            border-left: 1px solid var(--studio-border-subtle);
            padding-left: 1.25rem;
        }
        #cue-list {
            display: flex;
            flex-direction: column;
        }
        .pair-card {
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid var(--studio-border-subtle);
            transition: background 0.15s, border-color 0.15s;
        }
        .pair-card:hover { background: var(--studio-hover); }
        .pair-card.cue-overlap { background: var(--studio-danger-bg); border-color: var(--studio-danger-border); }
        .pair-card.cue-error   { background: var(--studio-danger-bg); border-color: var(--studio-danger); }
        .pair-index {
            font-size: 0.72rem;
            color: var(--studio-text-muted);
            text-align: right;
            padding-top: 0.15rem;
        }
        .orig-block { min-width: 0; }
        .orig-ts {
            display: block;
            font-size: 0.72rem;
            font-family: monospace;
            color: var(--studio-text-muted);
            margin-bottom: 0.3rem;
        }
        .orig-text {
            font-size: 0.88rem;
            color: var(--studio-text);
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .orig-empty {
            font-size: 0.88rem;
            color: var(--studio-text-muted);
        }
        .trans-block {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            min-width: 0;
            border-left: 1px solid var(--studio-border-subtle);
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
            background: var(--studio-border-subtle);
            border: 1px solid var(--studio-border);
            border-radius: 3px;
            color: var(--studio-text);
            font-size: 0.8rem;
            font-family: monospace;
        }
        .cue-ts:focus { outline: none; border-color: var(--studio-text-muted); }
        .cue-arrow {
            font-size: 0.75rem;
            color: var(--studio-text-muted);
        }
        .cue-text {
            width: 100%;
            padding: 0.4rem 0.5rem;
            background: var(--studio-border-subtle);
            border: 1px solid var(--studio-border);
            border-radius: 3px;
            color: var(--studio-text);
            font-size: 0.9rem;
            resize: vertical;
            font-family: inherit;
        }
        .cue-text:focus { outline: none; border-color: var(--studio-text-muted); }
    </style>
</head>
<body>
    <div class="toolbar" id="editor-toolbar">
        <span class="toolbar-title"><?= htmlspecialchars($langLabel) ?></span>
        <div class="toolbar-actions">
            <button id="save-btn" type="button"><span class="btn-label">Desa</span></button>
            <button id="cancel-btn" type="button"><span class="btn-label">Cancel·la</span></button>
        </div>
        <pre id="save-error" hidden></pre>
        <button id="download-srt-btn" type="button"><span class="material-icons">download</span>Descarrega SRT</button>
        <span class="cue-count"><?= count($translatedCues) ?> subtítols</span>
    </div>

    <div class="cue-pane" id="editor-cue-pane">
        <div class="pair-columns pair-columns-header">
            <span></span>
            <span class="col-header">Referència</span>
            <span class="col-header col-header-trans">Subtítol</span>
        </div>
        <div id="cue-list"></div>
    </div>

    <script>
        window.__masterCues = <?= json_encode($masterCues, JSON_UNESCAPED_UNICODE) ?>;
        window.__translatedCues = <?= json_encode($translatedCues, JSON_UNESCAPED_UNICODE) ?>;
        window.__vimeoId = <?= json_encode($vimeoId) ?>;
        window.__lang = <?= json_encode($lang) ?>;
        window.__postSaveRedirect = <?= json_encode($postSaveRedirect) ?>;
    </script>
    <script src="js/caption-utils.js?v=<?= filemtime(__DIR__ . '/../js/caption-utils.js') ?>"></script>
    <script src="js/continguts-caption-editor.js?v=<?= filemtime(__DIR__ . '/../js/continguts-caption-editor.js') ?>"></script>
</body>
</html>
