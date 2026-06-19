/* caption-table.js — unified captions table: persisted rows + pending uploads + master picker.
 *
 * Modes:
 *   'intake'  — pending uploads only; component owns master selection and applies the
 *               default rule (1 file → master; many → English; otherwise none/user-must-choose).
 *   'detail'  — persisted rows (label/file/download/edit/replace/delete) + pending uploads;
 *               master changes and row actions are delegated to host callbacks.
 *
 * CaptionTable.create(container, languages, options) → instance
 *   options: { mode, existingCaptions:[{lang,file}], master, requireMaster, vimeoId,
 *              onChange, onMasterChange(lang), onEdit(lang,btn,row),
 *              onReplace(lang,btn,row), onDelete(lang,btn,row) }
 */
(function () {
    'use strict';

    var MAX_FILE_BYTES = 5 * 1024 * 1024;
    var ACCEPT_RE = /\.(srt|vtt)$/i;
    var MASTER_RADIO_NAME = 'master_caption';

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function create(container, languages, options) {
        options = options || {};
        var detect = window.TranscriptionIntake.detectSubtitleLanguageFromFilename;
        var populateSelect = window.TranscriptionIntake.populateLanguageSelect;

        var mode = options.mode === 'detail' ? 'detail' : 'intake';
        var vimeoId = options.vimeoId || '';
        var onChangeCb = typeof options.onChange === 'function' ? options.onChange : null;

        var langLabelById = {};
        languages.forEach(function (l) { langLabelById[l.id] = l.label || l.id; });

        var existing = (options.existingCaptions || []).map(function (c) {
            return { lang: c.lang, file: c.file || '' };
        });
        var pending = [];                       // { file, langId }
        var master = options.master || '';
        var userPickedMaster = !!options.master;
        var requireMaster = !!options.requireMaster;

        container.classList.add('caption-table-component');

        var tableFeedback = document.createElement('div');
        tableFeedback.className = 'caption-table-feedback';
        tableFeedback.id = 'caption-table-feedback';

        var table = document.createElement('table');
        table.className = 'caption-table';
        table.id = 'caption-tracks-table';
        var thead = document.createElement('thead');
        thead.innerHTML = '<tr><th class="caption-master-cell">Master</th><th>Idioma</th>' +
            '<th>Fitxer al servidor</th><th>Descàrrega</th><th>Accions</th></tr>';
        var tbody = document.createElement('tbody');
        table.appendChild(thead);
        table.appendChild(tbody);

        var masterFeedback = document.createElement('div');
        masterFeedback.className = 'caption-master-feedback';
        masterFeedback.id = 'master-caption-feedback';

        var dropzone = document.createElement('div');
        dropzone.className = 'caption-uploader-dropzone';
        dropzone.textContent = 'Arrossegueu fitxers .srt o .vtt aquí, o feu clic per triar';

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.className = 'caption-uploader-file-input';
        fileInput.hidden = true;
        fileInput.multiple = true;
        fileInput.accept = '.srt,.vtt';

        container.appendChild(tableFeedback);
        container.appendChild(table);
        container.appendChild(masterFeedback);
        container.appendChild(dropzone);
        container.appendChild(fileInput);

        function notify() { if (onChangeCb) onChangeCb(); }

        function presentLangs() {
            return existing.map(function (e) { return e.lang; })
                .concat(pending.map(function (p) { return p.langId; }).filter(Boolean));
        }

        function pendingLangCount(id) {
            return pending.filter(function (p) { return p.langId === id; }).length;
        }

        function applyAutoMaster() {
            if (mode !== 'intake') return;
            var present = presentLangs();
            if (userPickedMaster && master && present.indexOf(master) !== -1) return;
            if (present.length === 1) {
                master = present[0];
            } else if (present.indexOf('en') !== -1) {
                master = 'en';
            } else {
                master = '';
            }
            userPickedMaster = false;
        }

        function isValid() {
            var seen = {};
            for (var i = 0; i < pending.length; i++) {
                var id = pending[i].langId;
                if (!id) return false;
                if (seen[id]) return false;       // two pending files, same language
                seen[id] = true;
            }
            if (requireMaster) {
                if (!master) return false;
                if (presentLangs().indexOf(master) === -1) return false;
            }
            return true;
        }

        function getEntries() {
            return pending.map(function (p) { return { file: p.file, langId: p.langId }; });
        }

        function getMasterLang() { return master; }

        function buildMasterRadio(langId, enabled) {
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.className = 'caption-master-radio';
            radio.name = MASTER_RADIO_NAME;
            radio.value = langId || '';
            radio.title = 'Definir com a subtítol mestre';
            radio.disabled = !enabled || !langId;
            radio.checked = !!langId && master === langId;
            return radio;
        }

        function buildExistingRow(cap) {
            var langId = cap.lang || '';
            var tr = document.createElement('tr');
            tr.dataset.lang = langId;

            var tdMaster = document.createElement('td');
            tdMaster.className = 'caption-master-cell';
            var radio = buildMasterRadio(langId, true);
            radio.addEventListener('change', function () {
                if (!radio.checked) return;
                master = langId;
                userPickedMaster = true;
                if (typeof options.onMasterChange === 'function') options.onMasterChange(langId);
                notify();
            });
            tdMaster.appendChild(radio);

            var tdLang = document.createElement('td');
            tdLang.textContent = langLabelById[langId] || langId;

            var tdFile = document.createElement('td');
            tdFile.className = 'caption-file-cell';
            tdFile.textContent = cap.file || '';

            var tdDl = document.createElement('td');
            if (cap.file && vimeoId) {
                var wrap = document.createElement('div');
                wrap.className = 'caption-download-btns';
                ['vtt', 'srt'].forEach(function (fmt) {
                    var a = document.createElement('a');
                    a.className = 'caption-download-btn';
                    a.href = '?action=continguts-download-caption-' + fmt +
                        '&vimeo_id=' + encodeURIComponent(vimeoId) + '&lang=' + encodeURIComponent(langId);
                    a.innerHTML = '<span class="material-icons">download</span>' + fmt.toUpperCase();
                    wrap.appendChild(a);
                });
                tdDl.appendChild(wrap);
            }

            var tdAct = document.createElement('td');
            tdAct.className = 'caption-actions-cell';
            tdAct.appendChild(buildExistingActions(langId, tr));

            tr.appendChild(tdMaster);
            tr.appendChild(tdLang);
            tr.appendChild(tdFile);
            tr.appendChild(tdDl);
            tr.appendChild(tdAct);
            return tr;
        }

        function buildActionButton(cls, icon, label, danger) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'caption-action-btn' + (danger ? ' danger' : '') + ' ' + cls;
            btn.innerHTML = '<span class="material-icons" aria-hidden="true">' + icon +
                '</span><span class="caption-action-label">' + label + '</span>';
            return btn;
        }

        function buildExistingActions(langId, row) {
            var wrap = document.createElement('div');
            wrap.className = 'caption-action-btns';
            var defs = [
                ['caption-edit-btn', 'edit', 'Edita', false, options.onEdit],
                ['caption-replace-btn', 'upload', 'Reemplaça', false, options.onReplace],
                ['caption-delete-btn', 'delete', 'Elimina', true, options.onDelete],
            ];
            defs.forEach(function (d) {
                if (typeof d[4] !== 'function') return;
                var btn = buildActionButton(d[0], d[1], d[2], d[3]);
                btn.addEventListener('click', function () { d[4](langId, btn, row); });
                wrap.appendChild(btn);
            });
            return wrap;
        }

        function buildPendingRow(entry, index) {
            var tr = document.createElement('tr');
            tr.className = 'caption-pending-row';
            if (!entry.langId || pendingLangCount(entry.langId) > 1) {
                tr.classList.add('caption-row--invalid');
            }

            var tdMaster = document.createElement('td');
            tdMaster.className = 'caption-master-cell';
            if (mode === 'intake') {
                var radio = buildMasterRadio(entry.langId, true);
                radio.addEventListener('change', function () {
                    if (!entry.langId || !radio.checked) return;
                    master = entry.langId;
                    userPickedMaster = true;
                    render();
                    notify();
                });
                tdMaster.appendChild(radio);
            }

            var tdLang = document.createElement('td');
            var select = document.createElement('select');
            select.className = 'caption-lang-select';
            populateSelect(select, languages, entry.langId);
            select.addEventListener('change', function () { setLangId(index, select.value); });
            tdLang.appendChild(select);

            var tdFile = document.createElement('td');
            tdFile.className = 'caption-file-cell';
            tdFile.textContent = entry.file.name + ' (' + formatSize(entry.file.size || 0) + ')';

            var tdDl = document.createElement('td');

            var tdAct = document.createElement('td');
            tdAct.className = 'caption-actions-cell';
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'caption-action-btn caption-remove-pending';
            rm.title = 'Elimina';
            rm.textContent = '×';
            rm.addEventListener('click', function () { removeAt(index); });
            tdAct.appendChild(rm);

            tr.appendChild(tdMaster);
            tr.appendChild(tdLang);
            tr.appendChild(tdFile);
            tr.appendChild(tdDl);
            tr.appendChild(tdAct);
            return tr;
        }

        function render() {
            tbody.innerHTML = '';
            existing.forEach(function (cap) { tbody.appendChild(buildExistingRow(cap)); });
            pending.forEach(function (entry, i) { tbody.appendChild(buildPendingRow(entry, i)); });
            var empty = existing.length === 0 && pending.length === 0;
            thead.style.display = empty ? 'none' : '';
        }

        function setLangId(index, langId) {
            if (!pending[index]) return;
            pending[index].langId = langId;
            applyAutoMaster();
            render();
            notify();
        }

        function removeAt(index) {
            pending.splice(index, 1);
            applyAutoMaster();
            render();
            notify();
        }

        function addFiles(files) {
            var rejected = [];
            Array.from(files || []).forEach(function (file) {
                if (!ACCEPT_RE.test(file.name || '')) { rejected.push({ file: file, reason: 'type' }); return; }
                if ((file.size || 0) > MAX_FILE_BYTES) { rejected.push({ file: file, reason: 'size' }); return; }
                pending.push({ file: file, langId: detect(file.name, languages) || '' });
            });
            applyAutoMaster();
            render();
            notify();
            return rejected;
        }

        function clear() {
            pending = [];
            applyAutoMaster();
            render();
            notify();
        }

        // ── Host-facing mutators (detail mode) ──────────────────────────────
        function setExisting(captions, newMaster) {
            existing = (captions || []).map(function (c) { return { lang: c.lang, file: c.file || '' }; });
            if (typeof newMaster === 'string' && newMaster !== '') master = newMaster;
            pending = [];
            render();
            notify();
        }
        function removeExisting(lang) {
            existing = existing.filter(function (e) { return e.lang !== lang; });
            render();
        }
        function updateExistingFile(lang, file) {
            existing.forEach(function (e) { if (e.lang === lang) e.file = file; });
            render();
        }
        function setMaster(lang) { master = lang; userPickedMaster = true; render(); }
        // No notify(): this is a host-driven flag; the host updates its own UI after calling it.
        // Firing onChange here would recurse when the host's onChange handler calls setRequireMaster.
        function setRequireMaster(value) { requireMaster = !!value; }

        dropzone.addEventListener('click', function () { fileInput.click(); });
        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.classList.add('caption-uploader-dropzone--active');
        });
        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('caption-uploader-dropzone--active');
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('caption-uploader-dropzone--active');
            if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length) addFiles(fileInput.files);
            fileInput.value = '';
        });

        applyAutoMaster();
        render();

        return {
            getEntries: getEntries,
            getMasterLang: getMasterLang,
            isValid: isValid,
            setRequireMaster: setRequireMaster,
            onChange: function (cb) { onChangeCb = cb; },
            addFiles: addFiles,
            setLangId: setLangId,
            removeAt: removeAt,
            clear: clear,
            setExisting: setExisting,
            removeExisting: removeExisting,
            updateExistingFile: updateExistingFile,
            setMaster: setMaster,
            getExistingLangs: function () { return existing.map(function (e) { return e.lang; }); },
        };
    }

    window.CaptionTable = { create: create };
}());
