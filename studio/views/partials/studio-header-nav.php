<?php
/** @var bool $isSyncing */
/** @var array|null $syncStatus */
?>
<div class="header-nav">
    <a class="btn-primary" href="?action=intake">Nova feina</a>
    <a class="btn-primary btn-transcription" href="?action=transcription-intake">Nova transcripció</a>
    <form method="POST" action="?action=sync" id="sync-form">
        <button type="submit" class="btn-secondary" id="sync-btn"
            <?= $isSyncing ? 'disabled' : '' ?>>
            <?php if ($isSyncing): ?>
                <span class="spinner-sm"></span> Sincronitzant…
            <?php else: ?>
                Sincronitzar a Vimeo
            <?php endif; ?>
        </button>
    </form>
    <span class="sync-status" id="sync-status-msg"><?php
        $s = $syncStatus['status'] ?? 'idle';
        if ($s === 'running') {
            $n = (int) ($syncStatus['synced'] ?? 0);
            $t = (int) ($syncStatus['total'] ?? 0);
            echo htmlspecialchars("Sincronitzant… ($n/$t)");
        } elseif ($s === 'done') {
            $n = (int) ($syncStatus['synced'] ?? 0);
            $t = (int) ($syncStatus['total'] ?? 0);
            echo htmlspecialchars("Sincronitzat ($n/$t vídeos)");
        }
    ?></span>
    <a class="nav-link logout" href="?action=logout">Tanca la sessió</a>
</div>
<?php if ($isSyncing): ?>
<script>
    (function () {
        var btn = document.getElementById('sync-btn');
        var msg = document.getElementById('sync-status-msg');
        if (!btn || !msg) return;
        msg.className = 'sync-status';

        function poll() {
            fetch('?action=sync-status')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var synced = data.synced || 0;
                    var total = data.total || 0;
                    if (data.status === 'done') {
                        btn.disabled = false;
                        btn.innerHTML = 'Sincronitzar a Vimeo';
                        msg.className = 'sync-status done';
                        msg.textContent = 'Sincronitzat (' + synced + '/' + total + ' vídeos)';
                    } else if (data.status === 'error') {
                        btn.disabled = false;
                        btn.innerHTML = 'Sincronitzar a Vimeo';
                        msg.className = 'sync-status error';
                        msg.textContent = 'Error en la sincronització. Torneu-ho a provar.';
                    } else {
                        msg.textContent = 'Sincronitzant… (' + synced + '/' + total + ')';
                        setTimeout(poll, 2000);
                    }
                })
                .catch(function () { setTimeout(poll, 3000); });
        }

        setTimeout(poll, 2000);
    }());
</script>
<?php endif; ?>
