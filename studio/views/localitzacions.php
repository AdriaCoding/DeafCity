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
        .section-toolbar { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
        .btn-seed { background: #1a3a6e; border: 1px solid #2a5090; border-radius: 4px; color: #9ab8ff; cursor: pointer; font-size: 0.8rem; padding: 0.35rem 0.75rem; }
        .btn-seed:hover { background: #1f4580; }
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
            <div class="section-toolbar">
                <?php foreach ($editableLangs as $langId): ?>
                    <button type="button" class="btn-seed" data-seed-lang="<?= htmlspecialchars($langId, ENT_QUOTES) ?>" data-seed-section="<?= htmlspecialchars($section, ENT_QUOTES) ?>">
                        Omple buits (<?= htmlspecialchars($langId, ENT_QUOTES) ?>)
                    </button>
                <?php endforeach; ?>
            </div>
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

    document.querySelectorAll('.btn-seed').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var lang = btn.getAttribute('data-seed-lang');
            var section = btn.getAttribute('data-seed-section');
            btn.disabled = true;
            var body = new FormData();
            body.append('lang', lang);
            body.append('section', section);
            fetch('?action=localitzacions-seed', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    if (data.ok) {
                        window.location.reload();
                    } else {
                        alert((data.errors && data.errors[0]) || 'Error en la traducció automàtica.');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    alert('Error de xarxa.');
                });
        });
    });
}());
</script>
</body>
</html>
