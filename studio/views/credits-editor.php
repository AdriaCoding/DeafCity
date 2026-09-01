<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crèdits — Studio DEAF.city</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: var(--studio-bg); font-family: var(--studio-font); color: var(--studio-text); }
        main { padding: 2rem clamp(1rem, 3vw, 2.5rem) 4rem; }
        h1 { font-size: 1.35rem; margin-bottom: 0.5rem; }
        .intro { color: var(--studio-text-muted); font-size: 0.9rem; margin-bottom: 1.5rem; max-width: 60rem; line-height: 1.5; }
        .editor-toolbar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 0.75rem; }
        .btn-save { background: var(--studio-btn-green-bg); border: 1px solid var(--studio-btn-green-border); border-radius: 4px; color: var(--studio-btn-green-text); cursor: pointer; font-size: 0.85rem; padding: 0.5rem 1.1rem; }
        .btn-save:hover:not(:disabled) { background: var(--studio-btn-green-bg-hover); }
        .btn-save:disabled { opacity: 0.5; cursor: progress; }
        .btn-revert { background: transparent; border: 1px solid var(--studio-border); border-radius: 4px; color: var(--studio-text-secondary); cursor: pointer; font-size: 0.85rem; padding: 0.5rem 1.1rem; }
        .btn-revert:hover:not(:disabled) { background: var(--studio-hover); }
        .btn-revert:disabled { opacity: 0.4; cursor: not-allowed; }
        .editor-status { font-size: 0.82rem; color: var(--studio-text-muted); }
        .editor-status.is-ok { color: var(--studio-success-text); }
        .editor-status.is-error { color: var(--studio-danger); white-space: pre-wrap; }
        #cm-editor { border: 1px solid var(--studio-border-subtle); border-radius: 6px; overflow: hidden; }
        .cm-editor { height: 70vh; font-size: 0.85rem; }
        .cm-editor.cm-focused { outline: none; }
        .cm-scroller { overflow: auto; }
    </style>
</head>
<body>
<?php Studio\StudioHeader::include(compact('baseUrl', 'syncStatus', 'isSyncing', 'activeNav')); ?>
<main>
    <h1>Crèdits</h1>
    <p class="intro">
        Edita el text dels crèdits que apareix a la pàgina About
        (<code>views/about/credits_body.html</code>). És HTML, no PHP: no s'hi pot posar codi.
        Els números i les etiquetes traduïdes s'omplen sols amb aquests marcadors:
        <code>{{count.participants}}</code>, <code>{{count.videos}}</code>,
        <code>{{count.deaf_hearing_pct}}</code>, <code>{{label.supported_by}}</code>,
        <code>{{label.participants}}</code>, <code>{{label.interpreter}}</code>,
        <code>{{label.interpreters}}</code>, <code>{{label.coordination}}</code>,
        <code>{{label.collaboration}}</code>, <code>{{label.thanks_to}}</code>,
        <code>{{project_by}}</code>, <code>{{contact}}</code>.
        En desar es comprova el contingut i, si hi ha algun error, no es desa res. Cada vegada que deses
        correctament es guarda la versió anterior; el botó "Desfés" la recupera.
    </p>

    <div class="editor-toolbar">
        <button type="button" class="btn-save" id="save-btn">Desa</button>
        <button type="button" class="btn-revert" id="revert-btn"<?= $hasBackup ? '' : ' disabled' ?>>Desfés (torna a l'anterior)</button>
        <span class="editor-status" id="editor-status" aria-live="polite"></span>
    </div>

    <div id="cm-editor"></div>
</main>
<script type="module">
import { EditorView, basicSetup } from 'https://esm.sh/codemirror@6.0.1';
import { html } from 'https://esm.sh/@codemirror/lang-html@6.4.9';

const initialContent = <?= json_encode($fileContent, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;

const view = new EditorView({
    doc: initialContent,
    extensions: [basicSetup, html()],
    parent: document.getElementById('cm-editor'),
});

const saveBtn = document.getElementById('save-btn');
const revertBtn = document.getElementById('revert-btn');
const statusEl = document.getElementById('editor-status');

function setStatus(text, cls) {
    statusEl.textContent = text;
    statusEl.className = 'editor-status' + (cls ? ' ' + cls : '');
}

saveBtn.addEventListener('click', function () {
    saveBtn.disabled = true;
    setStatus('Desant…', '');

    const body = new FormData();
    body.append('content', view.state.doc.toString());

    fetch('?action=credits-editor-save', { method: 'POST', body: body })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
            saveBtn.disabled = false;
            if (res.data.ok) {
                setStatus('Desat.', 'is-ok');
                revertBtn.disabled = false;
                setTimeout(function () { setStatus('', ''); }, 2500);
            } else {
                setStatus(res.data.error || 'Error en desar.', 'is-error');
            }
        })
        .catch(function () {
            saveBtn.disabled = false;
            setStatus('Error de connexió en desar.', 'is-error');
        });
});

revertBtn.addEventListener('click', function () {
    if (!window.confirm('Recuperar la versió anterior i substituir el que hi ha ara al fitxer?')) {
        return;
    }
    revertBtn.disabled = true;
    setStatus('Recuperant…', '');

    fetch('?action=credits-editor-revert', { method: 'POST' })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
            revertBtn.disabled = false;
            if (res.data.ok) {
                view.dispatch({
                    changes: { from: 0, to: view.state.doc.length, insert: res.data.content },
                });
                setStatus('Recuperat.', 'is-ok');
                setTimeout(function () { setStatus('', ''); }, 2500);
            } else {
                setStatus(res.data.error || 'Error en recuperar.', 'is-error');
            }
        })
        .catch(function () {
            revertBtn.disabled = false;
            setStatus('Error de connexió en recuperar.', 'is-error');
        });
});
</script>
</body>
</html>
