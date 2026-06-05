<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transcripció en curs — Studio</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #0a0a0a;
            font-family: system-ui, sans-serif;
            color: #e0e0e0;
            display: flex;
            flex-direction: column;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 2rem;
            border-bottom: 1px solid #1e1e1e;
            flex-shrink: 0;
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
        .stage {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 1.5rem;
            padding: 2rem;
        }
        .filename {
            font-size: 1rem;
            color: #555;
            font-weight: 400;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 36px;
            height: 36px;
            border: 2px solid #222;
            border-top-color: #5a9a5a;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }
        .status-label {
            font-size: 0.85rem;
            color: #666;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .error-panel {
            background: #1a1010;
            border: 1px solid #3a2020;
            border-radius: 6px;
            padding: 1.25rem 1.5rem;
            color: #e05555;
            font-size: 0.875rem;
            max-width: 400px;
            text-align: left;
        }
        .error-panel p { margin-bottom: 1rem; }
        button.btn-danger {
            padding: 0.65rem 1.25rem;
            background: transparent;
            color: #a55;
            border: 1px solid #533;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        button.btn-danger:hover { color: #e88; border-color: #844; }
        button.btn-retry {
            padding: 0.65rem 1.25rem;
            background: #1a2a1a;
            color: #7ed87e;
            border: 1px solid #2a6040;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        button.btn-retry:hover { background: #1e3320; }
        .btn-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            justify-content: center;
        }
        .download-msg {
            font-size: 0.9rem;
            color: #5a9a5a;
        }
        .done-msg {
            font-size: 0.85rem;
            color: #5a9a5a;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .file-cards {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            width: 100%;
            max-width: 400px;
        }
        .file-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #141414;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 0.85rem 1.1rem;
        }
        .file-card-lang {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
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
        .file-download-btns {
            display: flex;
            gap: 0.4rem;
            flex-shrink: 0;
        }
        a.file-download-btn {
            display: flex;
            align-items: center;
            gap: 0.2rem;
            padding: 0.28rem 0.6rem;
            background: transparent;
            color: #555;
            border: 1px solid #2a2a2a;
            border-radius: 4px;
            font-size: 0.75rem;
            text-decoration: none;
            letter-spacing: 0.04em;
        }
        a.file-download-btn:hover { color: #aaa; border-color: #555; }
        a.file-download-btn .material-icons { font-size: 0.9rem; }
        button.btn-finish {
            margin-top: 0.5rem;
            padding: 0.65rem 1.5rem;
            background: #141414;
            color: #888;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        button.btn-finish:hover { color: #ccc; border-color: #555; }
    </style>
</head>
<body>
    <header>
        <h1>Studio</h1>
        <a class="logout" href="?action=logout">Tanca la sessió</a>
    </header>

    <?php if ($pipelineStatus === 'transcribing'): ?>
    <div class="stage" id="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="spinner"></div>
        <p class="status-label" id="status-label">Transcrivint…</p>
        <form method="POST" action="?action=cancel" id="cancel-form">
            <button type="submit" class="btn-danger" style="font-size:0.8rem;padding:0.5rem 1rem">Cancel·la</button>
        </form>
    </div>
    <script>
    (function () {
        document.getElementById('cancel-form').addEventListener('submit', function (e) {
            if (!confirm('Voleu cancel·lar aquesta transcripció?')) e.preventDefault();
        });
        function poll() {
            fetch('?action=transcription-status')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'done') {
                        window.location.reload();
                    } else if (data.status === 'error') {
                        document.getElementById('status-label').textContent =
                            data.message || 'Error en la transcripció';
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

    <?php elseif ($pipelineStatus === 'translating'): ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="spinner"></div>
        <p class="status-label" id="trans-label">Traduint a l'anglès…</p>
        <form method="POST" action="?action=cancel" id="cancel-form">
            <button type="submit" class="btn-danger" style="font-size:0.8rem;padding:0.5rem 1rem">Cancel·la</button>
        </form>
    </div>
    <script>
    (function () {
        document.getElementById('cancel-form').addEventListener('submit', function (e) {
            if (!confirm('Voleu cancel·lar aquesta transcripció?')) e.preventDefault();
        });
        function poll() {
            fetch('?action=translation-status')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'done') {
                        window.location.reload();
                    } else {
                        setTimeout(poll, 3000);
                    }
                })
                .catch(function () { setTimeout(poll, 3000); });
        }
        setTimeout(poll, 3000);
    }());
    </script>

    <?php elseif ($pipelineStatus === 'translation_error'): ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="error-panel">
            <p>No s'ha pogut traduir el fitxer a l'anglès.</p>
            <div class="btn-row">
                <form method="POST" action="?action=translation-retry" id="retry-form">
                    <input type="hidden" name="lang" value="en">
                    <button type="submit" class="btn-retry">Torna-ho a provar</button>
                </form>
                <form method="POST" action="?action=cancel" id="cancel-form">
                    <button type="submit" class="btn-danger">Cancel·la</button>
                </form>
            </div>
        </div>
    </div>
    <script>
    (function () {
        document.getElementById('cancel-form').addEventListener('submit', function (e) {
            if (!confirm('Voleu cancel·lar aquesta transcripció?')) e.preventDefault();
        });
        document.getElementById('retry-form').addEventListener('submit', function () {
            setTimeout(function () { window.location.reload(); }, 800);
        });
    }());
    </script>

    <?php else: /* download_ready */ ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <p class="done-msg">Subtítols generats</p>
        <div class="file-cards">
            <div class="file-card">
                <span class="file-card-lang">
                    <?= htmlspecialchars($subtitleLanguageLabel) ?>
                    <span class="badge-original">Original</span>
                </span>
                <div class="file-download-btns">
                    <a class="file-download-btn" href="?action=download-vtt"><span class="material-icons">download</span>VTT</a>
                    <a class="file-download-btn" href="?action=download-srt"><span class="material-icons">download</span>SRT</a>
                </div>
            </div>
            <div class="file-card">
                <span class="file-card-lang">Anglès</span>
                <div class="file-download-btns">
                    <a class="file-download-btn" href="?action=download-vtt&amp;lang=en"><span class="material-icons">download</span>VTT</a>
                    <a class="file-download-btn" href="?action=download-srt&amp;lang=en"><span class="material-icons">download</span>SRT</a>
                </div>
            </div>
        </div>
        <form method="POST" action="?action=cancel" id="finish-form">
            <button type="submit" class="btn-finish">Finalitza</button>
        </form>
    </div>
    <?php endif; ?>
</body>
</html>
