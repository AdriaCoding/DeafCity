/* transcription-intake.js — auto-detect language from filename suffix */
(function () {
    'use strict';

    var AUDIO_EXTENSIONS = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'flac', 'webm', 'wma', 'mp4', 'opus'];

    function fileExtension(filename) {
        var match = filename.replace(/^.*[\\/]/, '').match(/\.([^.]+)$/);
        return match ? match[1].toLowerCase() : '';
    }

    function isSubtitleFile(filename) {
        var ext = fileExtension(filename);
        return ext === 'vtt' || ext === 'srt';
    }

    function isAudioFile(filename) {
        var ext = fileExtension(filename);
        return AUDIO_EXTENSIONS.indexOf(ext) !== -1;
    }

    function buildLanguageCodes(languages) {
        var codes = [];
        languages.forEach(function (lang) {
            var id = (lang.id || '').toLowerCase();
            if (id) {
                codes.push({ langId: lang.id, code: id, len: id.length });
            }
        });

        codes.sort(function (a, b) {
            return b.len - a.len;
        });

        return codes;
    }

    function matchCodeAt(stem, start, codes) {
        for (var i = 0; i < codes.length; i++) {
            var entry = codes[i];
            if (stem.substr(start, entry.len) === entry.code) {
                return entry;
            }
        }
        return null;
    }

    /**
     * Filename suffix → initial dropdown value only.
     * Prefers source→target pairs (e.g. talk_ES_EN / talk_ES-EN → es) over a lone
     * trailing code, so the English target half does not seed the source dropdown.
     */
    function detectSubtitleLanguageFromFilename(filename, languages) {
        var stem = filename.replace(/^.*[\\/]/, '').replace(/\.[^.]+$/, '').toLowerCase();
        if (!stem) {
            return null;
        }

        var codes = buildLanguageCodes(languages);
        if (!codes.length) {
            return null;
        }

        // Pair suffix: …[_\-.]{src}[_\-]{tgt} → source language
        for (var srcIdx = 0; srcIdx < codes.length; srcIdx++) {
            var src = codes[srcIdx];
            for (var tgtIdx = 0; tgtIdx < codes.length; tgtIdx++) {
                var tgt = codes[tgtIdx];
                var pairLen = src.len + 1 + tgt.len;
                if (stem.length < pairLen + 1) {
                    continue;
                }
                var pairStart = stem.length - pairLen;
                var sepBefore = stem.charAt(pairStart - 1);
                if (sepBefore !== '_' && sepBefore !== '-' && sepBefore !== '.') {
                    continue;
                }
                if (stem.substr(pairStart, src.len) !== src.code) {
                    continue;
                }
                var mid = stem.charAt(pairStart + src.len);
                if (mid !== '_' && mid !== '-') {
                    continue;
                }
                if (stem.substr(pairStart + src.len + 1, tgt.len) !== tgt.code) {
                    continue;
                }
                return src.langId;
            }
        }

        for (var len = 3; len >= 2; len--) {
            if (stem.length < len) {
                continue;
            }
            var matched = matchCodeAt(stem, stem.length - len, codes.filter(function (c) {
                return c.len === len;
            }));
            if (matched) {
                return matched.langId;
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

    function showBulkError(bulkContainer, message) {
        bulkContainer.style.display = '';
        bulkContainer.innerHTML = '';
        var error = document.createElement('p');
        error.className = 'error';
        error.textContent = message;
        bulkContainer.appendChild(error);
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

        for (var j = 0; j < files.length; j++) {
            if (!isAudioFile(files[j].name) && !isSubtitleFile(files[j].name)) {
                singleField.style.display = '';
                bulkContainer.style.display = '';
                if (templateSelect) {
                    templateSelect.required = true;
                }
                showBulkError(
                    bulkContainer,
                    'Format no reconegut. En mode massa, només s\'accepten fitxers d\'àudio o subtítols.'
                );
                return 'bulk-error';
            }
        }

        singleField.style.display = 'none';
        bulkContainer.style.display = '';
        if (templateSelect) {
            templateSelect.required = false;
        }

        var table = document.createElement('table');
        table.className = 'bulk-table';
        table.innerHTML = '<thead><tr><th>Fitxer</th><th>Llengua d\'entrada</th></tr></thead>';
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
        var form = document.getElementById('intake-form');
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

        if (form) {
            form.addEventListener('submit', function (event) {
                var files = fileInput.files;
                if (!files || files.length < 2) {
                    return;
                }
                for (var i = 0; i < files.length; i++) {
                    if (!isAudioFile(files[i].name) && !isSubtitleFile(files[i].name)) {
                        event.preventDefault();
                        renderBulkTable(fileInput, languages, singleField, bulkContainer, languageSelect);
                        return;
                    }
                }
            });
        }

        fileInput.addEventListener('change', onFileChange);
    }

    window.TranscriptionIntake = {
        fileExtension: fileExtension,
        isSubtitleFile: isSubtitleFile,
        isAudioFile: isAudioFile,
        detectSubtitleLanguageFromFilename: detectSubtitleLanguageFromFilename,
        initIntakeLanguageDetection: initIntakeLanguageDetection,
        renderBulkTable: renderBulkTable,
        populateLanguageSelect: populateLanguageSelect,
    };
}());
