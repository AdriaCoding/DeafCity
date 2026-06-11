<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transcripció en massa — Studio</title>
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
            max-width: 720px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th, td {
            text-align: left;
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #1e1e1e;
        }
        th {
            color: #666;
            font-size: 0.75rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 500;
        }
        .status-pending { color: #666; }
        .status-processing { color: #7ed87e; }
        .status-done { color: #5a9a5a; }
        .status-failed { color: #e05555; }
        .reason {
            display: block;
            font-size: 0.78rem;
            color: #a55;
            margin-top: 0.25rem;
        }
        .download-msg {
            margin-top: 1.5rem;
            color: #5a9a5a;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <header>
        <h1>Studio</h1>
        <a class="logout" href="?action=logout">Tanca la sessió</a>
    </header>
    <main>
        <h2>Transcripció en massa</h2>
        <p class="lead">Processant els fitxers d'àudio. Quan acabi, es descarregarà un ZIP amb tots els subtítols en anglès en format SRT.</p>

        <table>
            <thead>
                <tr>
                    <th>Fitxer</th>
                    <th>Estat</th>
                </tr>
            </thead>
            <tbody id="bulk-progress-body">
                <?php foreach ($snapshot['items'] as $item): ?>
                <tr data-id="<?= htmlspecialchars($item['id']) ?>">
                    <td><?= htmlspecialchars($item['originalFilename']) ?></td>
                    <td class="status-cell status-<?= htmlspecialchars($item['status']) ?>">
                        <?= htmlspecialchars(match ($item['status']) {
                            'pending' => 'Pendent',
                            'processing' => 'Processant…',
                            'done' => 'Fet',
                            'failed' => 'Error',
                            default => $item['status'],
                        }) ?>
                        <?php if (!empty($item['reason'])): ?>
                            <span class="reason"><?= htmlspecialchars($item['reason']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="download-msg" id="download-msg" style="display:none">Descarregant ZIP…</p>
    </main>
    <script>
    (function () {
        var statusLabels = {
            pending: 'Pendent',
            processing: 'Processant…',
            done: 'Fet',
            failed: 'Error'
        };

        function esc(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function updateRow(item) {
            var row = document.querySelector('tr[data-id="' + item.id + '"]');
            if (!row) return;
            var cell = row.querySelector('.status-cell');
            cell.className = 'status-cell status-' + esc(item.status);
            var html = statusLabels[item.status] || esc(item.status);
            if (item.reason) {
                html += '<span class="reason">' + esc(item.reason) + '</span>';
            }
            cell.innerHTML = html;
        }

        function poll() {
            fetch('?action=bulk-status')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    (data.items || []).forEach(updateRow);
                    if (data.completed) {
                        document.getElementById('download-msg').style.display = 'block';
                        setTimeout(function () {
                            window.location.href = '?action=bulk-download';
                        }, 1500);
                        return;
                    }
                    setTimeout(poll, 2000);
                })
                .catch(function () { setTimeout(poll, 2000); });
        }

        setTimeout(poll, 2000);
    }());
    </script>
</body>
</html>
