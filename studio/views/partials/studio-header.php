<?php
/**
 * Unified Studio header. Expects:
 * @var string $baseUrl
 * @var array|null $syncStatus
 * @var bool $isSyncing
 * @var string|null $activeNav  StudioHeader::NAV_* constant
 * @var string|null $pipelineStep  translation|tagging|publication
 */

use Studio\StudioHeader;

$syncMessage = StudioHeader::syncStatusMessage($syncStatus);
$syncStatusClass = match ($syncStatus['status'] ?? 'idle') {
    'done' => 'is-done',
    'error' => 'is-error',
    default => '',
};

$cssPath = dirname(__DIR__, 2) . '/css/studio-header.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';

$pipelineSteps = [
    'translation' => ['label' => 'Traducció', 'url' => '?action=translation'],
    'tagging' => ['label' => 'Etiquetatge', 'url' => '?action=tagging'],
    'publication' => ['label' => 'Publicació', 'url' => '?action=publication'],
];
$pipelineOrder = ['translation', 'tagging', 'publication'];
$currentStepIndex = $pipelineStep !== null ? array_search($pipelineStep, $pipelineOrder, true) : false;
?>
<link rel="stylesheet" href="css/studio-header.css?v=<?= htmlspecialchars($cssVersion, ENT_QUOTES) ?>">
<header class="studio-header">
    <div class="studio-header-row">
        <a class="studio-brand" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>" aria-label="Torna al catàleg">Studio</a>
        <nav class="studio-nav" aria-label="Navegació principal">
            <a class="studio-nav-link<?= $activeNav === StudioHeader::NAV_CATALOG ? ' is-active' : '' ?>"
               href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>"
               <?= $activeNav === StudioHeader::NAV_CATALOG ? 'aria-current="page"' : '' ?>>Catàleg</a>
            <a class="studio-nav-link<?= $activeNav === StudioHeader::NAV_INTAKE ? ' is-active' : '' ?>"
               href="?action=intake"
               <?= $activeNav === StudioHeader::NAV_INTAKE ? 'aria-current="page"' : '' ?>>Nova feina</a>
            <a class="studio-nav-link<?= $activeNav === StudioHeader::NAV_TRANSCRIPTION_INTAKE ? ' is-active' : '' ?>"
               href="?action=transcription-intake"
               <?= $activeNav === StudioHeader::NAV_TRANSCRIPTION_INTAKE ? 'aria-current="page"' : '' ?>>Nova transcripció</a>
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
    <?php if ($pipelineStep !== null && $currentStepIndex !== false): ?>
        <nav class="studio-pipeline-steps" aria-label="Pas del pipeline">
            <?php foreach ($pipelineOrder as $index => $stepId): ?>
                <?php if ($index > 0): ?>
                    <span class="studio-pipeline-sep" aria-hidden="true">→</span>
                <?php endif; ?>
                <?php
                    $step = $pipelineSteps[$stepId];
                    $classes = ['studio-pipeline-step'];
                    if ($stepId === $pipelineStep) {
                        $classes[] = 'is-current';
                    } elseif ($index < $currentStepIndex) {
                        $classes[] = 'is-done';
                    }
                ?>
                <a class="<?= implode(' ', $classes) ?>"
                   href="<?= htmlspecialchars($step['url'], ENT_QUOTES) ?>"
                   <?= $stepId === $pipelineStep ? 'aria-current="step"' : '' ?>><?= htmlspecialchars($step['label']) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
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
                        msg.textContent = 'Sincronitzat (' + synced + '/' + total + ' vídeos)';
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
