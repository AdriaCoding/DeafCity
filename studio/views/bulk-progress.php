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
            background: var(--studio-bg);
            font-family: var(--studio-font);
            color: var(--studio-text);
        }
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
            color: var(--studio-text-muted);
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
            border-bottom: 1px solid var(--studio-border-subtle);
        }
        th {
            color: var(--studio-text-muted);
            font-size: 0.75rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 500;
        }
        .status-pending { color: var(--studio-text-muted); }
        .status-processing { color: var(--studio-btn-green-text); }
        .status-done { color: var(--studio-success-muted); }
        .status-failed { color: var(--studio-danger); }
        a.back {
            font-size: 0.8rem;
            color: var(--studio-text-muted);
            text-decoration: none;
        }
        a.back:hover { color: var(--studio-text-muted); }
        .reason {
            display: block;
            font-size: 0.78rem;
            color: var(--studio-danger);
            margin-top: 0.25rem;
        }
        .download-msg {
            margin-top: 1.5rem;
            color: var(--studio-success-muted);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/partials/studio-header.php'; ?>
    <main>
        <h2>Transcripció en massa</h2>
        <p class="lead">Processant els fitxers d'àudio. Quan acabi, es descarregarà un ZIP amb tots els subtítols.</p>

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

        <div id="completed-actions" style="display:none; margin-top:1.5rem;">
            <p class="download-msg">Descàrrega iniciada.</p>
            <p style="margin-top:0.6rem; font-size:0.85rem; color:var(--studio-text-muted);">
                Si la descàrrega no s'inicia automàticament,
                <a href="?action=bulk-download" id="manual-download-link" style="color:var(--studio-success-text);">feu clic aquí</a>.
            </p>
        </div>
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

        function triggerDownload() {
            document.getElementById('completed-actions').style.display = 'block';
            var a = document.createElement('a');
            a.href = '?action=bulk-download';
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function poll() {
            fetch('?action=bulk-status')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    (data.items || []).forEach(updateRow);
                    if (data.completed) {
                        triggerDownload();
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
