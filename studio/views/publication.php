<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publicació — Studio</title>
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
        .step-header p { color: #777; font-size: 0.85rem; }
        .summary-card {
            background: #111;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .summary-row {
            display: flex;
            gap: 1rem;
            padding: 0.45rem 0;
            border-bottom: 1px solid #1a1a1a;
            font-size: 0.875rem;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row dt {
            color: #666;
            flex: 0 0 9rem;
            font-weight: 400;
        }
        .summary-row dd { color: #ccc; }
        .warning-banner {
            background: #2a1f00;
            border: 1px solid #5c4000;
            border-radius: 5px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            color: #ffca60;
            font-size: 0.875rem;
        }
        .error-banner {
            background: #2a0a0a;
            border: 1px solid #6a2020;
            border-radius: 5px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            color: #ff8080;
            font-size: 0.875rem;
        }
        .warning-banner p { margin-bottom: 0.4rem; }
        .warning-banner ul { padding-left: 1.2rem; margin-top: 0.3rem; }
        .actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .btn-publish {
            background: #1a3a1a;
            border: 1px solid #2a6a2a;
            border-radius: 5px;
            color: #80e080;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 0.7rem 1.75rem;
            cursor: pointer;
            letter-spacing: 0.04em;
        }
        .btn-publish:hover { background: #1f461f; }
        a.home-link {
            color: #555;
            font-size: 0.85rem;
            text-decoration: none;
        }
        a.home-link:hover { color: #888; }
    </style>
</head>
<body>
<header>
    <h1>Studio — Publicació</h1>
    <nav>
        <a href="./" class="nav-link">Inici</a>
        <a href="?action=logout" class="nav-link">Sortir</a>
    </nav>
</header>
<main>
    <div class="step-header">
        <h2>Confirma i publica</h2>
        <p>Revisa el resum del vídeo. Quan estiguis preparat/da, fes clic a <strong>Publicar</strong>.</p>
    </div>

    <?php if (!empty($publicationError)): ?>
    <div class="error-banner">
        <p><?= htmlspecialchars($publicationError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($vimeoWarnings)): ?>
    <div class="warning-banner">
        <p>El vídeo s'ha publicat al catàleg, però han fallat algunes pujades a Vimeo:</p>
        <ul>
            <?php foreach ($vimeoWarnings as $w): ?>
            <li><?= htmlspecialchars($w, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <dl class="summary-card">
        <div class="summary-row">
            <dt>Títol</dt>
            <dd><?= htmlspecialchars($summaryTitle, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="summary-row">
            <dt>Llengua de signes</dt>
            <dd><?= htmlspecialchars($summarySignLanguage, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="summary-row">
            <dt>Ciutat</dt>
            <dd><?= htmlspecialchars($summaryEdition, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="summary-row">
            <dt>Etiquetes</dt>
            <dd><?= htmlspecialchars($summaryTags ?: '—', ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="summary-row">
            <dt>Subtítols</dt>
            <dd><?= htmlspecialchars($summaryCaptions ?: '—', ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
    </dl>

    <?php if (empty($vimeoWarnings) && empty($publicationError)): ?>
    <div class="actions">
        <form method="POST" style="display:inline;">
            <button type="submit" class="btn-publish">Publicar</button>
        </form>
    </div>
    <?php elseif (!empty($vimeoWarnings)): ?>
    <div class="actions">
        <a href="./" class="home-link">Torna a l'inici</a>
    </div>
    <?php else: ?>
    <div class="actions">
        <form method="POST" style="display:inline;">
            <button type="submit" class="btn-publish">Publicar</button>
        </form>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
