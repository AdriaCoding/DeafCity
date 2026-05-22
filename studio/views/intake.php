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
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #ccc;
            cursor: pointer;
        }
        .radio-option input[type="radio"] {
            width: auto;
            margin: 0;
            accent-color: #e0e0e0;
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
    </style>
</head>
<body>
    <header>
        <h1>Studio</h1>
        <a class="logout" href="?action=logout">Tanca la sessió</a>
    </header>
    <main>
        <h2>Nova feina</h2>
        <p class="lead">Enganxeu la URL o l'ID de Vimeo d'un vídeo que ja sigui al vostre compte, trieu les metadades i pugeu el fitxer de subtítols o l'àudio de l'intèrpret.</p>

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
                </select>
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
                <label>Font dels subtítols</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="intake_mode" value="upload" <?= ($values['intake_mode'] ?? 'upload') === 'upload' ? 'checked' : '' ?>>
                        Pujar fitxer WebVTT
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="intake_mode" value="generate" <?= ($values['intake_mode'] ?? 'upload') === 'generate' ? 'checked' : '' ?>>
                        Generar des de l'àudio de l'intèrpret
                    </label>
                </div>
            </div>

            <div class="field" id="field-subtitle-file">
                <label for="subtitle_file">Fitxer de subtítols (WebVTT)</label>
                <input type="file" id="subtitle_file" name="subtitle_file" accept=".vtt,text/vtt">
                <?php if (!empty($errors['subtitle_file'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['subtitle_file']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field" id="field-interpreter-audio" style="display:none">
                <label for="interpreter_audio">Àudio de l'intèrpret</label>
                <input type="file" id="interpreter_audio" name="interpreter_audio">
                <?php if (!empty($errors['interpreter_audio'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['interpreter_audio']) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit">Crea la feina</button>

            <script>
                (function () {
                    var radios = document.querySelectorAll('input[name="intake_mode"]');
                    var vttField = document.getElementById('field-subtitle-file');
                    var audioField = document.getElementById('field-interpreter-audio');

                    function toggle() {
                        var mode = document.querySelector('input[name="intake_mode"]:checked').value;
                        vttField.style.display = mode === 'upload' ? '' : 'none';
                        audioField.style.display = mode === 'generate' ? '' : 'none';
                    }

                    radios.forEach(function (r) { r.addEventListener('change', toggle); });
                    toggle();
                }());
            </script>
        </form>
        <p style="margin-top: 1.5rem;"><a class="back" href="./">← Torna a l'estudi</a></p>
    </main>
</body>
</html>
