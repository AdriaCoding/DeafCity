<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetatge — Studio</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0a0a0a;
            color: #e0e0e0;
            font-family: system-ui, sans-serif;
            min-height: 100vh;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #1e1e1e;
        }
        h1 {
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #888;
        }
        a.nav-link {
            font-size: 0.8rem;
            color: #555;
            text-decoration: none;
            margin-left: 1rem;
        }
        a.nav-link:hover { color: #999; }
        main {
            max-width: 42rem;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        .step-header { margin-bottom: 1.5rem; }
        .step-header h2 {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.35rem;
        }
        .step-header p {
            color: #777;
            font-size: 0.85rem;
        }
        .error-banner {
            background: #2a1010;
            border: 1px solid #5c1a1a;
            border-radius: 5px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            color: #f08080;
            font-size: 0.875rem;
        }
        .tag-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            max-height: 22rem;
            overflow-y: auto;
            background: #111;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 0.75rem 1rem;
        }
        .tag-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.9rem;
            cursor: pointer;
            color: #ccc;
        }
        .tag-item input[type="checkbox"] {
            accent-color: #5c9aff;
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
        }
        .tag-list:empty::after {
            content: 'Encara no hi ha etiquetes al catàleg.';
            color: #555;
            font-size: 0.85rem;
            font-style: italic;
        }
        .new-tag-row {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .new-tag-row input[type="text"] {
            flex: 1;
            background: #141414;
            border: 1px solid #2e2e2e;
            border-radius: 5px;
            padding: 0.5rem 0.75rem;
            color: #e0e0e0;
            font-size: 0.875rem;
        }
        .new-tag-row input[type="text"]::placeholder { color: #555; }
        .new-tag-row input[type="text"]:focus {
            outline: none;
            border-color: #444;
        }
        .btn-secondary {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #aaa;
            font-size: 0.8rem;
            padding: 0.5rem 0.85rem;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-secondary:hover { background: #222; color: #ddd; }
        .actions { display: flex; gap: 0.75rem; }
        .btn-primary {
            background: #1a3a6e;
            border: 1px solid #2a5090;
            border-radius: 5px;
            color: #9ab8ff;
            font-size: 0.875rem;
            padding: 0.6rem 1.25rem;
            cursor: pointer;
        }
        .btn-primary:hover { background: #1f4580; }
    </style>
</head>
<body>
<header>
    <h1>Studio — Etiquetatge</h1>
    <nav>
        <a href="./" class="nav-link">Inici</a>
        <a href="?action=logout" class="nav-link">Sortir</a>
    </nav>
</header>
<main>
    <div class="step-header">
        <h2>Etiquetes del vídeo</h2>
        <p>Selecciona etiquetes existents o afegeix-ne de noves. Cal almenys una etiqueta per continuar.</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="error-banner">
        <?php foreach ($errors as $err): ?>
        <p><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="tagging-form">
        <div class="tag-list" id="tag-list">
            <?php foreach ($catalogTags as $tag): ?>
            <label class="tag-item">
                <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>"<?= in_array($tag, $jobTags, true) ? ' checked' : '' ?>>
                <?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="new-tag-row">
            <input type="text" id="new-tag-input" placeholder="Nova etiqueta…" autocomplete="off">
            <button type="button" id="add-tag-btn" class="btn-secondary">Afegeix</button>
        </div>

        <div class="actions">
            <button type="submit" class="btn-primary">Desa i continua</button>
        </div>
    </form>
</main>
<script>
(function () {
    function addTag() {
        var input = document.getElementById('new-tag-input');
        var val = input.value.trim();
        if (!val) return;

        var checkboxes = document.querySelectorAll('#tag-list input[type="checkbox"]');
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].value === val) {
                checkboxes[i].checked = true;
                input.value = '';
                return;
            }
        }

        var label = document.createElement('label');
        label.className = 'tag-item';
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.name = 'tags[]';
        cb.value = val;
        cb.checked = true;
        label.appendChild(cb);
        label.appendChild(document.createTextNode(' ' + val));
        document.getElementById('tag-list').appendChild(label);
        input.value = '';
    }

    document.getElementById('add-tag-btn').addEventListener('click', addTag);
    document.getElementById('new-tag-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTag();
        }
    });
}());
</script>
</body>
</html>
