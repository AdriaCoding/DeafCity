<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio — DEAF.city</title>
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
            padding: 3rem 2rem;
            max-width: 640px;
        }
        .idle p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .job-card {
            background: #141414;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 1.5rem;
        }
        .job-card h2 {
            font-size: 1.15rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }
        .job-meta {
            color: #888;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }
        a.btn-primary {
            display: inline-block;
            padding: 0.65rem 1.25rem;
            background: #e0e0e0;
            color: #0a0a0a;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        a.btn-primary:hover { background: #fff; }
        button.btn-danger {
            padding: 0.65rem 1.25rem;
            background: transparent;
            color: #a55;
            border: 1px solid #533;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        button.btn-danger:hover {
            color: #e88;
            border-color: #844;
        }
    </style>
</head>
<body>
    <header>
        <h1>Studio</h1>
        <a class="logout" href="?action=logout">Tanca la sessió</a>
    </header>
    <main>
        <?php if (!$hasActiveJob): ?>
            <div class="idle">
                <p>No hi ha cap feina en curs. Inicieu la recepció per registrar un vídeo de Vimeo i pujar un fitxer de subtítols esborrany.</p>
                <div class="actions">
                    <a class="btn-primary" href="?action=intake">Nova feina</a>
                </div>
            </div>
        <?php else: ?>
            <div class="job-card">
                <h2><?= htmlspecialchars($job['video_title']) ?></h2>
                <p class="job-meta">
                    <strong>Edició:</strong> <?= htmlspecialchars($editionLabel) ?><br>
                    <strong>Pas:</strong> <?= htmlspecialchars($stepLabel) ?>
                </p>
                <div class="actions">
                    <a class="btn-primary" href="<?= htmlspecialchars($resumeUrl) ?>">Continua</a>
                    <form method="POST" action="?action=cancel" style="display:inline" id="cancel-form">
                        <button type="submit" class="btn-danger">Cancel·la la feina</button>
                    </form>
                </div>
            </div>
            <script>
                document.getElementById('cancel-form').addEventListener('submit', function (e) {
                    var ok = confirm(
                        'Voleu cancel·lar aquesta feina?\n\nAixò suprimirà la carpeta de la feina i el fitxer de subtítols esborrany pujat (draft.vtt). Aquesta acció no es pot desfer.'
                    );
                    if (!ok) e.preventDefault();
                });
            </script>
        <?php endif; ?>
    </main>
</body>
</html>
