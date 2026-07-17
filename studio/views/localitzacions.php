<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localitzacions — Studio DEAF.city</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: var(--studio-bg); font-family: var(--studio-font); color: var(--studio-text); }
        main { padding: 2rem clamp(1rem, 3vw, 2.5rem) 4rem; }
        h1 { font-size: 1.35rem; margin-bottom: 0.5rem; }
        .intro { color: var(--studio-text-muted); font-size: 0.9rem; margin-bottom: 1.5rem; max-width: 60rem; line-height: 1.5; }
        .lang-status { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; }
        .lang-badge { font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 999px; border: 1px solid var(--studio-border); }
        .lang-badge.is-complete { border-color: var(--studio-success-border); color: var(--studio-success-text); }
        .lang-badge.is-incomplete { border-color: var(--studio-text-muted); color: var(--studio-text-muted); }
        .section-block { margin-bottom: 2.5rem; }
        .section-block h2 { font-size: 1rem; text-transform: capitalize; margin-bottom: 0.75rem; color: var(--studio-text-secondary); }
        .global-toolbar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .btn-seed-all { background: var(--studio-accent-bg); border: 1px solid var(--studio-accent-border); border-radius: 4px; color: var(--studio-accent-text); cursor: pointer; font-size: 0.85rem; padding: 0.5rem 1rem; }
        .btn-seed-all:hover:not(:disabled) { background: var(--studio-accent-bg-hover); }
        .btn-seed-all:disabled { opacity: 0.5; cursor: progress; }
        .seed-progress { color: var(--studio-text-muted); font-size: 0.8rem; }
        .loc-table-wrap { overflow-x: auto; border: 1px solid var(--studio-border-subtle); border-radius: 6px; }
        table.loc-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; min-width: 48rem; height: 1px; }
        .loc-table th, .loc-table td { border-bottom: 1px solid var(--studio-border-subtle); padding: 0.5rem 0.65rem; vertical-align: top; text-align: left; }
        .loc-table td:has(.cell-fill) { height: 100%; padding: 0; }
        .loc-table th { background: var(--studio-surface); color: var(--studio-text-muted); font-weight: 500; position: sticky; top: 0; }
        .loc-table tr:last-child td { border-bottom: none; }
        .key-cell { font-family: ui-monospace, monospace; font-size: 0.75rem; color: var(--studio-text-muted); white-space: nowrap; }
        .context-cell { color: var(--studio-text-muted); font-size: 0.75rem; max-width: 12rem; }
        .en-cell { color: var(--studio-text-secondary); background: var(--studio-surface-raised); }
        .cell-fill { display: flex; flex-direction: column; min-height: 100%; height: 100%; padding: 0.35rem 0.5rem; box-sizing: border-box; }
        .cell-input { flex: 1 1 auto; width: 100%; min-width: 8rem; min-height: 2.2rem; background: var(--studio-hover); border: 1px solid var(--studio-border); border-radius: 4px; color: var(--studio-text); font: inherit; padding: 0.35rem 0.5rem; resize: vertical; box-sizing: border-box; }
        .cell-input:focus { outline: none; border-color: var(--studio-text-muted); }
        .cell-input.is-seeded { border-color: var(--studio-warn-border); background: var(--studio-warn-bg); }
        .cell-input.is-saving { opacity: 0.6; }
        .cell-input.is-saved { border-color: var(--studio-success-border); }
        .cell-input.is-error { border-color: var(--studio-danger); }
    </style>
</head>
<body>
<?php Studio\StudioHeader::include(compact('baseUrl', 'syncStatus', 'isSyncing', 'activeNav')); ?>
<main>
    <h1>Localitzacions</h1>
    <p class="intro">Traduccions de la interfície del preview (reproductor, About, Participants). L'anglès és la font de referència. Subratllats en taronja apareixen els resultats de traducció automàtica. Si a un idioma li falta alguna traducció, no estarà disponible.</p>

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
    $tableLangs = in_array('en', $languageIds, true)
        ? array_merge(['en'], array_values(array_filter($languageIds, static fn(string $id): bool => $id !== 'en')))
        : $languageIds;
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
                            <?php foreach ($tableLangs as $langId): ?>
                                <th><?= $langId === 'en' ? 'Anglès' : htmlspecialchars($langId, ENT_QUOTES) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped[$section] as $row): ?>
                            <tr>
                                <td class="key-cell"><?= htmlspecialchars($row['key'], ENT_QUOTES) ?></td>
                                <td class="context-cell"><?= htmlspecialchars($row['context'], ENT_QUOTES) ?></td>
                                <?php foreach ($tableLangs as $langId): ?>
                                    <?php
                                    $val = $row['translations'][$langId] ?? '';
                                    $seeded = !empty($row['seeded'][$langId]);
                                    ?>
                                    <td<?= $langId === 'en' ? ' class="en-cell"' : '' ?>>
                                        <div class="cell-fill">
                                            <textarea
                                                class="cell-input<?= $seeded ? ' is-seeded' : '' ?>"
                                                data-key="<?= htmlspecialchars($row['key'], ENT_QUOTES) ?>"
                                                data-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>"
                                                rows="1"
                                            ><?= htmlspecialchars($val, ENT_QUOTES) ?></textarea>
                                        </div>
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

    function syncTextareaHeights() {
        document.querySelectorAll('.loc-table tbody tr').forEach(function (row) {
            var rowHeight = row.offsetHeight;
            row.querySelectorAll('.cell-fill').forEach(function (wrap) {
                wrap.style.minHeight = rowHeight + 'px';
            });
        });
    }

    function scheduleSyncTextareaHeights() {
        requestAnimationFrame(function () {
            requestAnimationFrame(syncTextareaHeights);
        });
    }

    scheduleSyncTextareaHeights();
    window.addEventListener('load', scheduleSyncTextareaHeights);
    window.addEventListener('resize', scheduleSyncTextareaHeights);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(scheduleSyncTextareaHeights);
    }

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
                    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                    .then(function (res) {
                        if (res.data.ok) { totalFilled += (res.data.filled || 0); }
                        else {
                            var msg = (res.data.errors && res.data.errors[0]) ? res.data.errors[0] : lang;
                            errors.push(lang + ': ' + msg);
                        }
                    })
                    .catch(function () { errors.push(lang + ': resposta invàlida'); })
                    .then(function () { i++; next(); });
            }

            next();
        });
    }
}());
</script>
</body>
</html>
