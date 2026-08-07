<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polint subtítols — Studio</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: var(--studio-bg);
            font-family: var(--studio-font);
            color: var(--studio-text);
            display: flex;
            flex-direction: column;
        }
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
            color: var(--studio-text-muted);
            font-weight: 400;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 36px;
            height: 36px;
            border: 2px solid var(--studio-border-subtle);
            border-top-color: var(--studio-success-muted);
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }
        .status-label {
            font-size: 0.85rem;
            color: var(--studio-text-muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .error-panel {
            background: var(--studio-danger-bg);
            border: 1px solid var(--studio-danger-border);
            border-radius: 6px;
            padding: 1.25rem 1.5rem;
            color: var(--studio-danger);
            font-size: 0.875rem;
            max-width: 400px;
            text-align: left;
        }
        .error-panel p { margin-bottom: 1rem; }
        button.btn-danger {
            padding: 0.65rem 1.25rem;
            background: transparent;
            color: var(--studio-danger);
            border: 1px solid var(--studio-danger-border);
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        button.btn-danger:hover { color: var(--studio-danger); border-color: var(--studio-danger); }
        .done-msg {
            font-size: 0.85rem;
            color: var(--studio-success-muted);
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
            background: var(--studio-hover);
            border: 1px solid var(--studio-border-subtle);
            border-radius: 6px;
            padding: 0.85rem 1.1rem;
        }
        .file-card-lang {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
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
            color: var(--studio-text-muted);
            border: 1px solid var(--studio-border);
            border-radius: 4px;
            font-size: 0.75rem;
            text-decoration: none;
            letter-spacing: 0.04em;
        }
        a.file-download-btn:hover { color: var(--studio-text-muted); border-color: var(--studio-text-muted); }
        a.file-download-btn .material-icons { font-size: 0.9rem; }
        button.btn-finish {
            margin-top: 0.5rem;
            padding: 0.65rem 1.5rem;
            background: var(--studio-hover);
            color: var(--studio-text-muted);
            border: 1px solid var(--studio-border);
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        button.btn-finish:hover { color: var(--studio-text-secondary); border-color: var(--studio-text-muted); }
    </style>
</head>
<body>
    <?php require __DIR__ . '/partials/studio-header.php'; ?>

    <?php if ($pipelineStatus === 'revising'): ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="spinner"></div>
        <p class="status-label">Polint subtítols…</p>
        <form method="POST" action="?action=shorten-cancel" id="cancel-form">
            <button type="submit" class="btn-danger" style="font-size:0.8rem;padding:0.5rem 1rem">Cancel·la</button>
        </form>
    </div>
    <script>
    (function () {
        document.getElementById('cancel-form').addEventListener('submit', function (e) {
            if (!confirm('Voleu cancel·lar aquest processament?')) e.preventDefault();
        });
        setTimeout(function () { window.location.reload(); }, 3000);
    }());
    </script>

    <?php elseif ($pipelineStatus === 'revision_error'): ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="error-panel">
            <p>No s'ha pogut polir el fitxer de subtítols.</p>
            <form method="POST" action="?action=shorten-cancel" id="cancel-form">
                <button type="submit" class="btn-danger">Cancel·la</button>
            </form>
        </div>
    </div>
    <script>
    (function () {
        document.getElementById('cancel-form').addEventListener('submit', function (e) {
            if (!confirm('Voleu cancel·lar aquest processament?')) e.preventDefault();
        });
    }());
    </script>

    <?php else: /* download_ready */ ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <p class="done-msg">Subtítols polits</p>
        <div class="file-cards">
            <div class="file-card">
                <span class="file-card-lang"><?= htmlspecialchars($sourceLanguageLabel) ?></span>
                <div class="file-download-btns">
                    <a class="file-download-btn" href="?action=shorten-download-vtt"><span class="material-icons">download</span>VTT</a>
                    <a class="file-download-btn" href="?action=shorten-download-srt"><span class="material-icons">download</span>SRT</a>
                </div>
            </div>
        </div>
        <form method="POST" action="?action=shorten-cancel" id="finish-form">
            <button type="submit" class="btn-finish">Finalitza</button>
        </form>
    </div>
    <?php endif; ?>
</body>
</html>
