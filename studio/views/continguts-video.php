<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($video['title'] ?? 'Vídeo', ENT_QUOTES) ?> — Studio</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #0a0a0a;
            font-family: system-ui, sans-serif;
            color: #e0e0e0;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 2rem;
            border-bottom: 1px solid #1e1e1e;
        }
        h1 {
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #888;
        }
        .header-nav { display: flex; gap: 1.25rem; align-items: center; }
        a.nav-link {
            font-size: 0.8rem;
            color: #555;
            text-decoration: none;
            letter-spacing: 0.05em;
        }
        a.nav-link:hover { color: #999; }
        main { max-width: 42rem; padding: 2rem 2rem 4rem; }

        .video-hero {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #1e1e1e;
        }
        .video-thumb {
            width: 200px;
            height: 112px;
            object-fit: cover;
            border-radius: 4px;
            background: #222;
            flex-shrink: 0;
        }
        .video-thumb-placeholder {
            width: 200px;
            height: 112px;
            border-radius: 4px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            flex-shrink: 0;
        }
        .video-hero h2 {
            font-size: 1.15rem;
            font-weight: 500;
            color: #e0e0e0;
            line-height: 1.35;
        }

        .field { margin-bottom: 1rem; }
        label.field-label {
            display: block;
            font-size: 0.78rem;
            color: #777;
            margin-bottom: 0.3rem;
        }
        input.title-input {
            display: block;
            width: 100%;
            padding: 0.55rem 0.75rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 0.9rem;
            outline: none;
        }
        input.title-input:focus { border-color: #555; }
        select.typology-select {
            display: block;
            width: 100%;
            padding: 0.55rem 0.75rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 0.9rem;
            outline: none;
        }
        select.typology-select:focus { border-color: #555; }

        .chip-input-box {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            padding: 0.5rem 0.6rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            cursor: text;
            min-height: 2.5rem;
            align-items: center;
        }
        .chip-input-box:focus-within { border-color: #555; }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: #2a3a5a;
            color: #9ab8ff;
            border-radius: 3px;
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
        }
        .chip-remove {
            background: none;
            border: none;
            color: #6080c0;
            cursor: pointer;
            padding: 0;
            font-size: 0.85rem;
            line-height: 1;
        }
        .chip-remove:hover { color: #e0e0e0; }
        .chip-text-input {
            flex: 1;
            min-width: 100px;
            background: none;
            border: none;
            color: #e0e0e0;
            font-size: 0.875rem;
            outline: none;
        }
        .tag-suggestions {
            display: none;
            position: absolute;
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 4px;
            margin-top: 2px;
            z-index: 10;
            max-height: 10rem;
            overflow-y: auto;
            min-width: 160px;
        }
        .tag-suggestion-item {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            cursor: pointer;
            color: #ccc;
        }
        .tag-suggestion-item:hover, .tag-suggestion-item.focused { background: #2a2a2a; }
        .chip-wrapper { position: relative; width: 100%; }

        .btn-save {
            padding: 0.55rem 1.1rem;
            background: #1a3a6e;
            border: 1px solid #2a5090;
            border-radius: 4px;
            color: #9ab8ff;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-save:hover { background: #1f4580; }
        .btn-save:disabled { opacity: 0.5; cursor: default; }
        .save-feedback {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }
        .save-feedback.ok { color: #4a8a4a; }
        .save-feedback.warn { color: #b58a4a; }
        .save-feedback.err { color: #a55; }

        .caption-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0.75rem;
            font-size: 0.82rem;
        }
        .caption-table th {
            text-align: left;
            font-weight: 500;
            color: #666;
            padding: 0.35rem 0.5rem 0.35rem 0;
            border-bottom: 1px solid #2a2a2a;
        }
        .caption-table td {
            padding: 0.4rem 0.5rem 0.4rem 0;
            color: #888;
            vertical-align: middle;
        }
.caption-table tbody tr + tr td {
            border-top: 1px solid #1e1e1e;
        }
        .caption-master-radio {
            appearance: none;
            -webkit-appearance: none;
            width: 1rem;
            height: 1rem;
            border: 1.5px solid #444;
            border-radius: 50%;
            cursor: pointer;
            position: relative;
            flex-shrink: 0;
            background: #1a1a1a;
            transition: border-color 0.15s;
        }
        .caption-master-radio:checked {
            border-color: #4a8a4a;
            background: #4a8a4a;
        }
        .caption-master-radio:checked::after {
            content: '';
            position: absolute;
            inset: 3px;
            border-radius: 50%;
            background: #0a0a0a;
        }
        .caption-master-radio:hover:not(:checked) { border-color: #666; }
        td.caption-master-cell { width: 2rem; text-align: center; }
        .caption-master-feedback {
            font-size: 0.75rem;
            margin-top: 0.3rem;
            min-height: 1em;
        }
        .caption-master-feedback.ok { color: #4a8a4a; }
        .caption-master-feedback.err { color: #a55; }
        .caption-download-btns { display: flex; gap: 0.35rem; }
        a.caption-download-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.5rem;
            background: transparent;
            color: #555;
            border: 1px solid #2a2a2a;
            border-radius: 4px;
            font-size: 0.72rem;
            text-decoration: none;
            letter-spacing: 0.04em;
        }
        a.caption-download-btn:hover { color: #aaa; border-color: #555; }
        a.caption-download-btn .material-icons { font-size: 0.95rem; }

        .caption-action-btns { display: flex; flex-wrap: wrap; gap: 0.35rem; }
        button.caption-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.5rem;
            background: transparent;
            color: #555;
            border: 1px solid #2a2a2a;
            border-radius: 4px;
            font-size: 0.72rem;
            cursor: pointer;
            letter-spacing: 0.04em;
        }
        button.caption-action-btn:hover { color: #aaa; border-color: #555; }
        button.caption-action-btn .material-icons { font-size: 0.95rem; }
        button.caption-action-btn.danger:hover { color: #e05555; border-color: #7c4a4a; }
        button.caption-action-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        button.caption-action-btn.is-loading {
            position: relative;
            opacity: 0.75;
            cursor: wait;
        }
        button.caption-action-btn.is-loading .material-icons,
        button.caption-action-btn.is-loading .caption-action-label {
            visibility: hidden;
        }
        button.caption-action-btn.is-loading::after {
            content: '';
            position: absolute;
            inset: 0;
            margin: auto;
            width: 0.85rem;
            height: 0.85rem;
            border: 2px solid #444;
            border-top-color: #aaa;
            border-radius: 50%;
            animation: caption-spin 0.7s linear infinite;
        }
        .caption-table-feedback {
            font-size: 0.75rem;
            margin-bottom: 0.4rem;
            min-height: 1em;
        }
        .caption-table-feedback.ok { color: #4a8a4a; }
        .caption-table-feedback.err { color: #a55; }
        .caption-row-spinner {
            display: inline-block;
            width: 0.85rem;
            height: 0.85rem;
            border: 2px solid #333;
            border-top-color: #888;
            border-radius: 50%;
            animation: caption-spin 0.7s linear infinite;
            vertical-align: middle;
        }
        @keyframes caption-spin { to { transform: rotate(360deg); } }

        #caption-edit-dialog {
            width: 100vw;
            max-width: 100vw;
            height: 100vh;
            max-height: 100vh;
            margin: 0;
            padding: 0;
            border: none;
            background: #0a0a0a;
        }
        #caption-edit-dialog::backdrop { background: rgba(0, 0, 0, 0.85); }
        .caption-edit-dialog-inner {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .caption-edit-dialog-status {
            position: absolute;
            inset: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            background: #0a0a0a;
            color: #888;
            font-size: 0.88rem;
        }
        .caption-edit-dialog-status[hidden] { display: none; }
        #caption-edit-iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #0a0a0a;
        }

        .caption-upload-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .caption-upload-row input[type="file"] {
            flex: 1;
            min-width: 0;
            padding: 0.4rem 0.5rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #ccc;
            font-size: 0.82rem;
        }
        .caption-upload-row select {
            width: 9rem;
            flex-shrink: 0;
            padding: 0.45rem 0.5rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 0.82rem;
            outline: none;
        }
        .caption-upload-row select:focus { border-color: #555; }
        .btn-remove-row {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.1rem;
            line-height: 1;
            padding: 0.2rem 0.35rem;
            flex-shrink: 0;
        }
        .btn-remove-row:hover { color: #a55; }
        .btn-add-caption {
            margin-top: 0.25rem;
            padding: 0.4rem 0.75rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #888;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .btn-add-caption:hover { background: #222; color: #bbb; }
        .field-hint {
            font-size: 0.75rem;
            color: #555;
            margin-top: 0.3rem;
        }

        .btn-generate {
            padding: 0.55rem 1.1rem;
            background: #1a4a1a;
            border: 1px solid #2a6a2a;
            border-radius: 4px;
            color: #7ed87e;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-generate:hover { background: #1f5a1f; }
        .btn-generate:disabled { opacity: 0.55; cursor: default; background: #141414; border-color: #2a2a2a; color: #555; }

        #ct-dialog {
            width: min(34rem, 92vw);
            border: 1px solid #2a2a2a;
            border-radius: 6px;
            background: #111;
            padding: 0;
            color: #e0e0e0;
            font-family: system-ui, sans-serif;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
        }
        #ct-dialog::backdrop { background: rgba(0,0,0,0.72); }
        .ct-inner { padding: 1.5rem; }
        .ct-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #aaa;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }
        .ct-lang-list { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.25rem; }
        .ct-lang-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #141414;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 0.75rem 1rem;
        }
        .ct-lang-label { font-size: 0.9rem; }
        .ct-badge {
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            border: 1px solid transparent;
            flex-shrink: 0;
        }
        .ct-badge-pending { color: #888; border-color: #333; background: #141414; }
        .ct-badge-running { color: #d4aa40; border-color: #3a2a10; background: #181410; }
        .ct-badge-done    { color: #7ed87e; border-color: #2a4a2a; background: #101810; }
        .ct-badge-error   { color: #e05555; border-color: #4a2020; background: #1a1010; }
        .ct-card-right { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        .ct-retry-btn {
            background: transparent;
            color: #aaa;
            border: 1px solid #444;
            border-radius: 4px;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .ct-retry-btn:hover { color: #fff; border-color: #666; }
        .ct-actions { display: flex; gap: 0.75rem; align-items: center; }
        .ct-btn-primary {
            padding: 0.55rem 1.1rem;
            background: #1a3a6e;
            border: 1px solid #2a5090;
            border-radius: 4px;
            color: #9ab8ff;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .ct-btn-primary:hover { background: #1f4580; }
        .ct-btn-primary:disabled { opacity: 0.55; cursor: default; }
        .ct-btn-close {
            padding: 0.55rem 1.1rem;
            background: transparent;
            border: 1px solid #333;
            border-radius: 4px;
            color: #888;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .ct-btn-close:hover { color: #ccc; border-color: #555; }
        .ct-info {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }
        .ct-feedback {
            font-size: 0.82rem;
            margin-top: 0.75rem;
            min-height: 1.2em;
        }
        .ct-feedback.ok  { color: #4a8a4a; }
        .ct-feedback.err { color: #a55; }
        .ct-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #333;
            border-top-color: #888;
            border-radius: 50%;
            animation: caption-spin 0.7s linear infinite;
            vertical-align: middle;
            margin-right: 0.4rem;
        }
    </style>
</head>
<body>
<header>
    <h1>Studio — Continguts</h1>
    <div class="header-nav">
        <a class="nav-link" href="./">← Inici</a>
        <a class="nav-link" href="?action=continguts">← Continguts</a>
        <a class="nav-link" href="?action=logout">Tanca la sessió</a>
    </div>
</header>
<main>
    <div class="video-hero">
        <?php if (!empty($video['thumbnail_url'])): ?>
            <img class="video-thumb" src="<?= htmlspecialchars($video['thumbnail_url'], ENT_QUOTES) ?>" alt="" loading="lazy">
        <?php else: ?>
            <div class="video-thumb-placeholder"></div>
        <?php endif; ?>
        <h2 id="video-hero-title"><?= htmlspecialchars($video['title'] ?? '', ENT_QUOTES) ?></h2>
    </div>

    <div class="video-edit-form" data-vimeo-id="<?= htmlspecialchars($video['vimeo_id'] ?? '', ENT_QUOTES) ?>">
        <div class="field">
            <label class="field-label">Títol</label>
            <input class="title-input" type="text" value="<?= htmlspecialchars($video['title'] ?? '', ENT_QUOTES) ?>">
        </div>
        <div class="field">
            <label class="field-label">Tipologia</label>
            <select class="typology-select">
                <option value="">Seleccioneu…</option>
                <?php foreach ($typologies as $ty): ?>
                <option value="<?= htmlspecialchars($ty['id'], ENT_QUOTES) ?>"<?= ($video['typology'] ?? '') === $ty['id'] ? ' selected' : '' ?>><?= htmlspecialchars($ty['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field-label">Etiquetes</label>
            <div class="chip-wrapper">
                <div class="chip-input-box">
                    <?php foreach ($video['tags'] ?? [] as $tag): ?>
                    <span class="chip" data-tag="<?= htmlspecialchars($tag, ENT_QUOTES) ?>">
                        <?= htmlspecialchars($tag) ?>
                        <button type="button" class="chip-remove" title="Elimina">×</button>
                    </span>
                    <?php endforeach; ?>
                    <input type="text" class="chip-text-input" placeholder="Afegir etiqueta…" autocomplete="off">
                </div>
                <div class="tag-suggestions"></div>
            </div>
        </div>
        <div class="field">
            <label class="field-label">Subtítols</label>
            <?php if (!empty($video['captions'])): ?>
            <?php
            $langLabels = [];
            foreach ($subtitleLanguages as $sl) {
                $langLabels[$sl['id']] = $sl['label'];
            }
            $masterCaptionLang = $video['master_caption_lang'] ?? ($video['captions'][0]['lang'] ?? '');
            ?>
            <div class="caption-table-feedback" id="caption-table-feedback"></div>
            <table class="caption-table" id="caption-tracks-table">
                <thead>
                    <tr>
                        <th class="caption-master-cell">Master</th>
                        <th>Idioma</th>
                        <th>Fitxer al servidor</th>
                        <th>Descàrrega</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($video['captions'] as $caption): ?>
                    <?php $langId = $caption['lang'] ?? ''; ?>
                    <tr data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>">
                        <td class="caption-master-cell">
                            <input
                                type="radio"
                                class="caption-master-radio"
                                name="master_caption"
                                value="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"
                                <?= $langId === $masterCaptionLang ? 'checked' : '' ?>
                                title="Definir com a subtítol mestre"
                            >
                        </td>
                        <td><?= htmlspecialchars($langLabels[$langId] ?? $langId, ENT_QUOTES) ?></td>
                        <td class="caption-file-cell"><?= htmlspecialchars($caption['file'] ?? '', ENT_QUOTES) ?></td>
                        <td>
                            <?php if (!empty($caption['file'])): ?>
                            <div class="caption-download-btns">
                                <a class="caption-download-btn" href="?action=continguts-download-caption-vtt&vimeo_id=<?= urlencode($video['vimeo_id'] ?? '') ?>&lang=<?= urlencode($langId) ?>"><span class="material-icons">download</span>VTT</a>
                                <a class="caption-download-btn" href="?action=continguts-download-caption-srt&vimeo_id=<?= urlencode($video['vimeo_id'] ?? '') ?>&lang=<?= urlencode($langId) ?>"><span class="material-icons">download</span>SRT</a>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="caption-actions-cell">
                            <div class="caption-action-btns">
                                <button type="button" class="caption-action-btn caption-edit-btn" data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"><span class="material-icons" aria-hidden="true">edit</span><span class="caption-action-label">Edita</span></button>
                                <button type="button" class="caption-action-btn caption-replace-btn" data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"><span class="material-icons" aria-hidden="true">upload</span><span class="caption-action-label">Reemplaça</span></button>
                                <button type="button" class="caption-action-btn danger caption-delete-btn" data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"><span class="material-icons" aria-hidden="true">delete</span><span class="caption-action-label">Elimina</span></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="caption-master-feedback" id="master-caption-feedback"></div>
            <?php endif; ?>
            <div class="caption-uploads"></div>
            <button type="button" class="btn-add-caption">Afegir fitxer</button>
            <p class="field-hint">WebVTT (.vtt) o SubRip (.srt). En desar, es reemplaçarà el fitxer de la llengua seleccionada.</p>
        </div>
        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <button class="btn-save">Desa</button>
            <button class="btn-generate" id="btn-generate-captions" type="button">Genera subtítols</button>
        </div>
        <div class="save-feedback"></div>
    </div>
</main>

<dialog id="ct-dialog">
    <div class="ct-inner">
        <p class="ct-title">Genera subtítols</p>
        <div id="ct-body"></div>
    </div>
</dialog>

<dialog id="caption-edit-dialog">
    <div class="caption-edit-dialog-inner">
        <div class="caption-edit-dialog-status" id="caption-edit-dialog-status" hidden>
            <span class="caption-row-spinner"></span>
            <span>Carregant editor…</span>
        </div>
        <iframe id="caption-edit-iframe" title="Editor de subtítols"></iframe>
    </div>
</dialog>

<script>
(function () {
    var ALL_TAGS = <?= json_encode($catalogTags, JSON_UNESCAPED_UNICODE) ?>;
    var SUBTITLE_LANGUAGES = <?= json_encode($subtitleLanguages, JSON_UNESCAPED_UNICODE) ?>;
    var EXISTING_CAPTION_LANGS = <?= json_encode(array_column($video['captions'] ?? [], 'lang'), JSON_UNESCAPED_UNICODE) ?>;
    var MASTER_LANG = <?= json_encode($video['master_caption_lang'] ?? ($video['captions'][0]['lang'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;

    var form = document.querySelector('.video-edit-form');
    var titleInput = form.querySelector('.title-input');
    var typologySelect = form.querySelector('.typology-select');
    var chipBox = form.querySelector('.chip-input-box');
    var textInput = form.querySelector('.chip-text-input');
    var suggestions = form.querySelector('.tag-suggestions');
    var saveBtn = form.querySelector('.btn-save');
    var feedback = form.querySelector('.save-feedback');
    var heroTitle = document.getElementById('video-hero-title');
    var vimeoId = form.dataset.vimeoId;
    var captionUploads = form.querySelector('.caption-uploads');
    var addCaptionBtn = form.querySelector('.btn-add-caption');

    setupChipInput(chipBox, textInput, suggestions);
    addCaptionRow();
    addCaptionBtn.addEventListener('click', addCaptionRow);
    setupMasterCaptionSelector();
    setupCaptionRowActions();

    saveBtn.addEventListener('click', function () {
        feedback.textContent = '';
        feedback.className = 'save-feedback';
        saveBtn.disabled = true;

        var chips = chipBox.querySelectorAll('.chip');
        var tags = Array.from(chips).map(function (c) { return c.dataset.tag; });

        var body = new FormData();
        body.append('vimeo_id', vimeoId);
        body.append('title', titleInput.value.trim());
        body.append('typology', typologySelect.value);
        tags.forEach(function (t) { body.append('tags[]', t); });

        captionUploads.querySelectorAll('.caption-upload-row').forEach(function (row) {
            var fileInput = row.querySelector('input[type="file"]');
            var langSelect = row.querySelector('select');
            if (fileInput.files.length > 0) {
                body.append('caption_file[]', fileInput.files[0]);
                body.append('caption_lang[]', langSelect.value);
            }
        });

        fetch('?action=continguts-save-video', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    feedback.textContent = data.error || 'Error en desar.';
                    feedback.className = 'save-feedback err';
                } else {
                    if (data.vimeoWarning) {
                        feedback.textContent = 'Desat localment, però Vimeo no s\'ha actualitzat: ' + data.vimeoWarning;
                        feedback.className = 'save-feedback warn';
                    } else {
                        feedback.textContent = 'Desat correctament.';
                        feedback.className = 'save-feedback ok';
                    }
                    heroTitle.textContent = titleInput.value.trim();
                    if (data.captions) {
                        updateCaptionTable(data.captions, data.masterCaptionLang || '');
                        resetCaptionUploads();
                    }
                }
            })
            .catch(function () {
                feedback.textContent = 'Error de connexió.';
                feedback.className = 'save-feedback err';
            })
            .finally(function () { saveBtn.disabled = false; });
    });

    function setupChipInput(chipBox, textInput, suggestions) {
        chipBox.addEventListener('click', function (e) {
            if (e.target === chipBox) textInput.focus();
        });

        chipBox.querySelectorAll('.chip-remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.chip').remove();
            });
        });

        textInput.addEventListener('input', function () {
            showSuggestions(textInput, chipBox, suggestions);
        });

        textInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                var val = textInput.value.trim().replace(/,$/, '');
                if (val) addChip(val, chipBox, textInput);
                hideSuggestions(suggestions);
            } else if (e.key === 'Backspace' && textInput.value === '') {
                var chips = chipBox.querySelectorAll('.chip');
                if (chips.length) chips[chips.length - 1].remove();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                focusSuggestion(suggestions, 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                focusSuggestion(suggestions, -1);
            } else if (e.key === 'Escape') {
                hideSuggestions(suggestions);
            }
        });

        textInput.addEventListener('blur', function () {
            setTimeout(function () { hideSuggestions(suggestions); }, 150);
        });
    }

    function addChip(tag, chipBox, textInput) {
        var existing = Array.from(chipBox.querySelectorAll('.chip')).map(function (c) { return c.dataset.tag; });
        if (existing.indexOf(tag) !== -1) { textInput.value = ''; return; }
        var chip = document.createElement('span');
        chip.className = 'chip';
        chip.dataset.tag = tag;
        chip.textContent = tag + ' ';
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'chip-remove';
        removeBtn.title = 'Elimina';
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', function () { chip.remove(); });
        chip.appendChild(removeBtn);
        chipBox.insertBefore(chip, textInput);
        textInput.value = '';
    }

    function showSuggestions(textInput, chipBox, suggestions) {
        var val = textInput.value.trim().toLowerCase();
        if (!val) { hideSuggestions(suggestions); return; }
        var existing = Array.from(chipBox.querySelectorAll('.chip')).map(function (c) { return c.dataset.tag; });
        var matches = ALL_TAGS.filter(function (t) {
            return t.toLowerCase().startsWith(val) && existing.indexOf(t) === -1;
        }).slice(0, 8);
        if (!matches.length) { hideSuggestions(suggestions); return; }
        suggestions.innerHTML = '';
        matches.forEach(function (tag) {
            var item = document.createElement('div');
            item.className = 'tag-suggestion-item';
            item.textContent = tag;
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                addChip(tag, chipBox, textInput);
                hideSuggestions(suggestions);
            });
            suggestions.appendChild(item);
        });
        suggestions.style.display = 'block';
    }

    function hideSuggestions(suggestions) {
        suggestions.style.display = 'none';
        suggestions.innerHTML = '';
    }

    function focusSuggestion(suggestions, direction) {
        var items = suggestions.querySelectorAll('.tag-suggestion-item');
        if (!items.length) return;
        var current = suggestions.querySelector('.focused');
        var idx = current ? Array.from(items).indexOf(current) + direction : (direction > 0 ? 0 : items.length - 1);
        idx = Math.max(0, Math.min(items.length - 1, idx));
        if (current) current.classList.remove('focused');
        items[idx].classList.add('focused');
    }

    var langLabels = {};
    SUBTITLE_LANGUAGES.forEach(function (lang) {
        langLabels[lang.id] = lang.label;
    });

    function escapeHtml(text) {
        var el = document.createElement('span');
        el.textContent = text;
        return el.innerHTML;
    }

    function updateCaptionTable(captions, masterLang) {
        if (!captions || !captions.length) return;

        var field = captionUploads.parentElement;
        var table = field.querySelector('.caption-table');
        var isNew = !table;
        if (isNew) {
            table = document.createElement('table');
            table.className = 'caption-table';
            table.id = 'caption-tracks-table';
            table.innerHTML =
                '<thead><tr>' +
                '<th class="caption-master-cell">Master</th>' +
                '<th>Idioma</th><th>Fitxer al servidor</th><th>Descàrrega</th><th>Accions</th>' +
                '</tr></thead><tbody></tbody>';
            field.insertBefore(table, captionUploads);
            if (!document.getElementById('caption-table-feedback')) {
                var tableFb = document.createElement('div');
                tableFb.className = 'caption-table-feedback';
                tableFb.id = 'caption-table-feedback';
                field.insertBefore(tableFb, table);
            }
            var fb = document.createElement('div');
            fb.className = 'caption-master-feedback';
            fb.id = 'master-caption-feedback';
            field.insertBefore(fb, captionUploads);
        }

        var currentMaster = masterLang || (function () {
            var checked = table.querySelector('.caption-master-radio:checked');
            return checked ? checked.value : (captions[0] ? captions[0].lang : '');
        })();

        var tbody = table.querySelector('tbody');
        tbody.innerHTML = '';
        captions.forEach(function (caption) {
            var langId = caption.lang || '';
            var tr = document.createElement('tr');
            tr.dataset.lang = langId;
            tr.innerHTML =
                '<td class="caption-master-cell">' +
                '<input type="radio" class="caption-master-radio" name="master_caption"' +
                ' value="' + escapeHtml(langId) + '"' +
                (langId === currentMaster ? ' checked' : '') +
                ' title="Definir com a subtítol mestre">' +
                '</td>' +
                '<td>' + escapeHtml(langLabels[langId] || langId) + '</td>' +
                '<td class="caption-file-cell">' + escapeHtml(caption.file || '') + '</td>' +
                '<td>' + (caption.file
                    ? '<div class="caption-download-btns">' +
                      '<a class="caption-download-btn" href="?action=continguts-download-caption-vtt&vimeo_id=' + encodeURIComponent(vimeoId) + '&lang=' + encodeURIComponent(langId) + '"><span class="material-icons">download</span>VTT</a>' +
                      '<a class="caption-download-btn" href="?action=continguts-download-caption-srt&vimeo_id=' + encodeURIComponent(vimeoId) + '&lang=' + encodeURIComponent(langId) + '"><span class="material-icons">download</span>SRT</a>' +
                      '</div>'
                    : '') + '</td>' +
                '<td class="caption-actions-cell">' + buildActionButtonsHtml(langId) + '</td>';
            tbody.appendChild(tr);
        });

        if (isNew) {
            setupMasterCaptionSelector();
            setupCaptionRowActions();
        } else {
            table.querySelectorAll('.caption-master-radio').forEach(function (radio) {
                radio.addEventListener('change', onMasterCaptionChange);
            });
            setupCaptionRowActions();
        }
    }

    function buildActionButtonsHtml(langId) {
        return '<div class="caption-action-btns">' +
            '<button type="button" class="caption-action-btn caption-edit-btn" data-lang="' + escapeHtml(langId) + '"><span class="material-icons" aria-hidden="true">edit</span><span class="caption-action-label">Edita</span></button>' +
            '<button type="button" class="caption-action-btn caption-replace-btn" data-lang="' + escapeHtml(langId) + '"><span class="material-icons" aria-hidden="true">upload</span><span class="caption-action-label">Reemplaça</span></button>' +
            '<button type="button" class="caption-action-btn danger caption-delete-btn" data-lang="' + escapeHtml(langId) + '"><span class="material-icons" aria-hidden="true">delete</span><span class="caption-action-label">Elimina</span></button>' +
            '</div>';
    }

    function setActionButtonLoading(btn, loading) {
        if (!btn) return;
        btn.classList.toggle('is-loading', loading);
        btn.disabled = loading;
        btn.setAttribute('aria-busy', loading ? 'true' : 'false');
    }

    function setRowActionsBusy(row, busy) {
        if (!row) return;
        row.querySelectorAll('.caption-action-btn').forEach(function (btn) {
            if (!btn.classList.contains('is-loading')) {
                btn.disabled = busy;
            }
        });
    }

    function clearRowActionLoading(row) {
        if (!row) return;
        row.querySelectorAll('.caption-action-btn').forEach(function (btn) {
            setActionButtonLoading(btn, false);
        });
    }

    function setupCaptionRowActions() {
        var table = document.getElementById('caption-tracks-table');
        if (!table) return;

        table.querySelectorAll('.caption-delete-btn').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', onDeleteClick);
        });

        table.querySelectorAll('.caption-replace-btn').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', onReplaceClick);
        });

        table.querySelectorAll('.caption-edit-btn').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', onEditClick);
        });
    }

    function setCaptionTableFeedback(message, kind) {
        var el = document.getElementById('caption-table-feedback');
        if (!el) return;
        el.textContent = message || '';
        el.className = 'caption-table-feedback' + (kind ? ' ' + kind : '');
    }

    function onDeleteClick(e) {
        var btn = e.currentTarget;
        var row = btn.closest('tr');
        var lang = btn.dataset.lang;

        if (!window.confirm('Eliminar aquest fitxer?')) {
            return;
        }

        setCaptionTableFeedback('', '');
        setRowActionsBusy(row, true);
        setActionButtonLoading(btn, true);

        var body = new FormData();
        body.append('vimeo_id', vimeoId);
        body.append('lang', lang);

        fetch('?action=continguts-delete-caption', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    clearRowActionLoading(row);
                    setCaptionTableFeedback(data.error || 'Error en eliminar.', 'err');
                    return;
                }

                var table = document.getElementById('caption-tracks-table');
                row.remove();

                if (data.newMaster && table) {
                    var radio = table.querySelector('.caption-master-radio[value="' + CSS.escape(data.newMaster) + '"]');
                    if (radio) radio.checked = true;
                }

                if (table && !table.querySelector('tbody tr')) {
                    table.remove();
                    var fb = document.getElementById('master-caption-feedback');
                    if (fb) fb.remove();
                }
            })
            .catch(function () {
                clearRowActionLoading(row);
                setCaptionTableFeedback('Error de connexió.', 'err');
            });
    }

    function onReplaceClick(e) {
        var btn = e.currentTarget;
        var lang = btn.dataset.lang;
        var row = btn.closest('tr');
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = '.vtt,.srt';
        input.style.display = 'none';
        document.body.appendChild(input);

        input.addEventListener('change', function () {
            if (!input.files.length) {
                input.remove();
                return;
            }

            if (!row) {
                input.remove();
                setCaptionTableFeedback('Error en reemplaçar.', 'err');
                return;
            }

            setCaptionTableFeedback('', '');
            setRowActionsBusy(row, true);
            setActionButtonLoading(btn, true);

            var body = new FormData();
            body.append('vimeo_id', vimeoId);
            body.append('lang', lang);
            body.append('caption_file', input.files[0]);

            fetch('?action=continguts-replace-caption', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    clearRowActionLoading(row);
                    if (!data.ok) {
                        setCaptionTableFeedback(data.error || 'Error en reemplaçar.', 'err');
                        return;
                    }
                    var fileCell = row.querySelector('.caption-file-cell');
                    if (fileCell && data.caption && data.caption.file) {
                        fileCell.textContent = data.caption.file;
                    }
                    setCaptionTableFeedback('Substituït correctament', 'ok');
                    setTimeout(function () { setCaptionTableFeedback('', ''); }, 2500);
                })
                .catch(function () {
                    clearRowActionLoading(row);
                    setCaptionTableFeedback('Error de connexió.', 'err');
                })
                .finally(function () { input.remove(); });
        });

        input.click();
    }

    function onEditClick(e) {
        var btn = e.currentTarget;
        var lang = btn.dataset.lang;
        var row = btn.closest('tr');
        var dialog = document.getElementById('caption-edit-dialog');
        var iframe = document.getElementById('caption-edit-iframe');
        var status = document.getElementById('caption-edit-dialog-status');
        if (!dialog || !iframe) return;

        setActionButtonLoading(btn, true);
        if (status) status.hidden = false;

        iframe.onload = function () {
            var search = '';
            try {
                search = iframe.contentWindow.location.search;
            } catch (err) {
                return;
            }
            if (search.indexOf('action=continguts-video') !== -1) {
                if (status) status.hidden = true;
                dialog.close();
                iframe.src = 'about:blank';
                setActionButtonLoading(btn, false);
                return;
            }
            if (status) status.hidden = true;
            setActionButtonLoading(btn, false);
        };

        iframe.src = '?action=continguts-caption-review&vimeo_id=' + encodeURIComponent(vimeoId) + '&lang=' + encodeURIComponent(lang);

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    }

    function setupMasterCaptionSelector() {
        var table = document.getElementById('caption-tracks-table');
        if (!table) return;
        table.querySelectorAll('.caption-master-radio').forEach(function (radio) {
            radio.addEventListener('change', onMasterCaptionChange);
        });
    }

    function onMasterCaptionChange(e) {
        var radio = e.target;
        var lang = radio.value;
        var feedbackEl = document.getElementById('master-caption-feedback');
        if (feedbackEl) { feedbackEl.textContent = ''; feedbackEl.className = 'caption-master-feedback'; }

        var body = new FormData();
        body.append('vimeo_id', vimeoId);
        body.append('lang', lang);

        fetch('?action=continguts-set-master-caption', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!feedbackEl) return;
                if (data.ok) {
                    feedbackEl.textContent = 'Subtítol mestre actualitzat.';
                    feedbackEl.className = 'caption-master-feedback ok';
                    setTimeout(function () { feedbackEl.textContent = ''; feedbackEl.className = 'caption-master-feedback'; }, 2500);
                } else {
                    feedbackEl.textContent = data.error || 'Error en desar.';
                    feedbackEl.className = 'caption-master-feedback err';
                }
            })
            .catch(function () {
                if (feedbackEl) { feedbackEl.textContent = 'Error de connexió.'; feedbackEl.className = 'caption-master-feedback err'; }
            });
    }

    function resetCaptionUploads() {
        captionUploads.innerHTML = '';
        addCaptionRow();
    }

    function addCaptionRow() {
        var row = document.createElement('div');
        row.className = 'caption-upload-row';

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.vtt,.srt';

        var langSelect = document.createElement('select');
        SUBTITLE_LANGUAGES.forEach(function (lang) {
            var opt = document.createElement('option');
            opt.value = lang.id;
            opt.textContent = lang.label;
            langSelect.appendChild(opt);
        });

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn-remove-row';
        removeBtn.title = 'Elimina';
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', function () {
            if (captionUploads.querySelectorAll('.caption-upload-row').length > 1) {
                row.remove();
            } else {
                fileInput.value = '';
            }
        });

        row.appendChild(fileInput);
        row.appendChild(langSelect);
        row.appendChild(removeBtn);
        captionUploads.appendChild(row);
    }
    // ── Caption translation modal ──────────────────────────────────────────

    var generateBtn     = document.getElementById('btn-generate-captions');
    var ctDialog        = document.getElementById('ct-dialog');
    var ctBody          = document.getElementById('ct-body');
    var ctPollTimer     = null;
    var ctBgPollTimer   = null;
    var ctLangLabels    = {};
    SUBTITLE_LANGUAGES.forEach(function (l) { ctLangLabels[l.id] = l.label; });

    function ctStatusUrl() {
        return '?action=continguts-caption-translate-status&vimeo_id=' + encodeURIComponent(vimeoId);
    }

    function ctFetchStatus(cb) {
        fetch(ctStatusUrl())
            .then(function (r) { return r.json(); })
            .then(cb)
            .catch(function () { cb({ status: 'error', message: 'Error de connexió.' }); });
    }

    function ctStopPoll() {
        if (ctPollTimer) { clearInterval(ctPollTimer); ctPollTimer = null; }
    }

    function ctStartPoll() {
        ctStopPoll();
        ctPollTimer = setInterval(function () {
            ctFetchStatus(function (data) {
                if (data.status === 'saved') {
                    ctStopPoll();
                    ctRender('saved', data);
                    ctScheduleReload();
                } else if (data.status === 'pending' || data.status === 'running') {
                    ctRender('running', data);
                }
            });
        }, 2000);
    }

    function ctStartBgPoll() {
        if (ctBgPollTimer) return;
        ctBgPollTimer = setInterval(function () {
            ctFetchStatus(function (data) {
                if (data.status === 'saved') {
                    clearInterval(ctBgPollTimer);
                    ctBgPollTimer = null;
                    window.location.reload();
                }
            });
        }, 3000);
    }

    function ctStopBgPoll() {
        if (ctBgPollTimer) { clearInterval(ctBgPollTimer); ctBgPollTimer = null; }
    }

    function ctScheduleReload() {
        setTimeout(function () { window.location.reload(); }, 1800);
    }

    function ctSetPageButton(mode) {
        if (!generateBtn) return;
        if (mode === 'running') {
            generateBtn.disabled = true;
            generateBtn.textContent = 'Traduint…';
        } else {
            generateBtn.disabled = false;
            generateBtn.textContent = 'Genera subtítols';
        }
    }

    function ctBadgeHtml(status) {
        var map = {
            pending: ['ct-badge-pending', 'Pendent'],
            running: ['ct-badge-running', 'Processant'],
            done:    ['ct-badge-done',    'Generat'],
            error:   ['ct-badge-error',   'Error'],
        };
        var pair = map[status] || map.pending;
        return '<span class="ct-badge ' + pair[0] + '">' + pair[1] + '</span>';
    }

    function ctRender(state, data) {
        var html = '';
        switch (state) {
            case 'loading':
                html = '<p class="ct-info"><span class="ct-spinner"></span> Carregant…</p>';
                break;

            case 'confirming': {
                var missing = (data && data.missingTargets) ? data.missingTargets : [];
                if (missing.length === 0) {
                    html = '<p class="ct-info">Tots els idiomes objectiu ja tenen subtítols per a aquest vídeo.</p>';
                    html += '<div class="ct-actions"><button type="button" class="ct-btn-close" id="ct-close-btn">Tanca</button></div>';
                } else {
                    html += '<p class="ct-info">Es generaran els subtítols que falten per als idiomes objectiu:</p>';
                    html += '<div class="ct-lang-list">';
                    missing.forEach(function (l) {
                        html += '<div class="ct-lang-card"><span class="ct-lang-label">' + escapeHtml(l.label) + '</span></div>';
                    });
                    html += '</div>';
                    html += '<div class="ct-actions">';
                    html += '<button type="button" class="ct-btn-primary" id="ct-start-btn">Inicia la traducció</button>';
                    html += '<button type="button" class="ct-btn-close" id="ct-close-btn">Cancel·la</button>';
                    html += '</div>';
                    html += '<div class="ct-feedback" id="ct-feedback"></div>';
                }
                break;
            }

            case 'nothing':
                html = '<p class="ct-info">Tots els idiomes objectiu ja tenen subtítols per a aquest vídeo.</p>';
                html += '<div class="ct-actions"><button type="button" class="ct-btn-close" id="ct-close-btn">Tanca</button></div>';
                break;

            case 'running': {
                var langs = (data && data.languages) ? data.languages : {};
                var allIds = Object.keys(langs);
                html += '<div class="ct-lang-list">';
                allIds.forEach(function (id) {
                    var entry   = langs[id] || { status: 'pending' };
                    var status  = entry.status || 'pending';
                    var label   = escapeHtml(ctLangLabels[id] || id);
                    html += '<div class="ct-lang-card"><span class="ct-lang-label">' + label + '</span>';
                    html += '<div class="ct-card-right">' + ctBadgeHtml(status) + '</div></div>';
                });
                html += '</div>';
                html += '<div class="ct-actions"><button type="button" class="ct-btn-close" id="ct-close-btn">Tanca</button></div>';
                html += '<p class="ct-info" style="margin-top:0.75rem;margin-bottom:0;">La traducció continuarà en segon pla si tanqueu.</p>';
                break;
            }

            case 'saved': {
                var saved  = (data && data.savedLangs)  ? data.savedLangs  : [];
                var errors = (data && data.errorLangs)  ? data.errorLangs  : [];
                var langs  = (data && data.languages)   ? data.languages   : {};
                html += '<div class="ct-lang-list">';
                saved.forEach(function (id) {
                    html += '<div class="ct-lang-card"><span class="ct-lang-label">' + escapeHtml(ctLangLabels[id] || id) + '</span>';
                    html += '<div class="ct-card-right">' + ctBadgeHtml('done') + '</div></div>';
                });
                errors.forEach(function (id) {
                    html += '<div class="ct-lang-card"><span class="ct-lang-label">' + escapeHtml(ctLangLabels[id] || id) + '</span>';
                    html += '<div class="ct-card-right">' + ctBadgeHtml('error');
                    html += '<button type="button" class="ct-retry-btn" data-lang="' + escapeHtml(id) + '">Reintenta</button>';
                    html += '</div></div>';
                });
                html += '</div>';
                if (saved.length > 0) {
                    html += '<p class="ct-feedback ok">Subtítols desats al catàleg. Actualitzant la pàgina…</p>';
                } else {
                    html += '<p class="ct-feedback err">No s\'ha pogut desar cap traducció.</p>';
                    html += '<div class="ct-actions"><button type="button" class="ct-btn-close" id="ct-close-btn">Tanca</button></div>';
                }
                break;
            }

            case 'error': {
                var msg = (data && data.message) ? data.message : 'Error desconegut.';
                html = '<p class="ct-info" style="color:#a55;">' + escapeHtml(msg) + '</p>';
                html += '<div class="ct-actions"><button type="button" class="ct-btn-close" id="ct-close-btn">Tanca</button></div>';
                break;
            }
        }

        ctBody.innerHTML = html;
        ctBindButtons(state, data);
    }

    function ctBindButtons(state, data) {
        var closeBtn = document.getElementById('ct-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                ctStopPoll();
                ctDialog.close();
            });
        }

        var startBtn = document.getElementById('ct-start-btn');
        if (startBtn) {
            startBtn.addEventListener('click', function () {
                startBtn.disabled = true;
                var fb = document.getElementById('ct-feedback');
                if (fb) { fb.textContent = ''; fb.className = 'ct-feedback'; }

                var body = new FormData();
                body.append('vimeo_id', vimeoId);
                fetch('?action=continguts-caption-translate-start', { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d.ok) {
                            if (fb) { fb.textContent = d.error || 'Error en iniciar.'; fb.className = 'ct-feedback err'; }
                            startBtn.disabled = false;
                            return;
                        }
                        if (d.nothing_to_translate) {
                            ctRender('nothing', null);
                            return;
                        }
                        ctSetPageButton('running');
                        ctStopBgPoll();
                        var initLangs = {};
                        (d.targets || []).forEach(function (id) { initLangs[id] = { status: 'pending' }; });
                        ctRender('running', { languages: initLangs });
                        ctStartPoll();
                        ctStartBgPoll();
                    })
                    .catch(function () {
                        if (fb) { fb.textContent = 'Error de connexió.'; fb.className = 'ct-feedback err'; }
                        startBtn.disabled = false;
                    });
            });
        }

        ctBody.querySelectorAll('.ct-retry-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var lang = btn.dataset.lang;
                btn.disabled = true;
                var body = new FormData();
                body.append('vimeo_id', vimeoId);
                body.append('lang', lang);
                fetch('?action=continguts-caption-translate-retry', { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d.ok) { btn.disabled = false; return; }
                        ctSetPageButton('running');
                        ctStopBgPoll();
                        // Merge updated lang into running view
                        var langs = (data && data.languages) ? Object.assign({}, data.languages) : {};
                        langs[lang] = { status: 'pending' };
                        var saved  = (data && data.savedLangs)  ? data.savedLangs.filter(function (id) { return id !== lang; })  : [];
                        var errors = (data && data.errorLangs)  ? data.errorLangs.filter(function (id) { return id !== lang; })  : [];
                        ctRender('running', { languages: langs, savedLangs: saved, errorLangs: errors });
                        ctStartPoll();
                        ctStartBgPoll();
                    })
                    .catch(function () { btn.disabled = false; });
            });
        });
    }

    if (generateBtn) {
        // Check status on page load to set initial button state
        ctFetchStatus(function (data) {
            if (data.status === 'pending' || data.status === 'running') {
                ctSetPageButton('running');
                ctStartBgPoll();
            }
        });

        generateBtn.addEventListener('click', function () {
            ctStopPoll();
            ctRender('loading', null);
            if (typeof ctDialog.showModal === 'function') ctDialog.showModal();
            ctFetchStatus(function (data) {
                if (data.status === 'idle') {
                    ctRender('confirming', data);
                } else if (data.status === 'pending' || data.status === 'running') {
                    ctRender('running', data);
                    ctStartPoll();
                } else if (data.status === 'saved') {
                    ctRender('saved', data);
                    if ((data.savedLangs || []).length > 0) ctScheduleReload();
                } else {
                    ctRender('error', { message: data.message || 'Error inesperat.' });
                }
            });
        });
    }

})();
</script>
</body>
</html>
