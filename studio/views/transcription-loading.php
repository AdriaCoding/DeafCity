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
        button.btn-retry {
            padding: 0.65rem 1.25rem;
            background: var(--studio-btn-green-alt-bg);
            color: var(--studio-btn-green-text);
            border: 1px solid var(--studio-btn-green-border);
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        button.btn-retry:hover { background: var(--studio-btn-green-alt-bg-hover); }
        .btn-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            justify-content: center;
        }
        .download-msg {
            font-size: 0.9rem;
            color: var(--studio-success-muted);
        }
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

    <?php if ($pipelineStatus === 'transcribing'): ?>
    <div class="stage" id="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="spinner"></div>
        <p class="status-label" id="status-label">Transcrivint…</p>
        <form method="POST" action="?action=cancel" id="cancel-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
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

    <?php elseif ($pipelineStatus === 'revising'): ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="spinner"></div>
        <p class="status-label">Revisant subtítols…</p>
        <form method="POST" action="?action=cancel" id="cancel-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <button type="submit" class="btn-danger" style="font-size:0.8rem;padding:0.5rem 1rem">Cancel·la</button>
        </form>
    </div>
    <script>
    (function () {
        document.getElementById('cancel-form').addEventListener('submit', function (e) {
            if (!confirm('Voleu cancel·lar aquesta transcripció?')) e.preventDefault();
        });
        setTimeout(function () { window.location.reload(); }, 3000);
    }());
    </script>

    <?php elseif ($pipelineStatus === 'revision_error'): ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="error-panel">
            <p>No s'ha pogut revisar el fitxer de subtítols.</p>
            <form method="POST" action="?action=cancel" id="cancel-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <button type="submit" class="btn-danger">Cancel·la</button>
            </form>
        </div>
    </div>
    <script>
    (function () {
        document.getElementById('cancel-form').addEventListener('submit', function (e) {
            if (!confirm('Voleu cancel·lar aquesta transcripció?')) e.preventDefault();
        });
    }());
    </script>

    <?php elseif ($pipelineStatus === 'translating'): ?>
    <div class="stage">
        <p class="filename"><?= htmlspecialchars($originalFilename) ?></p>
        <div class="spinner"></div>
        <p class="status-label" id="trans-label">Traduint a l'anglès…</p>
        <form method="POST" action="?action=cancel" id="cancel-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
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
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    <input type="hidden" name="lang" value="en">
                    <button type="submit" class="btn-retry">Torna-ho a provar</button>
                </form>
                <form method="POST" action="?action=cancel" id="cancel-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
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
            <?php if (!$englishTranslationSkipped): ?>
            <div class="file-card">
                <span class="file-card-lang"><?= htmlspecialchars($sourceLanguageLabel) ?></span>
                <div class="file-download-btns">
                    <a class="file-download-btn" href="?action=download-srt"><span class="material-icons">download</span>SRT</a>
                </div>
            </div>
            <?php endif; ?>
            <div class="file-card">
                <span class="file-card-lang">Anglès</span>
                <div class="file-download-btns">
                    <?php if (!empty($englishTranslationSkipped)): ?>
                    <a class="file-download-btn" href="?action=download-srt"><span class="material-icons">download</span>SRT</a>
                    <?php else: ?>
                    <a class="file-download-btn" href="?action=download-srt&amp;lang=en"><span class="material-icons">download</span>SRT</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <form method="POST" action="?action=cancel" id="finish-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <button type="submit" class="btn-finish">Finalitza</button>
        </form>
    </div>
    <?php endif; ?>
</body>
</html>
