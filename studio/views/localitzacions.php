<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localitzacions — Studio DEAF.city</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #0a0a0a; font-family: system-ui, sans-serif; color: #e0e0e0; }
        main { padding: 2rem clamp(1rem, 3vw, 2.5rem) 4rem; }
        h1 { font-size: 1.35rem; margin-bottom: 0.5rem; }
        .intro { color: #888; font-size: 0.9rem; margin-bottom: 1.5rem; max-width: 60rem; line-height: 1.5; }
        .lang-status { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; }
        .lang-badge { font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 999px; border: 1px solid #333; }
        .lang-badge.is-complete { border-color: #2d6a4f; color: #95d5b2; }
        .lang-badge.is-incomplete { border-color: #555; color: #999; }
        .section-block { margin-bottom: 2.5rem; }
        .section-block h2 { font-size: 1rem; text-transform: capitalize; margin-bottom: 0.75rem; color: #bbb; }
        .global-toolbar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .btn-seed-all { background: #1a3a6e; border: 1px solid #2a5090; border-radius: 4px; color: #9ab8ff; cursor: pointer; font-size: 0.85rem; padding: 0.5rem 1rem; }
        .btn-seed-all:hover:not(:disabled) { background: #1f4580; }
        .btn-seed-all:disabled { opacity: 0.5; cursor: progress; }
        .seed-progress { color: #888; font-size: 0.8rem; }
        .loc-table-wrap { overflow-x: auto; border: 1px solid #222; border-radius: 6px; }
        table.loc-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; min-width: 48rem; }
        .loc-table th, .loc-table td { border-bottom: 1px solid #222; padding: 0.5rem 0.65rem; vertical-align: top; text-align: left; }
        .loc-table th { background: #111; color: #aaa; font-weight: 500; position: sticky; top: 0; }
        .loc-table tr:last-child td { border-bottom: none; }
        .key-cell { font-family: ui-monospace, monospace; font-size: 0.75rem; color: #888; white-space: nowrap; }
        .context-cell { color: #666; font-size: 0.75rem; max-width: 12rem; }
        .en-cell { color: #ccc; background: #0d0d0d; }
        .cell-input { width: 100%; min-width: 8rem; min-height: 2.2rem; background: #141414; border: 1px solid #333; border-radius: 4px; color: #e0e0e0; font: inherit; padding: 0.35rem 0.5rem; resize: vertical; }
        .cell-input:focus { outline: none; border-color: #555; }
        .cell-input.is-seeded { border-color: #6b4f1d; background: #1a1508; }
        .cell-input.is-saving { opacity: 0.6; }
        .cell-input.is-saved { border-color: #2d6a4f; }
        .cell-input.is-error { border-color: #9b2226; }
        .readonly-en { display: block; padding: 0.35rem 0; line-height: 1.4; }
    </style>
</head>
<body>
<?php Studio\StudioHeader::include(compact('baseUrl', 'syncStatus', 'isSyncing', 'activeNav')); ?>
<main>
    <h1>Localitzacions</h1>
    <p class="intro">Traduccions de la interfície del preview (reproductor, About, Participants). L'anglès és la font de referència. Un idioma només s'auto-detecta quan tot el chrome està traduït.</p>

    <div class="lang-status">
        <?php foreach ($languages as $lang): ?>
            <?php $id = $lang['id']; $complete = $completeness[$id] ?? false; ?>
            <span class="lang-badge <?= $complete ? 'is-complete' : 'is-incomplete' ?>" title="<?= $complete ? 'Complet — actiu per auto-detect' : 'Incomplet — només via ?lang=' ?>">
                <?= htmlspecialchars($lang['label'], ENT_QUOTES) ?> (<?= htmlspecialchars($id, ENT_QUOTES) ?>)
                <?= $complete ? '✓' : '…' ?>
            </span>
        <?php endforeach; ?>
    </div>

    <div class="global-toolbar">
        <button type="button" class="btn-seed-all" id="seed-all-btn" data-langs="<?= htmlspecialchars(implode(',', array_values(array_filter($languageIds, static fn(string $id): bool => $id !== 'en'))), ENT_QUOTES) ?>">
            Omple tots els buits (totes les llengües)
        </button>
        <span class="seed-progress" id="seed-progress" aria-live="polite"></span>
    </div>

    <?php
    $sectionLabels = [
        'player' => 'Reproductor',
        'about' => 'About',
        'participants' => 'Participants',
        'content' => 'Etiquetes de contingut',
    ];
    $editableLangs = array_values(array_filter($languageIds, static fn(string $id): bool => $id !== 'en'));
    ?>

    <?php foreach (['player', 'about', 'participants', 'content'] as $section): ?>
        <?php if (empty($grouped[$section])) continue; ?>
        <section class="section-block" data-section="<?= htmlspecialchars($section, ENT_QUOTES) ?>">
            <h2><?= htmlspecialchars($sectionLabels[$section] ?? $section, ENT_QUOTES) ?></h2>
            <div class="loc-table-wrap">
                <table class="loc-table">
                    <thead>
                        <tr>
                            <th>Clau</th>
                            <th>Context</th>
                            <th>Anglès</th>
                            <?php foreach ($editableLangs as $langId): ?>
                                <th><?= htmlspecialchars($langId, ENT_QUOTES) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped[$section] as $row): ?>
                            <tr>
                                <td class="key-cell"><?= htmlspecialchars($row['key'], ENT_QUOTES) ?></td>
                                <td class="context-cell"><?= htmlspecialchars($row['context'], ENT_QUOTES) ?></td>
                                <td class="en-cell"><span class="readonly-en"><?= htmlspecialchars($row['translations']['en'] ?? '', ENT_QUOTES) ?></span></td>
                                <?php foreach ($editableLangs as $langId): ?>
                                    <?php
                                    $val = $row['translations'][$langId] ?? '';
                                    $seeded = !empty($row['seeded'][$langId]);
                                    ?>
                                    <td>
                                        <textarea
                                            class="cell-input<?= $seeded ? ' is-seeded' : '' ?>"
                                            data-key="<?= htmlspecialchars($row['key'], ENT_QUOTES) ?>"
                                            data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"
                                            rows="2"
                                        ><?= htmlspecialchars($val, ENT_QUOTES) ?></textarea>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</main>
<script>
(function () {
    'use strict';

    function saveCell(textarea) {
        var key = textarea.getAttribute('data-key');
        var lang = textarea.getAttribute('data-lang');
        textarea.classList.add('is-saving');
        textarea.classList.remove('is-saved', 'is-error');

        var body = new FormData();
        body.append('key', key);
        body.append('lang', lang);
        body.append('value', textarea.value);

        fetch('?action=localitzacions-save', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                textarea.classList.remove('is-saving');
                if (data.ok) {
                    textarea.classList.add('is-saved');
                    textarea.classList.remove('is-seeded');
                    setTimeout(function () { textarea.classList.remove('is-saved'); }, 1200);
                } else {
                    textarea.classList.add('is-error');
                }
            })
            .catch(function () {
                textarea.classList.remove('is-saving');
                textarea.classList.add('is-error');
            });
    }

    var debounceTimers = {};
    document.querySelectorAll('.cell-input').forEach(function (textarea) {
        textarea.addEventListener('input', function () {
            var id = textarea.getAttribute('data-key') + ':' + textarea.getAttribute('data-lang');
            clearTimeout(debounceTimers[id]);
            debounceTimers[id] = setTimeout(function () { saveCell(textarea); }, 600);
        });
    });

    var seedBtn = document.getElementById('seed-all-btn');
    var seedProgress = document.getElementById('seed-progress');
    if (seedBtn) {
        seedBtn.addEventListener('click', function () {
            var langs = (seedBtn.getAttribute('data-langs') || '').split(',').filter(Boolean);
            if (langs.length === 0) { return; }

            seedBtn.disabled = true;
            var totalFilled = 0;
            var errors = [];
            var i = 0;

            function next() {
                if (i >= langs.length) {
                    seedProgress.textContent = 'Fet — ' + totalFilled + ' cel·les omplertes'
                        + (errors.length ? (' · errors: ' + errors.join(', ')) : '') + '. Recarregant…';
                    setTimeout(function () { window.location.reload(); }, 1000);
                    return;
                }

                var lang = langs[i];
                seedProgress.textContent = 'Traduint ' + lang + '… (' + (i + 1) + '/' + langs.length + ')';

                var body = new FormData();
                body.append('lang', lang);
                fetch('?action=localitzacions-seed', { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) { totalFilled += (data.filled || 0); }
                        else { errors.push(lang); }
                    })
                    .catch(function () { errors.push(lang); })
                    .then(function () { i++; next(); });
            }

            next();
        });
    }
}());
</script>
</body>
</html>
