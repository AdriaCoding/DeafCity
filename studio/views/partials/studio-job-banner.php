<?php /** @var array{title: string, resumeUrl: string, detail: ?string} $activeJobBanner */ ?>
<div class="studio-job-banner" role="status">
    <div class="studio-job-banner-text">
        <strong>Feina en curs:</strong>
        <?= htmlspecialchars($activeJobBanner['title'], ENT_QUOTES) ?>
        <?php if (!empty($activeJobBanner['detail'])): ?>
            <span class="studio-job-banner-detail"> — Pas: <?= htmlspecialchars($activeJobBanner['detail'], ENT_QUOTES) ?></span>
        <?php endif; ?>
    </div>
    <a class="studio-job-banner-cta" href="<?= htmlspecialchars($activeJobBanner['resumeUrl'], ENT_QUOTES) ?>">Continua</a>
</div>
