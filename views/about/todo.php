<?php
if (!function_exists('preview_t')) {
    require_once dirname(dirname(__DIR__)) . '/lib/preview_locale.php';
}
if (!isset($GLOBALS['preview_i18n']) || !($GLOBALS['preview_i18n'] instanceof PreviewI18n)) {
    $__todo_locale = preview_bootstrap_locale();
    $GLOBALS['preview_i18n'] = $__todo_locale['i18n'];
}
?>
<b><?= htmlspecialchars(preview_t('about.block.deaf_city.title'), ENT_QUOTES, 'UTF-8') ?></b>
<p class="style1"><?= preview_t('about.block.deaf_city.p1') ?></p>

<b><?= htmlspecialchars(preview_t('about.block.breaking_silence.title'), ENT_QUOTES, 'UTF-8') ?></b>
<p class="style1"><?= preview_t('about.block.breaking_silence.p1') ?></p>

<b><?= htmlspecialchars(preview_t('about.block.dissemination.title'), ENT_QUOTES, 'UTF-8') ?></b>
<p class="style1"><?= preview_t('about.block.dissemination.p1') ?></p>
<p class="style1"></p>

<b><?= htmlspecialchars(preview_t('about.block.timeline.title'), ENT_QUOTES, 'UTF-8') ?></b>
<p class="style1"><?= preview_t('about.block.timeline.p1') ?></p>

<b><?= htmlspecialchars(preview_t('about.block.silent_eloquence.title'), ENT_QUOTES, 'UTF-8') ?></b>
<p class="style1"><?= preview_t('about.block.silent_eloquence.p1') ?></p>
