<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova feina — Studio</title>
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
        a.back, a.logout {
            font-size: 0.8rem;
            color: #555;
            text-decoration: none;
            letter-spacing: 0.05em;
        }
        a.back:hover, a.logout:hover { color: #999; }
        main {
            max-width: 560px;
            padding: 2.5rem 2rem 4rem;
        }
        h2 {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        p.lead {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        label {
            display: block;
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 0.35rem;
            letter-spacing: 0.03em;
        }
        .field {
            margin-bottom: 1.25rem;
        }
        input[type="text"],
        input[type="url"],
        select,
        input[type="file"] {
            display: block;
            width: 100%;
            padding: 0.65rem 0.75rem;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 0.95rem;
            outline: none;
        }
        input:focus, select:focus { border-color: #555; }
        .error {
            font-size: 0.82rem;
            color: #e05555;
            margin-top: 0.35rem;
        }
        .form-error {
            margin-bottom: 1.25rem;
            padding: 0.75rem;
            background: #1a1010;
            border: 1px solid #3a2020;
            border-radius: 4px;
            color: #e05555;
            font-size: 0.85rem;
        }
        button[type="submit"] {
            margin-top: 0.5rem;
            padding: 0.7rem 1.5rem;
            background: #e0e0e0;
            color: #0a0a0a;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
        }
        button[type="submit"]:hover { background: #fff; }
        .edition-new-panel {
            display: none;
            margin-top: 0.75rem;
            padding: 1rem;
            background: #111;
            border: 1px solid #2a2a2a;
            border-radius: 5px;
        }
        .edition-new-panel.is-open { display: block; }
        .edition-new-panel h3 {
            font-size: 0.85rem;
            font-weight: 500;
            color: #aaa;
            margin-bottom: 0.85rem;
        }
        .edition-new-grid {
            display: grid;
            grid-template-columns: 1fr 6rem;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .edition-preview {
            font-size: 0.8rem;
            color: #666;
            line-height: 1.5;
            margin-bottom: 0.85rem;
        }
        .edition-preview strong { color: #999; font-weight: 500; }
        .edition-preview .value { color: #bbb; }
        .edition-new-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .btn-secondary {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 4px;
            color: #aaa;
            font-size: 0.85rem;
            padding: 0.55rem 0.9rem;
            cursor: pointer;
        }
        .btn-secondary:hover { background: #222; color: #ddd; }
        .btn-link {
            background: none;
            border: none;
            color: #555;
            font-size: 0.8rem;
            padding: 0.55rem 0.5rem;
            cursor: pointer;
            text-decoration: underline;
        }
        .btn-link:hover { color: #888; }
        .edition-add-error {
            font-size: 0.82rem;
            color: #e05555;
            margin-bottom: 0.65rem;
        }
        .edition-add-error:empty { display: none; }
        .edition-new-hint {
            font-size: 0.78rem;
            color: #555;
            margin-top: 0.65rem;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <header>
        <h1>Studio</h1>
        <a class="logout" href="?action=logout">Tanca la sessió</a>
    </header>
    <main>
        <h2>Nova feina</h2>
        <p class="lead">Enganxeu la URL o l'ID de Vimeo d'un vídeo que ja sigui al vostre compte, trieu les metadades i pugeu un fitxer WebVTT o l'àudio de l'intèrpret.</p>

        <?php if (!empty($errors['_form'])): ?>
            <p class="form-error"><?= htmlspecialchars($errors['_form']) ?></p>
        <?php endif; ?>

        <form method="POST" action="?action=intake" enctype="multipart/form-data">
            <div class="field">
                <label for="vimeo_input">URL o ID de Vimeo (exemple: 639494119)</label>
                <input type="text" id="vimeo_input" name="vimeo_input" value="<?= htmlspecialchars($values['vimeo_input'] ?? '') ?>" required>
                <?php if (!empty($errors['vimeo_input'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['vimeo_input']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="sign_language">Llengua de signes</label>
                <select id="sign_language" name="sign_language" required>
                    <option value="">Seleccioneu…</option>
                    <?php foreach ($signLanguages as $option): ?>
                        <option value="<?= htmlspecialchars($option['id']) ?>" <?= ($values['sign_language'] ?? '') === $option['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['sign_language'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['sign_language']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="edition">Edició</label>
                <select id="edition" name="edition" required>
                    <option value="">Seleccioneu…</option>
                    <?php foreach ($editions as $option): ?>
                        <option value="<?= htmlspecialchars($option['id']) ?>" <?= ($values['edition'] ?? '') === $option['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__new__" <?= ($values['edition'] ?? '') === '__new__' ? 'selected' : '' ?>>+ Afegiu una edició…</option>
                </select>

                <div class="edition-new-panel<?= ($values['edition'] ?? '') === '__new__' ? ' is-open' : '' ?>" id="edition-new-panel" aria-hidden="<?= ($values['edition'] ?? '') === '__new__' ? 'false' : 'true' ?>">
                    <h3>Nova edició</h3>
                    <p class="edition-add-error" id="edition-add-error" role="alert"></p>
                    <div class="edition-new-grid">
                        <div>
                            <label for="edition_city">Ciutat</label>
                            <input type="text" id="edition_city" name="edition_city" autocomplete="off" placeholder="p. ex. Lisboa">
                        </div>
                        <div>
                            <label for="edition_year">Any</label>
                            <input type="text" id="edition_year" name="edition_year" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="off" placeholder="2027">
                        </div>
                    </div>
                    <p class="edition-preview">
                        <strong>Nom:</strong> <span class="value" id="edition-preview-label">—</span><br>
                        <strong>Identificador:</strong> <span class="value" id="edition-preview-id">—</span>
                    </p>
                    <div class="edition-new-actions">
                        <button type="button" class="btn-secondary" id="edition-add-btn">Afegir a la llista</button>
                        <button type="button" class="btn-link" id="edition-cancel-btn">Cancel·la</button>
                    </div>
                    <p class="edition-new-hint">Es desarà a la llista d'edicions per a aquest i futurs vídeos.</p>
                </div>

                <?php if (!empty($errors['edition'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['edition']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="subtitle_language">Llengua dels subtítols</label>
                <select id="subtitle_language" name="subtitle_language" required>
                    <option value="">Seleccioneu…</option>
                    <?php foreach ($subtitleLanguages as $option): ?>
                        <option value="<?= htmlspecialchars($option['id']) ?>" <?= ($values['subtitle_language'] ?? '') === $option['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['subtitle_language'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['subtitle_language']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="intake_file">Fitxer de subtítols o àudio de l'intèrpret</label>
                <input type="file" id="intake_file" name="intake_file" accept=".vtt,text/vtt,audio/*,.mp3,.wav,.m4a,.aac,.ogg,.flac,.webm" required>
                <?php if (!empty($errors['intake_file'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['intake_file']) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit">Crea la feina</button>
        </form>
        <p style="margin-top: 1.5rem;"><a class="back" href="./">← Torna a l'estudi</a></p>
    </main>
    <script>
    (function () {
        var editionSelect = document.getElementById('edition');
        var panel = document.getElementById('edition-new-panel');
        var cityInput = document.getElementById('edition_city');
        var yearInput = document.getElementById('edition_year');
        var previewLabel = document.getElementById('edition-preview-label');
        var previewId = document.getElementById('edition-preview-id');
        var addBtn = document.getElementById('edition-add-btn');
        var cancelBtn = document.getElementById('edition-cancel-btn');
        var addError = document.getElementById('edition-add-error');
        var intakeForm = editionSelect.closest('form');

        function slugifyCity(city) {
            var normalized = city.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            return normalized.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }

        function buildPreview() {
            var city = cityInput.value.trim();
            var year = yearInput.value.trim();
            if (city === '' || !/^\d{4}$/.test(year)) {
                previewLabel.textContent = '—';
                previewId.textContent = '—';
                return;
            }
            var slug = slugifyCity(city);
            if (slug === '') {
                previewLabel.textContent = '—';
                previewId.textContent = '—';
                return;
            }
            previewLabel.textContent = city + ' ' + year;
            previewId.textContent = year + '-' + slug;
        }

        function setPanelOpen(open) {
            panel.classList.toggle('is-open', open);
            panel.setAttribute('aria-hidden', open ? 'false' : 'true');
            editionSelect.required = !open;
            if (!open) {
                addError.textContent = '';
            }
        }

        function closeNewEditionPanel() {
            if (editionSelect.value === '__new__') {
                editionSelect.value = '';
            }
            cityInput.value = '';
            yearInput.value = '';
            buildPreview();
            setPanelOpen(false);
        }

        editionSelect.addEventListener('change', function () {
            if (editionSelect.value === '__new__') {
                setPanelOpen(true);
                cityInput.focus();
            } else {
                setPanelOpen(false);
            }
        });

        cityInput.addEventListener('input', buildPreview);
        yearInput.addEventListener('input', buildPreview);

        cancelBtn.addEventListener('click', closeNewEditionPanel);

        addBtn.addEventListener('click', function () {
            addError.textContent = '';
            var city = cityInput.value.trim();
            var year = yearInput.value.trim();
            if (city === '' || !/^\d{4}$/.test(year)) {
                addError.textContent = 'Indiqueu una ciutat i un any de quatre xifres.';
                return;
            }

            addBtn.disabled = true;
            var body = new FormData();
            body.append('edition_city', city);
            body.append('edition_year', year);

            fetch('?action=add-edition', { method: 'POST', body: body })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        addError.textContent = (data.errors && data.errors[0]) || 'No s\'ha pogut afegir l\'edició.';
                        return;
                    }
                    var opt = document.createElement('option');
                    opt.value = data.id;
                    opt.textContent = data.label;
                    editionSelect.insertBefore(opt, editionSelect.querySelector('option[value="__new__"]'));
                    editionSelect.value = data.id;
                    cityInput.value = '';
                    yearInput.value = '';
                    buildPreview();
                    setPanelOpen(false);
                })
                .catch(function () {
                    addError.textContent = 'No s\'ha pogut afegir l\'edició.';
                })
                .finally(function () {
                    addBtn.disabled = false;
                });
        });

        intakeForm.addEventListener('submit', function (e) {
            if (editionSelect.value === '__new__') {
                e.preventDefault();
                addError.textContent = 'Afegiu l\'edició a la llista abans de crear la feina.';
                setPanelOpen(true);
            }
        });

        if (editionSelect.value === '__new__') {
            setPanelOpen(true);
            buildPreview();
        }
    })();
    </script>
</body>
</html>
