<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Continguts — Studio</title>
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
        main { max-width: 860px; padding: 2rem 2rem 4rem; }

        /* ── Tabs ── */
        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #222;
            margin-bottom: 2rem;
        }
        .tab-btn {
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            color: #666;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0.65rem 1.25rem;
            margin-bottom: -1px;
            transition: color 0.15s;
        }
        .tab-btn:hover { color: #bbb; }
        .tab-btn.active { color: #e0e0e0; border-bottom-color: #e0e0e0; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── Video list ── */
        .video-row {
            background: #111;
            border: 1px solid #1e1e1e;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            overflow: hidden;
        }
        .video-row-header {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            user-select: none;
        }
        .video-row-header:hover { background: #161616; }
        .video-thumb {
            width: 160px;
            height: 90px;
            object-fit: cover;
            border-radius: 3px;
            background: #222;
            flex-shrink: 0;
        }
        .video-thumb-placeholder {
            width: 160px;
            height: 90px;
            border-radius: 3px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            flex-shrink: 0;
        }
        .video-row-title {
            flex: 1;
            font-size: 0.9rem;
            color: #ccc;
        }
        .video-row-chevron {
            color: #444;
            font-size: 0.75rem;
            transition: transform 0.15s;
        }
        .video-row.open .video-row-chevron { transform: rotate(180deg); }

        .video-edit-panel {
            display: none;
            padding: 1rem 1rem 1.25rem;
            border-top: 1px solid #1e1e1e;
            background: #0d0d0d;
        }
        .video-row.open .video-edit-panel { display: block; }

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

        /* chip tag input */
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

        /* ── Edition groups ── */
        .edition-group {
            margin-bottom: 1rem;
            border: 1px solid #1e1e1e;
            border-radius: 6px;
            overflow: hidden;
            background: #0d0d0d;
        }
        .edition-heading {
            width: 100%;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #888;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.75rem 1rem;
            background: none;
            border: none;
            cursor: pointer;
            user-select: none;
            text-align: left;
        }
        .edition-heading:hover { background: #111; color: #ccc; }
        .edition-count {
            font-size: 0.72rem;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 0.1rem 0.5rem;
            color: #444;
        }
        .edition-chevron {
            margin-left: auto;
            color: #444;
            font-size: 0.75rem;
            transition: transform 0.15s;
        }
        .edition-group.open .edition-chevron { transform: rotate(180deg); }
        .edition-videos { display: none; padding: 0 0.5rem 0.5rem; }
        .edition-group.open .edition-videos { display: block; }

        /* ── Config list (editions / sign languages) ── */
        .config-list { margin-bottom: 2rem; }
        .config-entry {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid #1a1a1a;
        }
        .config-entry:last-child { border-bottom: none; }
        .config-entry-label {
            flex: 1;
            font-size: 0.9rem;
            color: #ccc;
        }
        .inline-label-input {
            flex: 1;
            padding: 0.35rem 0.5rem;
            background: #1a1a1a;
            border: 1px solid #444;
            border-radius: 3px;
            color: #e0e0e0;
            font-size: 0.9rem;
            outline: none;
            display: none;
        }
        .inline-label-input.editing { display: block; }
        .config-entry-label.editing { display: none; }
        .config-id {
            font-size: 0.75rem;
            color: #444;
            font-family: monospace;
        }
        .btn-icon {
            background: none;
            border: 1px solid #333;
            border-radius: 3px;
            color: #666;
            cursor: pointer;
            font-size: 0.75rem;
            padding: 0.3rem 0.55rem;
        }
        .btn-icon:hover { color: #bbb; border-color: #555; }
        .btn-icon.danger:hover { color: #e55; border-color: #744; }
        .btn-icon.confirm { background: #1a3a6e; border-color: #2a5090; color: #9ab8ff; }
        .config-feedback {
            font-size: 0.78rem;
            margin-top: 0.25rem;
        }
        .config-feedback.ok { color: #4a8a4a; }
        .config-feedback.err { color: #a55; }

        /* ── Add panel (shared) ── */
        .config-new-panel {
            display: none;
            margin-top: 1.25rem;
            padding: 1rem;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 5px;
        }
        .config-new-panel.is-open { display: block; }
        .config-new-panel h3 {
            font-size: 0.85rem;
            font-weight: 500;
            color: #aaa;
            margin-bottom: 0.85rem;
        }
        .config-new-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .config-new-grid--year { grid-template-columns: 1fr 6rem; }
        input.config-input {
            display: block;
            width: 100%;
            padding: 0.55rem 0.7rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 0.875rem;
            outline: none;
        }
        input.config-input:focus { border-color: #555; }
        .config-preview {
            font-size: 0.8rem;
            color: #666;
            line-height: 1.5;
            margin-bottom: 0.85rem;
        }
        .config-preview strong { color: #999; font-weight: 500; }
        .config-preview .value { color: #bbb; }
        .config-new-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .btn-secondary {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #aaa;
            font-size: 0.85rem;
            padding: 0.5rem 0.85rem;
            cursor: pointer;
        }
        .btn-secondary:hover { background: #222; color: #ddd; }
        .config-add-error {
            font-size: 0.82rem;
            color: #e05555;
            margin-bottom: 0.65rem;
        }
        .config-add-error:empty { display: none; }
        .section-label {
            font-size: 0.78rem;
            color: #555;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        .add-trigger-btn {
            background: none;
            border: 1px dashed #333;
            border-radius: 4px;
            color: #555;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            margin-top: 0.75rem;
            width: 100%;
            text-align: left;
        }
        .add-trigger-btn:hover { color: #999; border-color: #555; }
    </style>
</head>
<body>
<header>
    <h1>Studio — Continguts</h1>
    <div class="header-nav">
        <a class="nav-link" href="./">← Inici</a>
        <a class="nav-link" href="?action=logout">Tanca la sessió</a>
    </div>
</header>
<main>
    <div class="tabs">
        <button class="tab-btn active" data-tab="videos">Vídeos</button>
        <button class="tab-btn" data-tab="editions">Edicions</button>
        <button class="tab-btn" data-tab="languages">Llengues de signes</button>
    </div>

    <!-- ══ Vídeos ══════════════════════════════════════════════════════════ -->
    <div class="tab-panel active" id="tab-videos">
        <?php if (empty($catalogVideos)): ?>
            <p style="color:#555;font-size:0.9rem;">El catàleg no conté cap vídeo publicat.</p>
        <?php else: ?>
        <?php
            $editionLabelById = array_column($editions, 'label', 'id');
            $videosByEdition = [];
            foreach ($catalogVideos as $video) {
                $videosByEdition[$video['edition'] ?? ''][] = $video;
            }
            $orderedEditionIds = array_column($editions, 'id');
            foreach (array_keys($videosByEdition) as $edId) {
                if (!in_array($edId, $orderedEditionIds, true)) {
                    $orderedEditionIds[] = $edId;
                }
            }
        ?>
        <?php foreach ($orderedEditionIds as $edId): ?>
            <?php if (empty($videosByEdition[$edId])): continue; endif; ?>
            <div class="edition-group open">
                <button type="button" class="edition-heading" aria-expanded="true">
                    <?= htmlspecialchars($editionLabelById[$edId] ?? $edId) ?>
                    <span class="edition-count"><?= count($videosByEdition[$edId]) ?></span>
                    <span class="edition-chevron" aria-hidden="true">▼</span>
                </button>
                <div class="edition-videos">
                <?php foreach ($videosByEdition[$edId] as $video): ?>
                <?php $vid = htmlspecialchars($video['vimeo_id'] ?? '', ENT_QUOTES) ?>
                <div class="video-row" data-vimeo-id="<?= $vid ?>">
                    <div class="video-row-header">
                        <?php if (!empty($video['thumbnail_url'])): ?>
                            <img class="video-thumb" src="<?= htmlspecialchars($video['thumbnail_url'], ENT_QUOTES) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <div class="video-thumb-placeholder"></div>
                        <?php endif; ?>
                        <span class="video-row-title"><?= htmlspecialchars($video['title'] ?? '', ENT_QUOTES) ?></span>
                        <span class="video-row-chevron">▼</span>
                    </div>
                    <div class="video-edit-panel">
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
                        <button class="btn-save">Desa</button>
                        <div class="save-feedback"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ══ Edicions ════════════════════════════════════════════════════════ -->
    <div class="tab-panel" id="tab-editions">
        <div class="config-list">
            <?php foreach ($editions as $ed): ?>
            <?php $isRef = in_array($ed['id'], $referencedEditionIds, true) ?>
            <div class="config-entry" data-id="<?= htmlspecialchars($ed['id'], ENT_QUOTES) ?>" data-type="edition">
                <span class="config-entry-label"><?= htmlspecialchars($ed['label']) ?></span>
                <input class="inline-label-input" type="text" value="<?= htmlspecialchars($ed['label'], ENT_QUOTES) ?>">
                <span class="config-id"><?= htmlspecialchars($ed['id']) ?></span>
                <button class="btn-icon edit-btn" title="Edita">✏️</button>
                <button class="btn-icon confirm-btn" title="Desa" style="display:none">✓</button>
                <?php if (!$isRef): ?>
                <button class="btn-icon danger delete-btn" title="Elimina">🗑</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Add edition -->
        <button class="add-trigger-btn" id="edition-add-trigger">+ Afegir edició…</button>
        <div class="config-new-panel" id="edition-new-panel">
            <h3>Nova edició</h3>
            <p class="config-add-error" id="edition-add-error" role="alert"></p>
            <div class="config-new-grid config-new-grid--year">
                <div>
                    <label class="field-label" for="edition_city_c">Ciutat</label>
                    <input type="text" id="edition_city_c" class="config-input" autocomplete="off" placeholder="p. ex. Lisboa">
                </div>
                <div>
                    <label class="field-label" for="edition_year_c">Any</label>
                    <input type="text" id="edition_year_c" class="config-input" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="off" placeholder="2027">
                </div>
            </div>
            <p class="config-preview">
                <strong>Nom:</strong> <span class="value" id="edition-preview-label-c">—</span><br>
                <strong>Identificador:</strong> <span class="value" id="edition-preview-id-c">—</span>
            </p>
            <div class="config-new-actions">
                <button type="button" class="btn-secondary" id="edition-add-btn-c">Afegir</button>
                <button type="button" class="btn-secondary" id="edition-cancel-btn-c" style="color:#666">Cancel·la</button>
            </div>
        </div>
    </div>

    <!-- ══ Llengues de signes ════════════════════════════════════════════ -->
    <div class="tab-panel" id="tab-languages">
        <div class="config-list">
            <?php foreach ($signLanguages as $sl): ?>
            <?php $isRef = in_array($sl['id'], $referencedSignLanguageIds, true) ?>
            <div class="config-entry" data-id="<?= htmlspecialchars($sl['id'], ENT_QUOTES) ?>" data-type="sign-language">
                <span class="config-entry-label"><?= htmlspecialchars($sl['label']) ?></span>
                <input class="inline-label-input" type="text" value="<?= htmlspecialchars($sl['label'], ENT_QUOTES) ?>">
                <span class="config-id"><?= htmlspecialchars($sl['id']) ?></span>
                <button class="btn-icon edit-btn" title="Edita">✏️</button>
                <button class="btn-icon confirm-btn" title="Desa" style="display:none">✓</button>
                <?php if (!$isRef): ?>
                <button class="btn-icon danger delete-btn" title="Elimina">🗑</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Add sign language -->
        <button class="add-trigger-btn" id="lang-add-trigger">+ Afegir llengua de signes…</button>
        <div class="config-new-panel" id="lang-new-panel">
            <h3>Nova llengua de signes</h3>
            <p class="config-add-error" id="lang-add-error" role="alert"></p>
            <div class="config-new-grid">
                <div>
                    <label class="field-label" for="sl_code_c">Codi</label>
                    <input type="text" id="sl_code_c" class="config-input" autocomplete="off" placeholder="p. ex. GSS">
                </div>
                <div>
                    <label class="field-label" for="sl_qualifier_c">País o variant</label>
                    <input type="text" id="sl_qualifier_c" class="config-input" autocomplete="off" placeholder="p. ex. Greek">
                </div>
            </div>
            <p class="config-preview">
                <strong>Nom:</strong> <span class="value" id="lang-preview-label-c">—</span><br>
                <strong>Identificador:</strong> <span class="value" id="lang-preview-id-c">—</span>
            </p>
            <div class="config-new-actions">
                <button type="button" class="btn-secondary" id="lang-add-btn-c">Afegir</button>
                <button type="button" class="btn-secondary" id="lang-cancel-btn-c" style="color:#666">Cancel·la</button>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    // ── Tag pool from server ──────────────────────────────────────────────────
    var ALL_TAGS = <?= json_encode($catalogTags, JSON_UNESCAPED_UNICODE) ?>;

    // ── Tabs ──────────────────────────────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        });
    });

    // ── Edition accordions ────────────────────────────────────────────────────
    document.querySelectorAll('.edition-group').forEach(function (group) {
        var toggle = group.querySelector('.edition-heading');
        toggle.addEventListener('click', function () {
            var isOpen = group.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    // ── Video rows ────────────────────────────────────────────────────────────
    document.querySelectorAll('.video-row').forEach(function (row) {
        setupVideoRow(row);
    });

    function setupVideoRow(row) {
        var header = row.querySelector('.video-row-header');
        var panel = row.querySelector('.video-edit-panel');
        var titleInput = panel.querySelector('.title-input');
        var chipBox = panel.querySelector('.chip-input-box');
        var textInput = panel.querySelector('.chip-text-input');
        var suggestions = panel.querySelector('.tag-suggestions');
        var saveBtn = panel.querySelector('.btn-save');
        var feedback = panel.querySelector('.save-feedback');
        var vimeoId = row.dataset.vimeoId;

        header.addEventListener('click', function () {
            row.classList.toggle('open');
        });

        // chip input
        setupChipInput(chipBox, textInput, suggestions);

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

            fetch('?action=continguts-save-video', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        feedback.textContent = data.error || 'Error en desar.';
                        feedback.className = 'save-feedback err';
                    } else if (data.vimeoWarning) {
                        feedback.textContent = 'Desat localment, però Vimeo no s\'ha actualitzat: ' + data.vimeoWarning;
                        feedback.className = 'save-feedback warn';
                        // Update visible title
                        row.querySelector('.video-row-title').textContent = titleInput.value.trim();
                    } else {
                        feedback.textContent = 'Desat correctament.';
                        feedback.className = 'save-feedback ok';
                        row.querySelector('.video-row-title').textContent = titleInput.value.trim();
                    }
                })
                .catch(function () {
                    feedback.textContent = 'Error de connexió.';
                    feedback.className = 'save-feedback err';
                })
                .finally(function () { saveBtn.disabled = false; });
        });
    }

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

    // ── Inline label editing ──────────────────────────────────────────────────
    document.querySelectorAll('.config-entry').forEach(function (entry) {
        var label = entry.querySelector('.config-entry-label');
        var input = entry.querySelector('.inline-label-input');
        var editBtn = entry.querySelector('.edit-btn');
        var confirmBtn = entry.querySelector('.confirm-btn');
        var id = entry.dataset.id;
        var type = entry.dataset.type;

        editBtn.addEventListener('click', function () {
            label.classList.add('editing');
            input.classList.add('editing');
            confirmBtn.style.display = '';
            editBtn.style.display = 'none';
            input.focus();
            input.select();
        });

        function saveLabel() {
            var newLabel = input.value.trim();
            if (!newLabel) return;
            var action = type === 'edition' ? 'continguts-save-edition-label' : 'continguts-save-sign-language-label';
            var body = new FormData();
            body.append('id', id);
            body.append('label', newLabel);
            fetch('?action=' + action, { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        label.textContent = newLabel;
                        label.classList.remove('editing');
                        input.classList.remove('editing');
                        confirmBtn.style.display = 'none';
                        editBtn.style.display = '';
                    } else {
                        alert('Error: ' + (data.error || 'No s\'ha pogut desar.'));
                    }
                })
                .catch(function () { alert('Error de connexió.'); });
        }

        confirmBtn.addEventListener('click', saveLabel);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); saveLabel(); }
            if (e.key === 'Escape') {
                label.classList.remove('editing');
                input.classList.remove('editing');
                confirmBtn.style.display = 'none';
                editBtn.style.display = '';
                input.value = label.textContent.trim();
            }
        });
    });

    // ── Delete entries ────────────────────────────────────────────────────────
    document.querySelectorAll('.delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var entry = btn.closest('.config-entry');
            var id = entry.dataset.id;
            var type = entry.dataset.type;
            var label = entry.querySelector('.config-entry-label').textContent.trim();
            if (!confirm('Eliminar "' + label + '"? Aquesta acció no es pot desfer.')) return;
            var action = type === 'edition' ? 'continguts-delete-edition' : 'continguts-delete-sign-language';
            var body = new FormData();
            body.append('id', id);
            fetch('?action=' + action, { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        entry.remove();
                    } else {
                        alert('Error: ' + (data.error || 'No s\'ha pogut eliminar.'));
                    }
                })
                .catch(function () { alert('Error de connexió.'); });
        });
    });

    // ── Add edition ───────────────────────────────────────────────────────────
    var editionTrigger = document.getElementById('edition-add-trigger');
    var editionPanel = document.getElementById('edition-new-panel');
    var editionCity = document.getElementById('edition_city_c');
    var editionYear = document.getElementById('edition_year_c');
    var editionPreviewLabel = document.getElementById('edition-preview-label-c');
    var editionPreviewId = document.getElementById('edition-preview-id-c');
    var editionAddError = document.getElementById('edition-add-error');
    var editionAddBtn = document.getElementById('edition-add-btn-c');
    var editionCancelBtn = document.getElementById('edition-cancel-btn-c');

    editionTrigger.addEventListener('click', function () {
        editionPanel.classList.add('is-open');
        editionCity.focus();
    });
    editionCancelBtn.addEventListener('click', function () {
        editionPanel.classList.remove('is-open');
        editionCity.value = '';
        editionYear.value = '';
        editionPreviewLabel.textContent = '—';
        editionPreviewId.textContent = '—';
        editionAddError.textContent = '';
    });

    function updateEditionPreview() {
        var city = editionCity.value.trim();
        var year = editionYear.value.trim();
        if (!city || !/^\d{4}$/.test(year)) {
            editionPreviewLabel.textContent = '—';
            editionPreviewId.textContent = '—';
            return;
        }
        var slug = slugify(city);
        editionPreviewLabel.textContent = city + ' ' + year;
        editionPreviewId.textContent = year + '-' + slug;
    }
    editionCity.addEventListener('input', updateEditionPreview);
    editionYear.addEventListener('input', updateEditionPreview);

    editionAddBtn.addEventListener('click', function () {
        editionAddError.textContent = '';
        var city = editionCity.value.trim();
        var year = editionYear.value.trim();
        if (!city || !/^\d{4}$/.test(year)) {
            editionAddError.textContent = 'Indiqueu una ciutat i un any de quatre xifres.';
            return;
        }
        editionAddBtn.disabled = true;
        var body = new FormData();
        body.append('edition_city', city);
        body.append('edition_year', year);
        fetch('?action=add-edition', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    editionAddError.textContent = (data.errors && data.errors[0]) || 'No s\'ha pogut afegir l\'edició.';
                    return;
                }
                // Append to list
                var list = document.querySelector('#tab-editions .config-list');
                var div = document.createElement('div');
                div.className = 'config-entry';
                div.dataset.id = data.id;
                div.dataset.type = 'edition';
                div.innerHTML = '<span class="config-entry-label">' + escHtml(data.label) + '</span>' +
                    '<input class="inline-label-input" type="text" value="' + escHtml(data.label) + '">' +
                    '<span class="config-id">' + escHtml(data.id) + '</span>' +
                    '<button class="btn-icon edit-btn" title="Edita">✏️</button>' +
                    '<button class="btn-icon confirm-btn" title="Desa" style="display:none">✓</button>' +
                    '<button class="btn-icon danger delete-btn" title="Elimina">🗑</button>';
                list.appendChild(div);
                attachConfigEntryListeners(div);
                editionCancelBtn.click();
            })
            .catch(function () { editionAddError.textContent = 'Error de connexió.'; })
            .finally(function () { editionAddBtn.disabled = false; });
    });

    // ── Add sign language ─────────────────────────────────────────────────────
    var langTrigger = document.getElementById('lang-add-trigger');
    var langPanel = document.getElementById('lang-new-panel');
    var slCode = document.getElementById('sl_code_c');
    var slQualifier = document.getElementById('sl_qualifier_c');
    var langPreviewLabel = document.getElementById('lang-preview-label-c');
    var langPreviewId = document.getElementById('lang-preview-id-c');
    var langAddError = document.getElementById('lang-add-error');
    var langAddBtn = document.getElementById('lang-add-btn-c');
    var langCancelBtn = document.getElementById('lang-cancel-btn-c');

    langTrigger.addEventListener('click', function () {
        langPanel.classList.add('is-open');
        slCode.focus();
    });
    langCancelBtn.addEventListener('click', function () {
        langPanel.classList.remove('is-open');
        slCode.value = '';
        slQualifier.value = '';
        langPreviewLabel.textContent = '—';
        langPreviewId.textContent = '—';
        langAddError.textContent = '';
    });

    function updateLangPreview() {
        var code = slCode.value.trim();
        var qualifier = slQualifier.value.trim();
        if (!code || !qualifier) {
            langPreviewLabel.textContent = '—';
            langPreviewId.textContent = '—';
            return;
        }
        var id = slugify(code);
        langPreviewLabel.textContent = code + ' ' + qualifier + ' Sign Language';
        langPreviewId.textContent = id;
    }
    slCode.addEventListener('input', updateLangPreview);
    slQualifier.addEventListener('input', updateLangPreview);

    langAddBtn.addEventListener('click', function () {
        langAddError.textContent = '';
        var code = slCode.value.trim();
        var qualifier = slQualifier.value.trim();
        if (!code || !qualifier) {
            langAddError.textContent = 'Indiqueu un codi i un país o variant.';
            return;
        }
        langAddBtn.disabled = true;
        var body = new FormData();
        body.append('sign_language_code', code);
        body.append('sign_language_qualifier', qualifier);
        fetch('?action=add-sign-language', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    langAddError.textContent = (data.errors && data.errors[0]) || 'No s\'ha pogut afegir la llengua de signes.';
                    return;
                }
                var list = document.querySelector('#tab-languages .config-list');
                var div = document.createElement('div');
                div.className = 'config-entry';
                div.dataset.id = data.id;
                div.dataset.type = 'sign-language';
                div.innerHTML = '<span class="config-entry-label">' + escHtml(data.label) + '</span>' +
                    '<input class="inline-label-input" type="text" value="' + escHtml(data.label) + '">' +
                    '<span class="config-id">' + escHtml(data.id) + '</span>' +
                    '<button class="btn-icon edit-btn" title="Edita">✏️</button>' +
                    '<button class="btn-icon confirm-btn" title="Desa" style="display:none">✓</button>' +
                    '<button class="btn-icon danger delete-btn" title="Elimina">🗑</button>';
                list.appendChild(div);
                attachConfigEntryListeners(div);
                langCancelBtn.click();
            })
            .catch(function () { langAddError.textContent = 'Error de connexió.'; })
            .finally(function () { langAddBtn.disabled = false; });
    });

    // ── Helpers ───────────────────────────────────────────────────────────────
    function attachConfigEntryListeners(entry) {
        var label = entry.querySelector('.config-entry-label');
        var input = entry.querySelector('.inline-label-input');
        var editBtn = entry.querySelector('.edit-btn');
        var confirmBtn = entry.querySelector('.confirm-btn');
        var deleteBtn = entry.querySelector('.delete-btn');
        var id = entry.dataset.id;
        var type = entry.dataset.type;

        editBtn.addEventListener('click', function () {
            label.classList.add('editing');
            input.classList.add('editing');
            confirmBtn.style.display = '';
            editBtn.style.display = 'none';
            input.focus();
            input.select();
        });

        function saveLabel() {
            var newLabel = input.value.trim();
            if (!newLabel) return;
            var action = type === 'edition' ? 'continguts-save-edition-label' : 'continguts-save-sign-language-label';
            var body = new FormData();
            body.append('id', id);
            body.append('label', newLabel);
            fetch('?action=' + action, { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        label.textContent = newLabel;
                        label.classList.remove('editing');
                        input.classList.remove('editing');
                        confirmBtn.style.display = 'none';
                        editBtn.style.display = '';
                    } else {
                        alert('Error: ' + (data.error || 'No s\'ha pogut desar.'));
                    }
                })
                .catch(function () { alert('Error de connexió.'); });
        }

        confirmBtn.addEventListener('click', saveLabel);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); saveLabel(); }
            if (e.key === 'Escape') {
                label.classList.remove('editing');
                input.classList.remove('editing');
                confirmBtn.style.display = 'none';
                editBtn.style.display = '';
                input.value = label.textContent.trim();
            }
        });

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                var labelText = label.textContent.trim();
                if (!confirm('Eliminar "' + labelText + '"? Aquesta acció no es pot desfer.')) return;
                var delAction = type === 'edition' ? 'continguts-delete-edition' : 'continguts-delete-sign-language';
                var body = new FormData();
                body.append('id', id);
                fetch('?action=' + delAction, { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) { entry.remove(); }
                        else { alert('Error: ' + (data.error || 'No s\'ha pogut eliminar.')); }
                    })
                    .catch(function () { alert('Error de connexió.'); });
            });
        }
    }

    function slugify(value) {
        var normalized = value.trim().normalize('NFD').replace(/[̀-ͯ]/g, '');
        return normalized.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}());
</script>
</body>
</html>
