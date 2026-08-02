<?php
/**
 * Unified Studio header. Expects:
 * @var string $baseUrl
 * @var array|null $syncStatus
 * @var bool $isSyncing
 * @var string|null $activeNav  StudioHeader::NAV_* constant
 */

use Studio\StudioHeader;

$syncMessage = StudioHeader::syncStatusMessage($syncStatus);
$syncStatusClass = match ($syncStatus['status'] ?? 'idle') {
    'done' => 'is-done',
    'error' => 'is-error',
    default => '',
};

$cssDir = dirname(__DIR__, 2) . '/css';
$colorsPath = $cssDir . '/colors.css';
$cssPath = $cssDir . '/studio-header.css';
$colorsVersion = is_file($colorsPath) ? (string) filemtime($colorsPath) : '1';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/colors.css?v=<?= htmlspecialchars($colorsVersion, ENT_QUOTES) ?>">
<link rel="stylesheet" href="css/studio-header.css?v=<?= htmlspecialchars($cssVersion, ENT_QUOTES) ?>">
<header class="studio-header">
    <div class="studio-header-row">
        <a class="studio-brand" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>" aria-label="Torna al catàleg">Studio</a>
        <nav class="studio-nav" aria-label="Navegació principal">
            <a class="studio-nav-link<?= $activeNav === StudioHeader::NAV_CATALOG ? ' is-active' : '' ?>"
               href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>"
               <?= $activeNav === StudioHeader::NAV_CATALOG ? 'aria-current="page"' : '' ?>>Catàleg</a>
            <a class="studio-nav-link<?= $activeNav === StudioHeader::NAV_TRANSCRIPTION_INTAKE ? ' is-active' : '' ?>"
               href="?action=transcription-intake"
               <?= $activeNav === StudioHeader::NAV_TRANSCRIPTION_INTAKE ? 'aria-current="page"' : '' ?>>Nova transcripció</a>
            <a class="studio-nav-link<?= $activeNav === StudioHeader::NAV_LOCALIZATIONS ? ' is-active' : '' ?>"
               href="?action=localitzacions"
               <?= $activeNav === StudioHeader::NAV_LOCALIZATIONS ? 'aria-current="page"' : '' ?>>Localitzacions</a>
            <a class="studio-nav-link<?= $activeNav === StudioHeader::NAV_CREDITS_EDITOR ? ' is-active' : '' ?>"
               href="?action=credits-editor"
               <?= $activeNav === StudioHeader::NAV_CREDITS_EDITOR ? 'aria-current="page"' : '' ?>>Crèdits</a>
        </nav>
        <div class="studio-header-utilities">
            <form method="POST" action="?action=sync" class="studio-sync-form" id="sync-form">
                <button type="submit" class="studio-sync-btn" id="sync-btn"
                    <?= $isSyncing ? 'disabled' : '' ?>>
                    <?php if ($isSyncing): ?>
                        <span class="studio-spinner-sm" aria-hidden="true"></span> Sincronitzant…
                    <?php else: ?>
                        Sincronitzar a Vimeo
                    <?php endif; ?>
                </button>
            </form>
            <?php if ($syncMessage !== ''): ?>
                <span class="studio-sync-status <?= htmlspecialchars($syncStatusClass, ENT_QUOTES) ?>"
                      id="sync-status-msg"
                      aria-live="polite"><?= htmlspecialchars($syncMessage) ?></span>
            <?php else: ?>
                <span class="studio-sync-status" id="sync-status-msg" aria-live="polite" hidden></span>
            <?php endif; ?>
            <a class="studio-logout" href="?action=logout">Tanca la sessió</a>
        </div>
    </div>
</header>
<?php if ($isSyncing): ?>
<script>
    (function () {
        var btn = document.getElementById('sync-btn');
        var msg = document.getElementById('sync-status-msg');
        if (!btn || !msg) return;
        msg.hidden = false;
        msg.className = 'studio-sync-status';

        function poll() {
            fetch('?action=sync-status')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var synced = data.synced || 0;
                    var total = data.total || 0;
                    if (data.status === 'done') {
                        btn.disabled = false;
                        btn.innerHTML = 'Sincronitzar a Vimeo';
                        msg.className = 'studio-sync-status is-done';
                        msg.textContent = 'Última sincronització: ' + synced + ' enviats de ' + total;
                    } else if (data.status === 'error') {
                        btn.disabled = false;
                        btn.innerHTML = 'Sincronitzar a Vimeo';
                        msg.className = 'studio-sync-status is-error';
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
