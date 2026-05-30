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
        .transcribing {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
            text-align: center;
            gap: 1.5rem;
        }
        .transcribing .job-title {
            font-size: 1rem;
            color: #555;
            font-weight: 400;
        }
        .transcribing .status-label {
            font-size: 0.85rem;
            color: #666;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 36px;
            height: 36px;
            border: 2px solid #222;
            border-top-color: #888;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }
        .transcribe-error {
            background: #1a1010;
            border: 1px solid #3a2020;
            border-radius: 6px;
            padding: 1.25rem 1.5rem;
            color: #e05555;
            font-size: 0.875rem;
            max-width: 400px;
            text-align: left;
        }
        .transcribe-error p { margin-bottom: 1rem; }
        .fallback-note {
            max-width: 26rem;
            margin: 0.75rem auto 0;
            color: #b58a4a;
            font-size: 0.8rem;
            line-height: 1.5;
            text-align: center;
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
        <?php elseif ($isTranscribing): ?>
            <?php if ($transcriptionError !== null): ?>
                <div class="transcribing">
                    <p class="job-title"><?= htmlspecialchars($job['video_title']) ?></p>
                    <div class="transcribe-error">
                        <p><?= htmlspecialchars($transcriptionError) ?></p>
                        <form method="POST" action="?action=cancel" id="cancel-form-err">
                            <button type="submit" class="btn-danger">Cancel·la la feina</button>
                        </form>
                    </div>
                </div>
                <script>
                    document.getElementById('cancel-form-err').addEventListener('submit', function (e) {
                        var ok = confirm('Voleu cancel·lar aquesta feina? Aquesta acció no es pot desfer.');
                        if (!ok) e.preventDefault();
                    });
                </script>
            <?php else: ?>
                <div class="transcribing" id="transcribing-view">
                    <p class="job-title"><?= htmlspecialchars($job['video_title']) ?></p>
                    <div class="spinner"></div>
                    <p class="status-label" id="status-label">Generant subtítols…</p>
                    <?php if (!empty($isLocalFallback)): ?>
                        <p class="fallback-note">El whisper online (Groq) no està disponible ara mateix. Generant els subtítols amb el whisper local; això pot trigar uns minuts.</p>
                    <?php endif; ?>
                    <form method="POST" action="?action=cancel" id="cancel-form-gen">
                        <button type="submit" class="btn-danger" style="font-size:0.8rem;padding:0.5rem 1rem">Cancel·la</button>
                    </form>
                </div>
                <script>
                    (function () {
                        document.getElementById('cancel-form-gen').addEventListener('submit', function (e) {
                            var ok = confirm('Voleu cancel·lar aquesta feina? Aquesta acció no es pot desfer.');
                            if (!ok) e.preventDefault();
                        });

                        function poll() {
                            fetch('?action=transcription-status')
                                .then(function (r) { return r.json(); })
                                .then(function (data) {
                                    if (data.status === 'done') {
                                        window.location.href = '?action=subtitle-editor';
                                    } else if (data.status === 'error') {
                                        document.getElementById('status-label').textContent =
                                            data.message || 'Error en la generació de subtítols';
                                        document.querySelector('.spinner').style.display = 'none';
                                    } else {
                                        setTimeout(poll, 3000);
                                    }
                                })
                                .catch(function () { setTimeout(poll, 3000); });
                        }

                        setTimeout(poll, 3000);
                    }());
                </script>
            <?php endif; ?>
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
