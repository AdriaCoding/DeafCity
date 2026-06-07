<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traducció — Studio</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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
        .job-title {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .intro {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        .lang-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        .lang-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #141414;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 1rem 1.25rem;
        }
.lang-label { font-size: 1rem; }
        .badge {
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            border: 1px solid transparent;
        }
        .badge-generated { color: #7ed87e; border-color: #2a4a2a; background: #101810; }
        .badge-reviewed { color: #7eb8d8; border-color: #2a3a4a; background: #101418; }
        .badge-error { color: #e05555; border-color: #4a2020; background: #1a1010; }
        .badge-pending { color: #888; border-color: #333; background: #141414; }
        .retry-btn {
            background: transparent;
            color: #aaa;
            border: 1px solid #444;
            border-radius: 4px;
            padding: 0.45rem 0.75rem;
            font-size: 0.78rem;
            cursor: pointer;
        }
        .retry-btn:hover { color: #fff; border-color: #666; }
        .actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .btn-primary {
            display: inline-block;
            padding: 0.75rem 1.4rem;
            background: #e0e0e0;
            color: #0a0a0a;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary:hover { background: #fff; }
        .download-btns {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        a.download-btn {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.3rem 0.65rem;
            background: transparent;
            color: #555;
            border: 1px solid #2a2a2a;
            border-radius: 4px;
            font-size: 0.75rem;
            text-decoration: none;
            letter-spacing: 0.04em;
        }
        a.download-btn:hover { color: #aaa; border-color: #555; }
        a.download-btn .material-icons { font-size: 0.95rem; }
        .lang-card-original {
            border-color: #1e1e1e;
        }
        .orig-lang-label {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .badge-original {
            font-size: 0.68rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            border: 1px solid #333;
            color: #666;
            background: #141414;
        }
    </style>
</head>
<body>
    <header>
        <h1>Traducció</h1>
        <div>
            <a class="nav-link" href="./">← Estudi</a>
            <a class="nav-link" href="?action=logout">Tanca la sessió</a>
        </div>
    </header>
    <main>
        <h2 class="job-title"><?= htmlspecialchars($job['video_title'] ?? '') ?></h2>
        <p class="intro">Reviseu les traduccions generades si cal, o continueu directament a l'etiquetatge.</p>

        <div class="lang-list">
            <div class="lang-card lang-card-original">
                <span class="orig-lang-label">
                    <span class="lang-label"><?= htmlspecialchars($masterLangLabel) ?></span>
                    <span class="badge-original">Original</span>
                </span>
                <div class="download-btns">
                    <a class="download-btn" href="?action=download-vtt"><span class="material-icons">download</span>VTT</a>
                    <a class="download-btn" href="?action=download-srt"><span class="material-icons">download</span>SRT</a>
                </div>
            </div>
            <?php foreach ($languageCards as $card): ?>
                <div class="lang-card">
                    <span class="lang-label"><?= htmlspecialchars($card['label']) ?></span>
                    <div class="actions">
                        <span class="badge badge-<?= htmlspecialchars($card['badgeClass']) ?>"><?= htmlspecialchars($card['badgeLabel']) ?></span>
                        <?php if ($card['canRetry']): ?>
                            <form method="POST" action="?action=translation-retry" class="retry-form">
                                <input type="hidden" name="lang" value="<?= htmlspecialchars($card['id']) ?>">
                                <button type="submit" class="retry-btn">Reintenta</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if ($card['hasFile']): ?>
                        <div class="download-btns">
                            <a class="download-btn" href="?action=download-vtt&amp;lang=<?= urlencode($card['id']) ?>"><span class="material-icons">download</span>VTT</a>
                            <a class="download-btn" href="?action=download-srt&amp;lang=<?= urlencode($card['id']) ?>"><span class="material-icons">download</span>SRT</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="?action=proceed-to-tagging" id="proceed-form">
            <button type="submit" class="btn-primary">Continua a l'etiquetatge</button>
        </form>
    </main>
    <script>
        document.querySelectorAll('.retry-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(form);
                fetch('?action=translation-retry', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function () {
                    window.location.href = '?action=translation';
                });
            });
        });

        document.getElementById('proceed-form').addEventListener('submit', function (e) {
            e.preventDefault();
            fetch('?action=proceed-to-tagging', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function () {
                window.location.href = '?action=tagging';
            });
        });
    </script>
</body>
</html>
