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
            background: var(--studio-bg);
            font-family: var(--studio-font);
            color: var(--studio-text);
        }
        main { width: 100%; padding: 2rem clamp(1.5rem, 4vw, 3rem) 4rem; }

        .video-hero {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--studio-border-subtle);
        }
        .video-thumb {
            width: 200px;
            height: 112px;
            object-fit: cover;
            border-radius: 4px;
            background: var(--studio-border-subtle);
            flex-shrink: 0;
        }
        .video-thumb-placeholder {
            width: 200px;
            height: 112px;
            border-radius: 4px;
            background: var(--studio-input-bg);
            border: 1px solid var(--studio-border);
            flex-shrink: 0;
        }
        .video-hero-meta {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .video-hero h2 {
            font-size: 1.15rem;
            font-weight: 500;
            color: var(--studio-text);
            line-height: 1.35;
            margin: 0;
        }

        .field { margin-bottom: 1rem; }
        label.field-label {
            display: block;
            font-size: 0.78rem;
            color: var(--studio-text-muted);
            margin-bottom: 0.3rem;
        }
        input.title-input {
            display: block;
            width: 100%;
            padding: 0.55rem 0.75rem;
            background: var(--studio-input-bg);
            border: 1px solid var(--studio-border);
            border-radius: 4px;
            color: var(--studio-text);
            font-size: 0.9rem;
            outline: none;
        }
        input.title-input:focus { border-color: var(--studio-text-muted); }
        select.typology-select {
            display: block;
            width: 100%;
            padding: 0.55rem 0.75rem;
            background: var(--studio-input-bg);
            border: 1px solid var(--studio-border);
            border-radius: 4px;
            color: var(--studio-text);
            font-size: 0.9rem;
            outline: none;
        }
        select.typology-select:focus { border-color: var(--studio-text-muted); }


        .btn-save {
            padding: 0.55rem 1.1rem;
            background: var(--studio-accent-bg);
            border: 1px solid var(--studio-accent-border);
            border-radius: 4px;
            color: var(--studio-accent-text);
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-save:hover { background: var(--studio-accent-bg-hover); }
        .btn-danger-solid {
            padding: 0.55rem 1.1rem;
            background: var(--studio-danger);
            border: 1px solid var(--studio-danger);
            border-radius: 4px;
            color: #fff;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-danger-solid:hover:not(:disabled) { opacity: 0.85; }
        .btn-danger-solid:disabled { opacity: 0.5; cursor: default; }
        .btn-save:disabled { opacity: 0.5; cursor: default; }
        .visibility-control {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            align-self: flex-start;
            flex-wrap: wrap;
        }
        .visibility-status {
            font-size: 0.72rem;
            padding: 0.18rem 0.55rem;
            border-radius: 10px;
            border: 1px solid transparent;
            letter-spacing: 0.02em;
        }
        .visibility-status--visible {
            color: var(--studio-btn-green-text);
            border-color: var(--studio-btn-green-alt-border);
            background: var(--studio-btn-green-alt-bg);
        }
        .visibility-status--hidden {
            color: var(--studio-danger);
            border-color: var(--studio-danger-border);
            background: var(--studio-danger-bg);
        }
        .visibility-action {
            background: none;
            border: none;
            color: var(--studio-text-muted);
            font-size: 0.72rem;
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 2px;
            padding: 0;
        }
        .visibility-action:hover { color: var(--studio-text-secondary); }
        .visibility-action:disabled {
            opacity: 0.5;
            cursor: default;
        }
        .visibility-feedback {
            font-size: 0.72rem;
            min-height: 1em;
            line-height: 1.3;
        }
        .visibility-feedback.err { color: var(--studio-danger); }

        .save-feedback {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }
        .save-feedback.ok { color: var(--studio-success-muted); }
        .save-feedback.warn { color: var(--studio-warn); }
        .save-feedback.err { color: var(--studio-danger); }

        #caption-edit-dialog {
            width: 100vw;
            max-width: 100vw;
            height: 100vh;
            max-height: 100vh;
            margin: 0;
            padding: 0;
            border: none;
            background: var(--studio-bg);
        }
        #caption-edit-dialog::backdrop { background: var(--studio-scrim-heavy); }
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
            background: var(--studio-bg);
            color: var(--studio-text-muted);
            font-size: 0.88rem;
        }
        .caption-edit-dialog-status[hidden] { display: none; }
        #caption-edit-iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: var(--studio-bg);
        }

        .field-hint {
            font-size: 0.75rem;
            color: var(--studio-text-muted);
            margin-top: 0.3rem;
        }

        .btn-generate {
            padding: 0.55rem 1.1rem;
            background: var(--studio-btn-green-alt-bg);
            border: 1px solid var(--studio-btn-green-alt-border);
            border-radius: 4px;
            color: var(--studio-btn-green-text);
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-generate:hover { background: var(--studio-btn-green-alt-bg-hover); }
        .btn-generate:disabled { opacity: 0.55; cursor: default; background: var(--studio-hover); border-color: var(--studio-border); color: var(--studio-text-muted); }

        #ct-dialog {
            width: min(34rem, 92vw);
            border: 1px solid var(--studio-border);
            border-radius: 6px;
            background: var(--studio-surface);
            padding: 0;
            color: var(--studio-text);
            font-family: var(--studio-font);
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            margin: 0;
        }
        #ct-dialog::backdrop { background: var(--studio-scrim); }
        .ct-inner { padding: 1.5rem; }
        .ct-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--studio-text-muted);
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
            background: var(--studio-hover);
            border: 1px solid var(--studio-border-subtle);
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
        .ct-badge-pending { color: var(--studio-text-muted); border-color: var(--studio-border); background: var(--studio-hover); }
        .ct-badge-running { color: var(--studio-warn); border-color: var(--studio-warn-border); background: var(--studio-warn-bg); }
        .ct-badge-done    { color: var(--studio-btn-green-text); border-color: var(--studio-btn-green-alt-border); background: var(--studio-btn-green-alt-bg); }
        .ct-badge-error   { color: var(--studio-danger); border-color: var(--studio-danger-border); background: var(--studio-danger-bg); }
        .ct-card-right { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        .ct-retry-btn {
            background: transparent;
            color: var(--studio-text-muted);
            border: 1px solid var(--studio-text-secondary);
            border-radius: 4px;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .ct-retry-btn:hover { color: var(--studio-text); border-color: var(--studio-text-muted); }
        .ct-actions { display: flex; gap: 0.75rem; align-items: center; }
        .ct-btn-primary {
            padding: 0.55rem 1.1rem;
            background: var(--studio-accent-bg);
            border: 1px solid var(--studio-accent-border);
            border-radius: 4px;
            color: var(--studio-accent-text);
            font-size: 0.85rem;
            cursor: pointer;
        }
        .ct-btn-primary:hover { background: var(--studio-accent-bg-hover); }
        .ct-btn-primary:disabled { opacity: 0.55; cursor: default; }
        .ct-btn-close {
            padding: 0.55rem 1.1rem;
            background: transparent;
            border: 1px solid var(--studio-border);
            border-radius: 4px;
            color: var(--studio-text-muted);
            font-size: 0.85rem;
            cursor: pointer;
        }
        .ct-btn-close:hover { color: var(--studio-text-secondary); border-color: var(--studio-text-muted); }
        .ct-info {
            font-size: 0.85rem;
            color: var(--studio-text-muted);
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }
        .ct-feedback {
            font-size: 0.82rem;
            margin-top: 0.75rem;
            min-height: 1.2em;
        }
        .ct-feedback.ok  { color: var(--studio-success-muted); }
        .ct-feedback.err { color: var(--studio-danger); }
        .ct-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid var(--studio-border);
            border-top-color: var(--studio-text-muted);
            border-radius: 50%;
            animation: caption-spin 0.7s linear infinite;
            vertical-align: middle;
            margin-right: 0.4rem;
        }
    </style>
    <link rel="stylesheet" href="js/tag-input.css?v=<?= filemtime(__DIR__ . '/../js/tag-input.css') ?>">
    <link rel="stylesheet" href="js/caption-table.css?v=<?= filemtime(__DIR__ . '/../js/caption-table.css') ?>">
</head>
<body>
<?php require __DIR__ . '/partials/studio-header.php'; ?>
<main>
    <div class="video-hero">
        <?php if (!empty($video['thumbnail_url'])): ?>
            <img class="video-thumb" src="<?= htmlspecialchars($video['thumbnail_url'], ENT_QUOTES) ?>" alt="" loading="lazy">
        <?php else: ?>
            <div class="video-thumb-placeholder"></div>
        <?php endif; ?>
        <div class="video-hero-meta">
            <h2 id="video-hero-title"><?= htmlspecialchars($video['title'] ?? '', ENT_QUOTES) ?></h2>
            <div class="visibility-control" data-invisible="<?= !empty($video['invisible']) ? '1' : '0' ?>">
                <span class="visibility-status" id="visibility-status"></span>
                <button type="button" class="visibility-action" id="visibility-action"></button>
            </div>
            <div class="visibility-feedback" id="visibility-feedback"></div>
        </div>
    </div>

    <div class="video-edit-form" data-vimeo-id="<?= htmlspecialchars($video['vimeo_id'] ?? '', ENT_QUOTES) ?>">
        <div class="field">
            <label class="field-label">Títol</label>
            <input class="title-input" type="text" value="<?= htmlspecialchars($video['title'] ?? '', ENT_QUOTES) ?>">
        </div>
        <div class="field">
            <label class="field-label">Llengua de signes</label>
            <select class="typology-select sign-language-select">
                <option value="">Seleccioneu…</option>
                <?php foreach ($signLanguages as $sl): ?>
                <option value="<?= htmlspecialchars($sl['id'], ENT_QUOTES) ?>"<?= ($video['sign_language'] ?? '') === $sl['id'] ? ' selected' : '' ?>><?= htmlspecialchars($sl['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field-label">Ciutat</label>
            <select class="typology-select edition-select">
                <option value="">Seleccioneu…</option>
                <?php foreach ($editions as $ed): ?>
                <option value="<?= htmlspecialchars($ed['id'], ENT_QUOTES) ?>"<?= ($video['edition'] ?? '') === $ed['id'] ? ' selected' : '' ?>><?= htmlspecialchars($ed['label']) ?></option>
                <?php endforeach; ?>
            </select>
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
            <div id="caption-table"></div>
            <p class="field-hint">WebVTT (.vtt) o SubRip (.srt). En desar, es reemplaçarà el fitxer de la llengua seleccionada.</p>
        </div>
        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <button class="btn-save">Desa</button>
            <button class="btn-generate" id="btn-generate-captions" type="button">Genera subtítols</button>
            <button type="button" class="btn-danger-solid" id="btn-delete-all-translations">Esborra totes les traduccions</button>
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

<script src="js/transcription-intake.js?v=<?= filemtime(__DIR__ . '/../js/transcription-intake.js') ?>"></script>
<script src="js/tag-input.js?v=<?= filemtime(__DIR__ . '/../js/tag-input.js') ?>"></script>
<script src="js/caption-table.js?v=<?= filemtime(__DIR__ . '/../js/caption-table.js') ?>"></script>
<script>
(function () {
    var ALL_TAGS = <?= json_encode($catalogTags, JSON_UNESCAPED_UNICODE) ?>;
    var SUBTITLE_LANGUAGES = <?= json_encode($subtitleLanguages, JSON_UNESCAPED_UNICODE) ?>;
    var EXISTING_CAPTIONS = <?= json_encode(array_map(static fn(array $c): array => ['lang' => $c['lang'] ?? '', 'file' => $c['file'] ?? ''], $video['captions'] ?? []), JSON_UNESCAPED_UNICODE) ?>;
    var MASTER_LANG = <?= json_encode($video['master_caption_lang'] ?? ($video['captions'][0]['lang'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;

    var form = document.querySelector('.video-edit-form');
    var titleInput = form.querySelector('.title-input');
    var signLanguageSelect = form.querySelector('.sign-language-select');
    var editionSelect = form.querySelector('.edition-select');
    var typologySelect = form.querySelector('.typology-select:not(.sign-language-select):not(.edition-select)');
    var chipBox = form.querySelector('.chip-input-box');
    var textInput = form.querySelector('.chip-text-input');
    var suggestions = form.querySelector('.tag-suggestions');
    var saveBtn = form.querySelector('.btn-save');
    var feedback = form.querySelector('.save-feedback');
    var heroTitle = document.getElementById('video-hero-title');
    var vimeoId = form.dataset.vimeoId;
    var captionTableEl = document.getElementById('caption-table');
    var captionTable = CaptionTable.create(captionTableEl, SUBTITLE_LANGUAGES, {
        mode: 'detail',
        existingCaptions: EXISTING_CAPTIONS,
        master: MASTER_LANG,
        vimeoId: vimeoId,
        onMasterChange: persistMasterCaption,
        onEdit: openCaptionEditor,
        onReplace: replaceCaption,
        onDelete: deleteCaption,
    });

    TagInput.init(chipBox, textInput, suggestions, ALL_TAGS);
    setupVisibilityControl();

    function setupVisibilityControl() {
        var control = document.querySelector('.visibility-control');
        var statusEl = document.getElementById('visibility-status');
        var actionBtn = document.getElementById('visibility-action');
        var visFeedback = document.getElementById('visibility-feedback');
        if (!control || !statusEl || !actionBtn) return;

        function isInvisible() {
            return control.dataset.invisible === '1';
        }

        function renderVisibilityState(invisible) {
            control.dataset.invisible = invisible ? '1' : '0';
            if (invisible) {
                statusEl.textContent = 'Ocult del web';
                statusEl.className = 'visibility-status visibility-status--hidden';
                actionBtn.textContent = 'Torna a fer visible';
            } else {
                statusEl.textContent = 'Visible al web';
                statusEl.className = 'visibility-status visibility-status--visible';
                actionBtn.textContent = 'Oculta del web';
            }
        }

        renderVisibilityState(isInvisible());

        actionBtn.addEventListener('click', function () {
            var nextInvisible = !isInvisible();
            visFeedback.textContent = '';
            visFeedback.className = 'visibility-feedback';
            actionBtn.disabled = true;

            var body = new FormData();
            body.append('vimeo_id', vimeoId);
            body.append('invisible', nextInvisible ? '1' : '0');

            fetch('?action=continguts-set-video-invisible', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        visFeedback.textContent = data.error || 'Error en desar la visibilitat.';
                        visFeedback.className = 'visibility-feedback err';
                    } else {
                        renderVisibilityState(nextInvisible);
                    }
                })
                .catch(function () {
                    visFeedback.textContent = 'Error de connexió.';
                    visFeedback.className = 'visibility-feedback err';
                })
                .finally(function () {
                    actionBtn.disabled = false;
                });
        });
    }

    saveBtn.addEventListener('click', function () {
        feedback.textContent = '';
        feedback.className = 'save-feedback';
        saveBtn.disabled = true;

        var tags = TagInput.getTags(chipBox);

        var body = new FormData();
        body.append('vimeo_id', vimeoId);
        body.append('title', titleInput.value.trim());
        body.append('sign_language', signLanguageSelect.value);
        body.append('edition', editionSelect.value);
        body.append('typology', typologySelect.value);
        tags.forEach(function (t) { body.append('tags[]', t); });

        captionTable.getEntries().forEach(function (entry) {
            body.append('caption_file[]', entry.file);
            body.append('caption_lang[]', entry.langId);
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
                        captionTable.setExisting(data.captions, data.masterCaptionLang || '');
                    }
                }
            })
            .catch(function () {
                feedback.textContent = 'Error de connexió.';
                feedback.className = 'save-feedback err';
            })
            .finally(function () { saveBtn.disabled = false; });
    });

    function escapeHtml(text) {
        var el = document.createElement('span');
        el.textContent = text;
        return el.innerHTML;
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

    function setCaptionTableFeedback(message, kind) {
        var el = document.getElementById('caption-table-feedback');
        if (!el) return;
        el.textContent = message || '';
        el.className = 'caption-table-feedback' + (kind ? ' ' + kind : '');
    }

    function deleteCaption(lang, btn, row) {
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
                captionTable.removeExisting(lang);
                if (data.newMaster) {
                    captionTable.setMaster(data.newMaster);
                }
            })
            .catch(function () {
                clearRowActionLoading(row);
                setCaptionTableFeedback('Error de connexió.', 'err');
            });
    }

    var deleteAllTranslationsBtn = document.getElementById('btn-delete-all-translations');
    if (deleteAllTranslationsBtn) {
        deleteAllTranslationsBtn.addEventListener('click', function () {
            var masterLang = captionTable.getMasterLang();
            var toDelete = captionTable.getExistingLangs().filter(function (lang) { return lang !== masterLang; });
            if (toDelete.length === 0) {
                setCaptionTableFeedback('No hi ha traduccions per esborrar.', '');
                return;
            }
            if (!window.confirm('Eliminar ' + toDelete.length + ' traduccions? Es conservarà el subtítol mestre.')) {
                return;
            }

            setCaptionTableFeedback('', '');
            deleteAllTranslationsBtn.disabled = true;

            var body = new FormData();
            body.append('vimeo_id', vimeoId);

            fetch('?action=continguts-delete-all-translations', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        setCaptionTableFeedback(data.error || 'Error en eliminar.', 'err');
                        return;
                    }
                    (data.deletedLangs || toDelete).forEach(function (lang) {
                        captionTable.removeExisting(lang);
                    });
                    setCaptionTableFeedback('Traduccions eliminades.', 'ok');
                })
                .catch(function () {
                    setCaptionTableFeedback('Error de connexió.', 'err');
                })
                .finally(function () {
                    deleteAllTranslationsBtn.disabled = false;
                });
        });
    }

    function replaceCaption(lang, btn, row) {
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
                    if (!data.ok) {
                        clearRowActionLoading(row);
                        setCaptionTableFeedback(data.error || 'Error en reemplaçar.', 'err');
                        return;
                    }
                    if (data.caption && data.caption.file) {
                        captionTable.updateExistingFile(lang, data.caption.file);
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

    function openCaptionEditor(lang, btn) {
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

    function persistMasterCaption(lang) {
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
                html = '<p class="ct-info" style="color:var(--studio-danger);">' + escapeHtml(msg) + '</p>';
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
