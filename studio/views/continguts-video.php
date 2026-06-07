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
        .caption-edit-frame-wrap {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .caption-edit-loading {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 0.85rem;
            gap: 0.75rem;
        }
        #caption-edit-iframe {
            flex: 1;
            width: 100%;
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
                                <button type="button" class="caption-action-btn caption-edit-btn" data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"><span class="material-icons">edit</span>Edita</button>
                                <button type="button" class="caption-action-btn caption-replace-btn" data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"><span class="material-icons">upload</span>Reemplaça</button>
                                <button type="button" class="caption-action-btn danger caption-delete-btn" data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"><span class="material-icons">delete</span>Elimina</button>
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
        <button class="btn-save">Desa</button>
        <div class="save-feedback"></div>
    </div>
</main>

<dialog id="caption-edit-dialog">
    <div class="caption-edit-frame-wrap">
        <div class="caption-edit-loading" id="caption-edit-loading">
            <span class="caption-row-spinner"></span>
            Carregant editor…
        </div>
        <iframe id="caption-edit-iframe" title="Editor de subtítols" hidden></iframe>
    </div>
</dialog>

<script>
(function () {
    var ALL_TAGS = <?= json_encode($catalogTags, JSON_UNESCAPED_UNICODE) ?>;
    var SUBTITLE_LANGUAGES = <?= json_encode($subtitleLanguages, JSON_UNESCAPED_UNICODE) ?>;

    var form = document.querySelector('.video-edit-form');
    var titleInput = form.querySelector('.title-input');
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
            '<button type="button" class="caption-action-btn caption-edit-btn" data-lang="' + escapeHtml(langId) + '"><span class="material-icons">edit</span>Edita</button>' +
            '<button type="button" class="caption-action-btn caption-replace-btn" data-lang="' + escapeHtml(langId) + '"><span class="material-icons">upload</span>Reemplaça</button>' +
            '<button type="button" class="caption-action-btn danger caption-delete-btn" data-lang="' + escapeHtml(langId) + '"><span class="material-icons">delete</span>Elimina</button>' +
            '</div>';
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
        var body = new FormData();
        body.append('vimeo_id', vimeoId);
        body.append('lang', lang);

        fetch('?action=continguts-delete-caption', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
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
                setCaptionTableFeedback('Error de connexió.', 'err');
            });
    }

    function onReplaceClick(e) {
        var lang = e.currentTarget.dataset.lang;
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

            var row = e.currentTarget.closest('tr');
            var actionsCell = row.querySelector('.caption-actions-cell');
            actionsCell.querySelector('.caption-action-btns').hidden = true;
            setCaptionTableFeedback('', '');
            var loading = document.createElement('span');
            loading.className = 'caption-row-spinner';
            actionsCell.appendChild(loading);

            var body = new FormData();
            body.append('vimeo_id', vimeoId);
            body.append('lang', lang);
            body.append('caption_file', input.files[0]);

            fetch('?action=continguts-replace-caption', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loading.remove();
                    actionsCell.querySelector('.caption-action-btns').hidden = false;
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
                    loading.remove();
                    actionsCell.querySelector('.caption-action-btns').hidden = false;
                    setCaptionTableFeedback('Error de connexió.', 'err');
                })
                .finally(function () { input.remove(); });
        });

        input.click();
    }

    function onEditClick(e) {
        var lang = e.currentTarget.dataset.lang;
        var dialog = document.getElementById('caption-edit-dialog');
        var iframe = document.getElementById('caption-edit-iframe');
        var loading = document.getElementById('caption-edit-loading');
        if (!dialog || !iframe) return;

        iframe.hidden = true;
        loading.hidden = false;
        iframe.src = '?action=continguts-caption-review&vimeo_id=' + encodeURIComponent(vimeoId) + '&lang=' + encodeURIComponent(lang);

        iframe.onload = function () {
            if (iframe.src.indexOf('continguts-video') !== -1) {
                dialog.close();
                iframe.src = 'about:blank';
                iframe.hidden = true;
                loading.hidden = false;
                return;
            }
            loading.hidden = true;
            iframe.hidden = false;
        };

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
})();
</script>
</body>
</html>
