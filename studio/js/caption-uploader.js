/* caption-uploader.js — shared multi-file subtitle uploader */
(function () {
    'use strict';

    var MAX_FILE_BYTES = 5 * 1024 * 1024;
    var ACCEPT_RE = /\.(srt|vtt)$/i;

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function create(container, languages) {
        var detect = window.TranscriptionIntake.detectSubtitleLanguageFromFilename;
        var populateSelect = window.TranscriptionIntake.populateLanguageSelect;
        var entries = [];
        var onChangeCb = null;

        var dropzone = container.querySelector('.caption-uploader-dropzone');
        var fileInput = container.querySelector('.caption-uploader-file-input');
        var rowsEl = container.querySelector('.caption-uploader-rows');

        if (!dropzone || !fileInput || !rowsEl) {
            throw new Error('CaptionUploader: missing required markup');
        }

        fileInput.accept = '.srt,.vtt';
        fileInput.multiple = true;

        function notify() {
            if (typeof onChangeCb === 'function') onChangeCb();
        }

        function langIds() {
            return entries.map(function (e) { return e.langId; }).filter(Boolean);
        }

        function isValid() {
            if (!entries.length) return true;
            var seen = {};
            for (var i = 0; i < entries.length; i++) {
                var langId = entries[i].langId;
                if (!langId) return false;
                if (seen[langId]) return false;
                seen[langId] = true;
            }
            return true;
        }

        function getEntries() {
            return entries.map(function (e) {
                return { file: e.file, langId: e.langId };
            });
        }

        function clear() {
            entries = [];
            render();
            notify();
        }

        function removeAt(index) {
            entries.splice(index, 1);
            render();
            notify();
        }

        function setLangId(index, langId) {
            if (entries[index]) {
                entries[index].langId = langId;
                render();
                notify();
            }
        }

        function addFiles(files) {
            var rejected = [];
            var list = Array.from(files || []);
            list.forEach(function (file) {
                if (!ACCEPT_RE.test(file.name || '')) {
                    rejected.push({ file: file, reason: 'type' });
                    return;
                }
                if ((file.size || 0) > MAX_FILE_BYTES) {
                    rejected.push({ file: file, reason: 'size' });
                    return;
                }
                var detected = detect(file.name, languages);
                entries.push({ file: file, langId: detected || '' });
            });
            render();
            notify();
            return rejected;
        }

        function render() {
            rowsEl.innerHTML = '';
            entries.forEach(function (entry, index) {
                var row = document.createElement('div');
                row.className = 'caption-uploader-row';
                if (!entry.langId || langIds().filter(function (id) { return id === entry.langId; }).length > 1) {
                    row.classList.add('caption-uploader-row--invalid');
                }

                var nameEl = document.createElement('span');
                nameEl.className = 'caption-uploader-filename';
                nameEl.textContent = entry.file.name + ' (' + formatSize(entry.file.size || 0) + ')';

                var langSelect = document.createElement('select');
                langSelect.className = 'caption-uploader-lang';
                populateSelect(langSelect, languages, entry.langId);
                langSelect.addEventListener('change', function () {
                    setLangId(index, langSelect.value);
                });

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'caption-uploader-remove';
                removeBtn.title = 'Elimina';
                removeBtn.textContent = '×';
                removeBtn.addEventListener('click', function () { removeAt(index); });

                row.appendChild(nameEl);
                row.appendChild(langSelect);
                row.appendChild(removeBtn);
                rowsEl.appendChild(row);
            });
        }

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
            if (e.dataTransfer && e.dataTransfer.files) {
                addFiles(e.dataTransfer.files);
            }
        });
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length) {
                addFiles(fileInput.files);
            }
            fileInput.value = '';
        });

        return {
            getEntries: getEntries,
            clear: clear,
            isValid: isValid,
            onChange: function (cb) { onChangeCb = cb; },
            addFiles: addFiles,
            setLangId: setLangId,
            removeAt: removeAt,
        };
    }

    window.CaptionUploader = { create: create };
}());
