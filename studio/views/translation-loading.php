<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traducció — Studio</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0a0a0a;
            color: #e0e0e0;
            font-family: system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .transcribing {
            width: 100%;
            max-width: 28rem;
        }
        .job-title {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
            color: #ccc;
            text-align: center;
        }
        .progress-summary {
            color: #777;
            font-size: 0.88rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .lang-progress-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.75rem;
        }
        .lang-progress-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #141414;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 0.75rem 1rem;
        }
        .lang-progress-row.is-active {
            border-color: #4a7c59;
            background: #111a14;
        }
        .lang-progress-row.is-done {
            border-color: #2a4a2a;
        }
        a.lang-progress-row {
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        a.lang-progress-row:hover {
            border-color: #4a7c59;
            background: #111a14;
        }
        .lang-progress-row.is-error {
            border-color: #4a2020;
            background: #1a1010;
        }
        .lang-label { font-size: 0.95rem; }
        .lang-status {
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #666;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .lang-status.is-active { color: #7ed87e; }
        .lang-status.is-done { color: #7ed87e; }
        .lang-status.is-error { color: #e05555; }
        .mini-spinner {
            width: 0.85rem;
            height: 0.85rem;
            border: 2px solid #333;
            border-top-color: #7ed87e;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            flex-shrink: 0;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .actions { text-align: center; }
        .btn-danger {
            background: transparent;
            color: #a55;
            border: 1px solid #533;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .btn-danger:hover { color: #e88; border-color: #744; }
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
        <div class="transcribing" id="translating-view">
            <p class="job-title"><?= htmlspecialchars($job['video_title'] ?? '') ?></p>
            <p class="progress-summary" id="progress-summary"></p>
            <div class="lang-progress-list" id="lang-progress-list">
                <?php foreach ($languageItems as $item):
                    $isDone = in_array($item['status'], ['done', 'reviewed'], true);
                    $tag = $isDone ? 'a' : 'div';
                    $href = $isDone ? ' href="?action=translation-review&lang=' . urlencode($item['id']) . '"' : '';
                ?>
                    <<?= $tag ?><?= $href ?>
                        class="lang-progress-row<?= $isDone ? ' is-done' : '' ?>"
                        data-lang="<?= htmlspecialchars($item['id']) ?>"
                    >
                        <span class="lang-label"><?= htmlspecialchars($item['label']) ?></span>
                        <span class="lang-status" data-status="<?= htmlspecialchars($item['status']) ?>"></span>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </div>
            <div class="actions">
                <form method="POST" action="?action=cancel" id="cancel-form">
                    <button type="submit" class="btn-danger">Cancel·la</button>
                </form>
            </div>
        </div>
    </main>
    <script>
        window.__languageItems = <?= json_encode($languageItems, JSON_UNESCAPED_UNICODE) ?>;

        document.getElementById('cancel-form').addEventListener('submit', function (e) {
            if (!confirm('Voleu cancel·lar aquesta feina? Aquesta acció no es pot desfer.')) {
                e.preventDefault();
            }
        });

        function statusLabel(status, isActive) {
            if (status === 'done' || status === 'reviewed') return 'Fet';
            if (status === 'error') return 'Error';
            if (isActive) return 'Generant…';
            return 'En cua';
        }

        function promoteToLink(row, lang) {
            if (row.tagName === 'A') return row;
            var a = document.createElement('a');
            a.href = '?action=translation-review&lang=' + encodeURIComponent(lang);
            a.className = row.className;
            a.dataset.lang = lang;
            while (row.firstChild) a.appendChild(row.firstChild);
            row.parentNode.replaceChild(a, row);
            return a;
        }

        function renderProgress(data) {
            var languages = data.languages || {};
            var rows = document.querySelectorAll('.lang-progress-row');
            var doneCount = 0;
            var total = rows.length;
            var activeAssigned = false;

            rows.forEach(function (row) {
                var lang = row.dataset.lang;
                var entry = languages[lang] || { status: 'pending' };
                var status = entry.status || 'pending';
                var isActive = false;
                var isDone = status === 'done' || status === 'reviewed';

                if (isDone || status === 'error') {
                    if (isDone) doneCount++;
                } else if (!activeAssigned) {
                    isActive = true;
                    activeAssigned = true;
                }

                if (isDone) {
                    row = promoteToLink(row, lang);
                }

                row.classList.toggle('is-active', isActive);
                row.classList.toggle('is-done', isDone);
                row.classList.toggle('is-error', status === 'error');

                var statusEl = row.querySelector('.lang-status');
                statusEl.className = 'lang-status'
                    + (isActive ? ' is-active' : '')
                    + (isDone ? ' is-done' : '')
                    + (status === 'error' ? ' is-error' : '');
                statusEl.dataset.status = status;
                statusEl.textContent = '';

                if (isActive) {
                    var spinner = document.createElement('span');
                    spinner.className = 'mini-spinner';
                    statusEl.appendChild(spinner);
                }
                statusEl.appendChild(document.createTextNode(statusLabel(status, isActive)));
            });

            var summary = document.getElementById('progress-summary');
            if (total === 0) {
                summary.textContent = 'Generant traduccions…';
            } else {
                summary.textContent = doneCount + ' de ' + total + ' traduccions completades';
            }
        }

        function poll() {
            fetch('?action=translation-status')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderProgress(data);
                    if (data.status === 'done' || data.status === 'error') {
                        window.location.href = '?action=translation';
                    } else {
                        setTimeout(poll, 3000);
                    }
                })
                .catch(function () { setTimeout(poll, 3000); });
        }

        renderProgress({ languages: Object.fromEntries(
            window.__languageItems.map(function (item) {
                return [item.id, { status: item.status }];
            })
        ) });
        poll();
    </script>
</body>
</html>
