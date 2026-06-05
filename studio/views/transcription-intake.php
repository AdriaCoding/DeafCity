<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova transcripció — Studio</title>
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
        a.logout {
            font-size: 0.8rem;
            color: #555;
            text-decoration: none;
            letter-spacing: 0.05em;
        }
        a.logout:hover { color: #999; }
        main {
            max-width: 520px;
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
        .field { margin-bottom: 1.25rem; }
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
        select:focus, input:focus { border-color: #555; }
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
            background: #2a6040;
            color: #7ed87e;
            border: 1px solid #3a8050;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
        }
        button[type="submit"]:hover { background: #336b49; }
        a.back {
            font-size: 0.8rem;
            color: #555;
            text-decoration: none;
        }
        a.back:hover { color: #999; }
    </style>
</head>
<body>
    <header>
        <h1>Studio</h1>
        <a class="logout" href="?action=logout">Tanca la sessió</a>
    </header>
    <main>
        <h2>Nova transcripció</h2>
        <p class="lead">Pugeu l'àudio de l'intèrpret. El sistema el transcriurà i el traduirà automàticament a l'anglès, i descarregarà els dos fitxers de subtítols quan estigui llest.</p>

        <?php if (!empty($errors['_form'])): ?>
            <p class="form-error"><?= htmlspecialchars($errors['_form']) ?></p>
        <?php endif; ?>

        <form method="POST" action="?action=transcription-intake" enctype="multipart/form-data">
            <div class="field">
                <label for="subtitle_language">Llengua de l'àudio</label>
                <select id="subtitle_language" name="subtitle_language" required>
                    <option value="">Seleccioneu…</option>
                    <?php foreach ($subtitleLanguages as $option): ?>
                        <option value="<?= htmlspecialchars($option['id']) ?>"
                            <?= ($values['subtitle_language'] ?? '') === $option['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['subtitle_language'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['subtitle_language']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="intake_file">Fitxer d'àudio de l'intèrpret</label>
                <input type="file" id="intake_file" name="intake_file"
                    accept="audio/*,.mp3,.wav,.m4a,.aac,.ogg,.flac,.webm" required>
                <?php if (!empty($errors['intake_file'])): ?>
                    <p class="error"><?= htmlspecialchars($errors['intake_file']) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit">Comença la transcripció</button>
        </form>
        <p style="margin-top: 1.5rem;"><a class="back" href="./">← Torna a l'estudi</a></p>
    </main>
</body>
</html>
