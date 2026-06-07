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
        main { width: 100%; padding: 2rem clamp(1.5rem, 4vw, 3rem) 4rem; }

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

        .tab-panel-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .btn-add-video {
            background: #1a3a6e;
            border: 1px solid #2a5090;
            border-radius: 4px;
            color: #9ab8ff;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }
        .btn-add-video:hover { background: #1f4580; }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.72);
            padding: 1.5rem;
            z-index: 100;
        }
        .modal-overlay:not([hidden]) {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-dialog {
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            max-height: calc(100vh - 3rem);
            max-width: 32rem;
            overflow-y: auto;
            padding: 1.5rem;
            width: 100%;
        }
        .modal-dialog h2 {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
        }
        .modal-field { margin-bottom: 1rem; }
        .modal-field label {
            display: block;
            font-size: 0.78rem;
            color: #777;
            margin-bottom: 0.35rem;
        }
        .modal-field select,
        .modal-field input[type="text"] {
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
        .modal-field select:focus,
        .modal-field input[type="text"]:focus { border-color: #555; }
        .vimeo-preview {
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #0d0d0d;
            border: 1px solid #1e1e1e;
            border-radius: 5px;
        }
        .vimeo-preview[hidden] { display: none; }
        .vimeo-preview-thumb {
            width: 120px;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            border-radius: 4px;
            background: #222;
            flex-shrink: 0;
        }
        .vimeo-preview-thumb-placeholder {
            width: 120px;
            aspect-ratio: 16 / 9;
            border-radius: 4px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            flex-shrink: 0;
        }
        .vimeo-preview-fields { flex: 1; min-width: 0; }
        .modal-error {
            font-size: 0.82rem;
            color: #e05555;
            margin-bottom: 0.75rem;
        }
        .modal-error:empty { display: none; }
        .modal-resolve-status {
            font-size: 0.78rem;
            color: #666;
            margin-top: 0.35rem;
        }
        .modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1.25rem;
        }
        .btn-primary-modal {
            background: #1a3a6e;
            border: 1px solid #2a5090;
            border-radius: 4px;
            color: #9ab8ff;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 0.55rem 1.1rem;
        }
        .btn-primary-modal:hover { background: #1f4580; }
        .btn-primary-modal:disabled { opacity: 0.5; cursor: default; }

        /* ── Video list ── */
        .edition-videos {
            display: none;
            padding: 0.5rem;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        }
        .edition-group.open .edition-videos { display: grid; }
        .video-card {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #111;
            border: 1px solid #1e1e1e;
            border-radius: 6px;
            text-decoration: none;
            color: inherit;
        }
        .video-card:hover { background: #161616; border-color: #2a2a2a; }
        .video-thumb {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            border-radius: 4px;
            background: #222;
        }
        .video-thumb-placeholder {
            width: 100%;
            aspect-ratio: 16 / 9;
            border-radius: 4px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
        }
        .video-card-meta {
            display: flex;
            align-items: flex-start;
            gap: 0.4rem;
            padding: 0 0.15rem 0.25rem;
        }
        .video-card-title {
            flex: 1;
            min-width: 0;
            font-size: 0.85rem;
            line-height: 1.35;
            color: #ccc;
        }
        .video-card:hover .video-card-title { color: #e0e0e0; }
        .video-caption-count {
            flex-shrink: 0;
            font-size: 0.72rem;
            background: #142818;
            border: 1px solid #2a5a32;
            border-radius: 10px;
            padding: 0.1rem 0.45rem;
            color: #6abf73;
            line-height: 1.35;
        }

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
            font-weight: 500;
            text-transform: none;
            letter-spacing: normal;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 0.15rem 0.55rem;
            color: #666;
            white-space: nowrap;
        }
        .edition-chevron {
            margin-left: auto;
            color: #444;
            font-size: 0.75rem;
            transition: transform 0.15s;
        }
        .edition-group.open .edition-chevron { transform: rotate(180deg); }

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
        <button class="tab-btn" data-tab="editions">Ciutats</button>
        <button class="tab-btn" data-tab="languages">Llengues de signes</button>
        <button class="tab-btn" data-tab="subtitle-languages">Llengues orals</button>
    </div>

    <!-- ══ Vídeos ══════════════════════════════════════════════════════════ -->
    <div class="tab-panel active" id="tab-videos">
        <div class="tab-panel-toolbar">
            <button type="button" class="btn-add-video" id="video-add-trigger">+ Afegir vídeo</button>
        </div>
        <p id="videos-empty-msg" style="color:#555;font-size:0.9rem;<?= empty($catalogVideos) ? '' : ' display:none;' ?>">El catàleg no conté cap vídeo publicat.</p>
        <div id="videos-catalog">
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
            <div class="edition-group" data-edition-id="<?= htmlspecialchars($edId, ENT_QUOTES) ?>">
                <button type="button" class="edition-heading" aria-expanded="false">
                    <?= htmlspecialchars($editionLabelById[$edId] ?? $edId) ?>
                    <?php $editionVideoCount = count($videosByEdition[$edId]); ?>
                    <span class="edition-count"><?= $editionVideoCount ?> vídeo<?= $editionVideoCount === 1 ? '' : 's' ?></span>
                    <span class="edition-chevron" aria-hidden="true">▼</span>
                </button>
                <div class="edition-videos">
                <?php foreach ($videosByEdition[$edId] as $video): ?>
                <?php $vid = htmlspecialchars($video['vimeo_id'] ?? '', ENT_QUOTES) ?>
                <a class="video-card" href="?action=continguts-video&amp;vimeo_id=<?= $vid ?>">
                    <?php if (!empty($video['thumbnail_url'])): ?>
                        <img class="video-thumb" src="<?= htmlspecialchars($video['thumbnail_url'], ENT_QUOTES) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <div class="video-thumb-placeholder"></div>
                    <?php endif; ?>
                    <?php $captionCount = count($video['captions'] ?? []); ?>
                    <div class="video-card-meta">
                        <span class="video-card-title"><?= htmlspecialchars($video['title'] ?? '', ENT_QUOTES) ?></span>
                        <span class="video-caption-count" title="<?= $captionCount ?> subtítol<?= $captionCount === 1 ? '' : 's' ?>"><?= $captionCount ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- ══ Ciutats ════════════════════════════════════════════════════════ -->
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

    <!-- ══ Llengues orals ═════════════════════════════════════════════════ -->
    <div class="tab-panel" id="tab-subtitle-languages">
        <div class="config-list">
            <?php foreach ($subtitleLanguages as $sl): ?>
            <?php $isRef = in_array($sl['id'], $referencedSubtitleLanguageIds, true) ?>
            <div class="config-entry" data-id="<?= htmlspecialchars($sl['id'], ENT_QUOTES) ?>" data-type="subtitle-language">
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

        <!-- Add subtitle language -->
        <button class="add-trigger-btn" id="subtitle-lang-add-trigger">+ Afegir llengua oral…</button>
        <div class="config-new-panel" id="subtitle-lang-new-panel">
            <h3>Nova llengua oral</h3>
            <p class="config-add-error" id="subtitle-lang-add-error" role="alert"></p>
            <div class="config-new-grid">
                <div>
                    <label class="field-label" for="oral_code_c">Codi</label>
                    <input type="text" id="oral_code_c" class="config-input" autocomplete="off" placeholder="p. ex. de">
                </div>
                <div>
                    <label class="field-label" for="oral_name_c">Nom</label>
                    <input type="text" id="oral_name_c" class="config-input" autocomplete="off" placeholder="p. ex. Alemany">
                </div>
            </div>
            <p class="config-preview">
                <strong>Nom:</strong> <span class="value" id="subtitle-lang-preview-label-c">—</span><br>
                <strong>Identificador:</strong> <span class="value" id="subtitle-lang-preview-id-c">—</span>
            </p>
            <div class="config-new-actions">
                <button type="button" class="btn-secondary" id="subtitle-lang-add-btn-c">Afegir</button>
                <button type="button" class="btn-secondary" id="subtitle-lang-cancel-btn-c" style="color:#666">Cancel·la</button>
            </div>
        </div>
    </div>
</main>

<div class="modal-overlay" id="video-add-modal" hidden aria-hidden="true">
    <div class="modal-dialog" role="dialog" aria-labelledby="video-add-modal-title">
        <h2 id="video-add-modal-title">Afegir vídeo al catàleg</h2>
        <p class="modal-error" id="video-add-error" role="alert"></p>

        <div class="modal-field">
            <label for="modal-vimeo-input">URL o ID de Vimeo</label>
            <input type="text" id="modal-vimeo-input" class="config-input" autocomplete="off" placeholder="p. ex. 639494119 o https://vimeo.com/…">
            <p class="modal-resolve-status" id="modal-resolve-status"></p>
        </div>

        <div class="vimeo-preview" id="vimeo-preview" hidden>
            <img class="vimeo-preview-thumb" id="vimeo-preview-thumb" alt="" hidden>
            <div class="vimeo-preview-thumb-placeholder" id="vimeo-preview-thumb-placeholder" hidden></div>
            <div class="vimeo-preview-fields">
                <label for="modal-video-title">Títol</label>
                <input type="text" id="modal-video-title" autocomplete="off">
            </div>
        </div>
        <input type="hidden" id="modal-vimeo-id" value="">

        <div class="modal-field">
            <label for="modal-sign-language">Llengua de signes</label>
            <select id="modal-sign-language">
                <option value="">Seleccioneu…</option>
                <?php foreach ($signLanguages as $sl): ?>
                <option value="<?= htmlspecialchars($sl['id'], ENT_QUOTES) ?>"><?= htmlspecialchars($sl['label']) ?></option>
                <?php endforeach; ?>
                <option value="__new__">+ Afegiu una llengua de signes…</option>
            </select>
            <div class="config-new-panel" id="modal-lang-new-panel">
                <h3>Nova llengua de signes</h3>
                <p class="config-add-error" id="modal-lang-add-error" role="alert"></p>
                <div class="config-new-grid">
                    <div>
                        <label class="field-label" for="modal-sl-code">Codi</label>
                        <input type="text" id="modal-sl-code" class="config-input" autocomplete="off" placeholder="p. ex. GSS">
                    </div>
                    <div>
                        <label class="field-label" for="modal-sl-qualifier">País o variant</label>
                        <input type="text" id="modal-sl-qualifier" class="config-input" autocomplete="off" placeholder="p. ex. Greek">
                    </div>
                </div>
                <p class="config-preview">
                    <strong>Nom:</strong> <span class="value" id="modal-lang-preview-label">—</span><br>
                    <strong>Identificador:</strong> <span class="value" id="modal-lang-preview-id">—</span>
                </p>
                <div class="config-new-actions">
                    <button type="button" class="btn-secondary" id="modal-lang-add-btn">Afegir a la llista</button>
                    <button type="button" class="btn-secondary" id="modal-lang-cancel-btn" style="color:#666">Cancel·la</button>
                </div>
            </div>
        </div>

        <div class="modal-field">
            <label for="modal-edition">Ciutat</label>
            <select id="modal-edition">
                <option value="">Seleccioneu…</option>
                <?php foreach ($editions as $ed): ?>
                <option value="<?= htmlspecialchars($ed['id'], ENT_QUOTES) ?>"><?= htmlspecialchars($ed['label']) ?></option>
                <?php endforeach; ?>
                <option value="__new__">+ Afegiu una ciutat…</option>
            </select>
            <div class="config-new-panel" id="modal-edition-new-panel">
                <h3>Nova ciutat</h3>
                <p class="config-add-error" id="modal-edition-add-error" role="alert"></p>
                <div class="config-new-grid config-new-grid--year">
                    <div>
                        <label class="field-label" for="modal-edition-city">Ciutat</label>
                        <input type="text" id="modal-edition-city" class="config-input" autocomplete="off" placeholder="p. ex. Lisboa">
                    </div>
                    <div>
                        <label class="field-label" for="modal-edition-year">Any</label>
                        <input type="text" id="modal-edition-year" class="config-input" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="off" placeholder="2027">
                    </div>
                </div>
                <p class="config-preview">
                    <strong>Nom:</strong> <span class="value" id="modal-edition-preview-label">—</span><br>
                    <strong>Identificador:</strong> <span class="value" id="modal-edition-preview-id">—</span>
                </p>
                <div class="config-new-actions">
                    <button type="button" class="btn-secondary" id="modal-edition-add-btn">Afegir a la llista</button>
                    <button type="button" class="btn-secondary" id="modal-edition-cancel-btn" style="color:#666">Cancel·la</button>
                </div>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-primary-modal" id="video-add-submit" disabled>Afegir al catàleg</button>
            <button type="button" class="btn-secondary" id="video-add-cancel">Cancel·la</button>
        </div>
    </div>
</div>

<script>
(function () {
    var EDITION_LABELS = <?= json_encode(array_column($editions, 'label', 'id'), JSON_UNESCAPED_UNICODE) ?>;
    var CONFIG_ACTIONS = {
        'edition': {
            save: 'continguts-save-edition-label',
            delete: 'continguts-delete-edition'
        },
        'sign-language': {
            save: 'continguts-save-sign-language-label',
            delete: 'continguts-delete-sign-language'
        },
        'subtitle-language': {
            save: 'continguts-save-subtitle-language-label',
            delete: 'continguts-delete-subtitle-language'
        }
    };

    function configAction(type, kind) {
        return (CONFIG_ACTIONS[type] || {})[kind] || '';
    }

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
        attachEditionGroupListeners(group);
    });

    function attachEditionGroupListeners(group) {
        var toggle = group.querySelector('.edition-heading');
        toggle.addEventListener('click', function () {
            var isOpen = group.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    // ── Add video modal ───────────────────────────────────────────────────────
    var videoModal = document.getElementById('video-add-modal');
    var videoAddTrigger = document.getElementById('video-add-trigger');
    var videoAddCancel = document.getElementById('video-add-cancel');
    var videoAddSubmit = document.getElementById('video-add-submit');
    var videoAddError = document.getElementById('video-add-error');
    var modalVimeoInput = document.getElementById('modal-vimeo-input');
    var modalVimeoId = document.getElementById('modal-vimeo-id');
    var modalVideoTitle = document.getElementById('modal-video-title');
    var modalResolveStatus = document.getElementById('modal-resolve-status');
    var vimeoPreview = document.getElementById('vimeo-preview');
    var vimeoPreviewThumb = document.getElementById('vimeo-preview-thumb');
    var vimeoPreviewPlaceholder = document.getElementById('vimeo-preview-thumb-placeholder');
    var modalSignLanguage = document.getElementById('modal-sign-language');
    var modalEdition = document.getElementById('modal-edition');
    var videosCatalog = document.getElementById('videos-catalog');
    var videosEmptyMsg = document.getElementById('videos-empty-msg');
    var resolveTimer = null;
    var resolving = false;

    function openVideoModal() {
        resetVideoModal();
        videoModal.hidden = false;
        videoModal.setAttribute('aria-hidden', 'false');
        modalVimeoInput.focus();
    }

    function closeVideoModal() {
        videoModal.hidden = true;
        videoModal.setAttribute('aria-hidden', 'true');
        resetVideoModal();
    }

    function resetVideoModal() {
        videoAddError.textContent = '';
        modalResolveStatus.textContent = '';
        modalVimeoInput.value = '';
        modalVimeoId.value = '';
        modalVideoTitle.value = '';
        modalSignLanguage.value = '';
        modalEdition.value = '';
        vimeoPreview.hidden = true;
        vimeoPreviewThumb.hidden = true;
        vimeoPreviewPlaceholder.hidden = true;
        videoAddSubmit.disabled = true;
        document.getElementById('modal-lang-new-panel').classList.remove('is-open');
        document.getElementById('modal-edition-new-panel').classList.remove('is-open');
    }

    function updateSubmitState() {
        videoAddSubmit.disabled = !(
            modalVimeoId.value.trim() !== '' &&
            modalSignLanguage.value.trim() !== '' &&
            modalSignLanguage.value !== '__new__' &&
            modalEdition.value.trim() !== '' &&
            modalEdition.value !== '__new__' &&
            modalVideoTitle.value.trim() !== ''
        );
    }

    function showVimeoPreview(thumbnailUrl, title) {
        vimeoPreview.hidden = false;
        modalVideoTitle.value = title;
        if (thumbnailUrl) {
            vimeoPreviewThumb.src = thumbnailUrl;
            vimeoPreviewThumb.hidden = false;
            vimeoPreviewPlaceholder.hidden = true;
        } else {
            vimeoPreviewThumb.hidden = true;
            vimeoPreviewPlaceholder.hidden = false;
        }
        updateSubmitState();
    }

    function resolveVimeoInput() {
        var input = modalVimeoInput.value.trim();
        if (!input) {
            modalVimeoId.value = '';
            vimeoPreview.hidden = true;
            modalResolveStatus.textContent = '';
            updateSubmitState();
            return;
        }
        resolving = true;
        modalResolveStatus.textContent = 'Resolent…';
        videoAddError.textContent = '';

        var body = new FormData();
        body.append('vimeo_input', input);

        fetch('?action=continguts-resolve-vimeo', { method: 'POST', body: body })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.data.ok) {
                    modalVimeoId.value = '';
                    vimeoPreview.hidden = true;
                    modalResolveStatus.textContent = '';
                    videoAddError.textContent = res.data.error || 'No s\'ha pogut resoldre el vídeo.';
                    updateSubmitState();
                    return;
                }
                modalVimeoId.value = res.data.vimeo_id;
                modalResolveStatus.textContent = 'ID: ' + res.data.vimeo_id;
                showVimeoPreview(res.data.thumbnail_url || null, res.data.title || '');
            })
            .catch(function () {
                videoAddError.textContent = 'Error de connexió.';
            })
            .finally(function () { resolving = false; });
    }

    function scheduleResolve() {
        clearTimeout(resolveTimer);
        resolveTimer = setTimeout(resolveVimeoInput, 300);
    }

    videoAddTrigger.addEventListener('click', openVideoModal);
    videoAddCancel.addEventListener('click', closeVideoModal);
    videoModal.addEventListener('click', function (e) {
        if (e.target === videoModal) closeVideoModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !videoModal.hidden) closeVideoModal();
    });

    modalVimeoInput.addEventListener('blur', resolveVimeoInput);
    modalVimeoInput.addEventListener('paste', function () { scheduleResolve(); });
    modalVimeoInput.addEventListener('input', function () {
        if (modalVimeoId.value) {
            modalVimeoId.value = '';
            vimeoPreview.hidden = true;
            modalResolveStatus.textContent = '';
            updateSubmitState();
        }
    });
    modalVideoTitle.addEventListener('input', updateSubmitState);
    modalSignLanguage.addEventListener('change', updateSubmitState);
    modalEdition.addEventListener('change', updateSubmitState);

    setupModalConfigAdd({
        select: modalSignLanguage,
        panelId: 'modal-lang-new-panel',
        errorId: 'modal-lang-add-error',
        addBtnId: 'modal-lang-add-btn',
        cancelBtnId: 'modal-lang-cancel-btn',
        previewLabelId: 'modal-lang-preview-label',
        previewIdId: 'modal-lang-preview-id',
        codeInputId: 'modal-sl-code',
        qualifierInputId: 'modal-sl-qualifier',
        action: 'add-sign-language',
        buildBody: function () {
            var body = new FormData();
            body.append('sign_language_code', document.getElementById('modal-sl-code').value.trim());
            body.append('sign_language_qualifier', document.getElementById('modal-sl-qualifier').value.trim());
            return body;
        },
        validate: function () {
            return document.getElementById('modal-sl-code').value.trim() !== '' &&
                document.getElementById('modal-sl-qualifier').value.trim() !== '';
        },
        onAdded: function (data) {
            appendSelectOption(modalSignLanguage, data.id, data.label);
            appendConfigListEntry('#tab-languages .config-list', data.id, data.label, 'sign-language');
            updateSubmitState();
        },
        buildPreview: function () {
            var code = document.getElementById('modal-sl-code').value.trim();
            var qualifier = document.getElementById('modal-sl-qualifier').value.trim();
            if (!code || !qualifier) {
                document.getElementById('modal-lang-preview-label').textContent = '—';
                document.getElementById('modal-lang-preview-id').textContent = '—';
                return;
            }
            document.getElementById('modal-lang-preview-label').textContent = code + ' ' + qualifier + ' Sign Language';
            document.getElementById('modal-lang-preview-id').textContent = slugify(code);
        },
        clearInputs: function () {
            document.getElementById('modal-sl-code').value = '';
            document.getElementById('modal-sl-qualifier').value = '';
        },
        focusInput: function () { document.getElementById('modal-sl-code').focus(); }
    });

    setupModalConfigAdd({
        select: modalEdition,
        panelId: 'modal-edition-new-panel',
        errorId: 'modal-edition-add-error',
        addBtnId: 'modal-edition-add-btn',
        cancelBtnId: 'modal-edition-cancel-btn',
        previewLabelId: 'modal-edition-preview-label',
        previewIdId: 'modal-edition-preview-id',
        codeInputId: 'modal-edition-city',
        qualifierInputId: 'modal-edition-year',
        action: 'add-edition',
        buildBody: function () {
            var body = new FormData();
            body.append('edition_city', document.getElementById('modal-edition-city').value.trim());
            body.append('edition_year', document.getElementById('modal-edition-year').value.trim());
            return body;
        },
        validate: function () {
            var city = document.getElementById('modal-edition-city').value.trim();
            var year = document.getElementById('modal-edition-year').value.trim();
            return city !== '' && /^\d{4}$/.test(year);
        },
        onAdded: function (data) {
            EDITION_LABELS[data.id] = data.label;
            appendSelectOption(modalEdition, data.id, data.label);
            appendConfigListEntry('#tab-editions .config-list', data.id, data.label, 'edition');
            updateSubmitState();
        },
        buildPreview: function () {
            var city = document.getElementById('modal-edition-city').value.trim();
            var year = document.getElementById('modal-edition-year').value.trim();
            if (!city || !/^\d{4}$/.test(year)) {
                document.getElementById('modal-edition-preview-label').textContent = '—';
                document.getElementById('modal-edition-preview-id').textContent = '—';
                return;
            }
            document.getElementById('modal-edition-preview-label').textContent = city + ' ' + year;
            document.getElementById('modal-edition-preview-id').textContent = year + '-' + slugify(city);
        },
        clearInputs: function () {
            document.getElementById('modal-edition-city').value = '';
            document.getElementById('modal-edition-year').value = '';
        },
        focusInput: function () { document.getElementById('modal-edition-city').focus(); }
    });

    videoAddSubmit.addEventListener('click', function () {
        if (videoAddSubmit.disabled || resolving) return;
        videoAddError.textContent = '';
        videoAddSubmit.disabled = true;

        var body = new FormData();
        body.append('vimeo_id', modalVimeoId.value);
        body.append('sign_language', modalSignLanguage.value);
        body.append('edition', modalEdition.value);
        body.append('title', modalVideoTitle.value.trim());

        fetch('?action=continguts-add-video', { method: 'POST', body: body })
            .then(function (r) { return r.json().then(function (d) { return { data: d, ok: r.ok }; }); })
            .then(function (res) {
                if (!res.data.ok) {
                    videoAddError.textContent = res.data.error || 'No s\'ha pogut afegir el vídeo.';
                    updateSubmitState();
                    return;
                }
                injectVideoCard(res.data.video, res.data.edition_label || res.data.video.edition);
                closeVideoModal();
            })
            .catch(function () {
                videoAddError.textContent = 'Error de connexió.';
                updateSubmitState();
            });
    });

    function appendSelectOption(select, id, label) {
        var newOpt = document.createElement('option');
        newOpt.value = id;
        newOpt.textContent = label;
        var last = select.querySelector('option[value="__new__"]');
        select.insertBefore(newOpt, last);
        select.value = id;
    }

    function appendConfigListEntry(listSelector, id, label, type) {
        var list = document.querySelector(listSelector);
        if (!list) return;
        var div = document.createElement('div');
        div.className = 'config-entry';
        div.dataset.id = id;
        div.dataset.type = type;
        div.innerHTML = '<span class="config-entry-label">' + escHtml(label) + '</span>' +
            '<input class="inline-label-input" type="text" value="' + escHtml(label) + '">' +
            '<span class="config-id">' + escHtml(id) + '</span>' +
            '<button class="btn-icon edit-btn" title="Edita">✏️</button>' +
            '<button class="btn-icon confirm-btn" title="Desa" style="display:none">✓</button>' +
            '<button class="btn-icon danger delete-btn" title="Elimina">🗑</button>';
        list.appendChild(div);
        attachConfigEntryListeners(div);
    }

    function injectVideoCard(video, editionLabel) {
        videosEmptyMsg.style.display = 'none';
        var editionId = video.edition;
        var group = videosCatalog.querySelector('.edition-group[data-edition-id="' + CSS.escape(editionId) + '"]');

        if (!group) {
            group = document.createElement('div');
            group.className = 'edition-group open';
            group.dataset.editionId = editionId;
            group.innerHTML =
                '<button type="button" class="edition-heading" aria-expanded="true">' +
                    escHtml(editionLabel || EDITION_LABELS[editionId] || editionId) +
                    '<span class="edition-count">0 vídeos</span>' +
                    '<span class="edition-chevron" aria-hidden="true">▼</span>' +
                '</button>' +
                '<div class="edition-videos"></div>';
            videosCatalog.appendChild(group);
            attachEditionGroupListeners(group);
        }

        var grid = group.querySelector('.edition-videos');
        var card = document.createElement('a');
        card.className = 'video-card';
        card.href = '?action=continguts-video&vimeo_id=' + encodeURIComponent(video.vimeo_id);
        var captionCount = (video.captions || []).length;
        var captionLabel = captionCount === 1 ? '1 subtítol' : captionCount + ' subtítols';
        var metaHtml =
            '<div class="video-card-meta">' +
                '<span class="video-card-title">' + escHtml(video.title) + '</span>' +
                '<span class="video-caption-count" title="' + escHtml(captionLabel) + '">' + captionCount + '</span>' +
            '</div>';
        if (video.thumbnail_url) {
            card.innerHTML = '<img class="video-thumb" src="' + escHtml(video.thumbnail_url) + '" alt="" loading="lazy">' + metaHtml;
        } else {
            card.innerHTML = '<div class="video-thumb-placeholder"></div>' + metaHtml;
        }
        grid.appendChild(card);

        var countEl = group.querySelector('.edition-count');
        countEl.textContent = formatEditionVideoCount(grid.querySelectorAll('.video-card').length);
        group.classList.add('open');
        group.querySelector('.edition-heading').setAttribute('aria-expanded', 'true');
    }

    function setupModalConfigAdd(cfg) {
        var panel = document.getElementById(cfg.panelId);
        var addError = document.getElementById(cfg.errorId);
        var addBtn = document.getElementById(cfg.addBtnId);
        var cancelBtn = document.getElementById(cfg.cancelBtnId);

        function setPanelOpen(open) {
            panel.classList.toggle('is-open', open);
            if (!open) addError.textContent = '';
        }

        function closePanel() {
            if (cfg.select.value === '__new__') cfg.select.value = '';
            cfg.clearInputs();
            cfg.buildPreview();
            setPanelOpen(false);
            updateSubmitState();
        }

        cfg.select.addEventListener('change', function () {
            if (cfg.select.value === '__new__') {
                setPanelOpen(true);
                cfg.focusInput();
            } else {
                setPanelOpen(false);
            }
            updateSubmitState();
        });

        cancelBtn.addEventListener('click', closePanel);

        document.getElementById(cfg.codeInputId).addEventListener('input', cfg.buildPreview);
        if (cfg.qualifierInputId) {
            document.getElementById(cfg.qualifierInputId).addEventListener('input', cfg.buildPreview);
        }

        addBtn.addEventListener('click', function () {
            addError.textContent = '';
            if (!cfg.validate()) {
                addError.textContent = 'Completeu tots els camps.';
                return;
            }
            addBtn.disabled = true;
            fetch('?action=' + cfg.action, { method: 'POST', body: cfg.buildBody() })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        addError.textContent = (data.errors && data.errors[0]) || 'No s\'ha pogut afegir.';
                        return;
                    }
                    cfg.onAdded(data);
                    closePanel();
                })
                .catch(function () { addError.textContent = 'Error de connexió.'; })
                .finally(function () { addBtn.disabled = false; });
        });
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
            var action = configAction(type, 'save');
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
            var action = configAction(type, 'delete');
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

    // ── Add subtitle language ─────────────────────────────────────────────────
    var subtitleLangTrigger = document.getElementById('subtitle-lang-add-trigger');
    var subtitleLangPanel = document.getElementById('subtitle-lang-new-panel');
    var oralCode = document.getElementById('oral_code_c');
    var oralName = document.getElementById('oral_name_c');
    var subtitleLangPreviewLabel = document.getElementById('subtitle-lang-preview-label-c');
    var subtitleLangPreviewId = document.getElementById('subtitle-lang-preview-id-c');
    var subtitleLangAddError = document.getElementById('subtitle-lang-add-error');
    var subtitleLangAddBtn = document.getElementById('subtitle-lang-add-btn-c');
    var subtitleLangCancelBtn = document.getElementById('subtitle-lang-cancel-btn-c');

    subtitleLangTrigger.addEventListener('click', function () {
        subtitleLangPanel.classList.add('is-open');
        oralCode.focus();
    });
    subtitleLangCancelBtn.addEventListener('click', function () {
        subtitleLangPanel.classList.remove('is-open');
        oralCode.value = '';
        oralName.value = '';
        subtitleLangPreviewLabel.textContent = '—';
        subtitleLangPreviewId.textContent = '—';
        subtitleLangAddError.textContent = '';
    });

    function updateSubtitleLangPreview() {
        var code = oralCode.value.trim();
        var name = oralName.value.trim();
        if (!code || !name) {
            subtitleLangPreviewLabel.textContent = '—';
            subtitleLangPreviewId.textContent = '—';
            return;
        }
        subtitleLangPreviewLabel.textContent = name;
        subtitleLangPreviewId.textContent = slugify(code);
    }
    oralCode.addEventListener('input', updateSubtitleLangPreview);
    oralName.addEventListener('input', updateSubtitleLangPreview);

    subtitleLangAddBtn.addEventListener('click', function () {
        subtitleLangAddError.textContent = '';
        var code = oralCode.value.trim();
        var name = oralName.value.trim();
        if (!code || !name) {
            subtitleLangAddError.textContent = 'Indiqueu un codi i un nom.';
            return;
        }
        subtitleLangAddBtn.disabled = true;
        var body = new FormData();
        body.append('subtitle_language_code', code);
        body.append('subtitle_language_name', name);
        fetch('?action=add-subtitle-language', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    subtitleLangAddError.textContent = (data.errors && data.errors[0]) || 'No s\'ha pogut afegir la llengua oral.';
                    return;
                }
                var list = document.querySelector('#tab-subtitle-languages .config-list');
                var div = document.createElement('div');
                div.className = 'config-entry';
                div.dataset.id = data.id;
                div.dataset.type = 'subtitle-language';
                div.innerHTML = '<span class="config-entry-label">' + escHtml(data.label) + '</span>' +
                    '<input class="inline-label-input" type="text" value="' + escHtml(data.label) + '">' +
                    '<span class="config-id">' + escHtml(data.id) + '</span>' +
                    '<button class="btn-icon edit-btn" title="Edita">✏️</button>' +
                    '<button class="btn-icon confirm-btn" title="Desa" style="display:none">✓</button>' +
                    '<button class="btn-icon danger delete-btn" title="Elimina">🗑</button>';
                list.appendChild(div);
                attachConfigEntryListeners(div);
                subtitleLangCancelBtn.click();
            })
            .catch(function () { subtitleLangAddError.textContent = 'Error de connexió.'; })
            .finally(function () { subtitleLangAddBtn.disabled = false; });
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
            var action = configAction(type, 'save');
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
                var delAction = configAction(type, 'delete');
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

    function formatEditionVideoCount(count) {
        return count + (count === 1 ? ' vídeo' : ' vídeos');
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}());
</script>
</body>
</html>
