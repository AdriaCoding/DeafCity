/* transcription-intake.js — auto-detect audio language from filename suffix */
(function () {
    'use strict';

    function detectSubtitleLanguageFromFilename(filename, languages) {
        var stem = filename.replace(/^.*[\\/]/, '').replace(/\.[^.]+$/, '').toLowerCase();
        if (!stem) {
            return null;
        }

        var codes = [];
        languages.forEach(function (lang) {
            var id = (lang.id || '').toLowerCase();
            var vimeo = (lang.vimeo_code || '').toLowerCase();
            if (id) {
                codes.push({ langId: lang.id, code: id, type: 'id', len: id.length });
            }
            if (vimeo && vimeo !== id) {
                codes.push({ langId: lang.id, code: vimeo, type: 'vimeo', len: vimeo.length });
            }
        });

        codes.sort(function (a, b) {
            if (b.len !== a.len) {
                return b.len - a.len;
            }
            if (a.type !== b.type) {
                return a.type === 'id' ? -1 : 1;
            }
            return 0;
        });

        for (var len = 3; len >= 2; len--) {
            if (stem.length < len) {
                continue;
            }
            var suffix = stem.slice(-len);
            for (var i = 0; i < codes.length; i++) {
                if (codes[i].len === len && codes[i].code === suffix) {
                    return codes[i].langId;
                }
            }
        }

        return null;
    }

    function populateLanguageSelect(select, languages, selected) {
        while (select.firstChild) {
            select.removeChild(select.firstChild);
        }

        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Seleccioneu…';
        select.appendChild(empty);

        languages.forEach(function (lang) {
            var opt = document.createElement('option');
            opt.value = lang.id || '';
            opt.textContent = lang.label || lang.id || '';
            if (lang.id === selected) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    function renderBulkTable(fileInput, languages, singleField, bulkContainer, templateSelect) {
        var files = fileInput.files;
        if (!files || files.length < 2) {
            singleField.style.display = '';
            bulkContainer.innerHTML = '';
            bulkContainer.style.display = 'none';
            if (templateSelect) {
                templateSelect.required = true;
            }
            return 'single';
        }

        singleField.style.display = 'none';
        bulkContainer.style.display = '';
        if (templateSelect) {
            templateSelect.required = false;
        }

        var table = document.createElement('table');
        table.className = 'bulk-table';
        table.innerHTML = '<thead><tr><th>Fitxer</th><th>Llengua</th></tr></thead>';
        var tbody = document.createElement('tbody');

        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var detected = detectSubtitleLanguageFromFilename(file.name, languages);
            var tr = document.createElement('tr');
            var tdName = document.createElement('td');
            tdName.textContent = file.name;
            var tdLang = document.createElement('td');
            var select = document.createElement('select');
            select.name = 'bulk_languages[' + i + ']';
            select.required = true;
            populateLanguageSelect(select, languages, detected);
            tdLang.appendChild(select);
            tr.appendChild(tdName);
            tr.appendChild(tdLang);
            tbody.appendChild(tr);
        }

        table.appendChild(tbody);
        bulkContainer.innerHTML = '';
        bulkContainer.appendChild(table);
        return 'bulk';
    }

    function initIntakeLanguageDetection(languages) {
        var fileInput = document.getElementById('intake_file');
        var languageSelect = document.getElementById('subtitle_language');
        var singleField = document.getElementById('single-language-field');
        var bulkContainer = document.getElementById('bulk-language-table');
        if (!fileInput || !languageSelect || !Array.isArray(languages)) {
            return;
        }

        function onFileChange() {
            var files = fileInput.files;
            if (!files || files.length === 0) {
                return;
            }

            if (files.length === 1) {
                renderBulkTable(fileInput, languages, singleField, bulkContainer, languageSelect);
                var detected = detectSubtitleLanguageFromFilename(files[0].name, languages);
                if (detected && [].some.call(languageSelect.options, function (opt) { return opt.value === detected; })) {
                    languageSelect.value = detected;
                }
                return;
            }

            renderBulkTable(fileInput, languages, singleField, bulkContainer, languageSelect);
        }

        fileInput.addEventListener('change', onFileChange);
    }

    window.TranscriptionIntake = {
        detectSubtitleLanguageFromFilename: detectSubtitleLanguageFromFilename,
        initIntakeLanguageDetection: initIntakeLanguageDetection,
        renderBulkTable: renderBulkTable,
        populateLanguageSelect: populateLanguageSelect,
    };
}());
