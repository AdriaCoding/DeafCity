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

    function initIntakeLanguageDetection(languages) {
        var fileInput = document.getElementById('intake_file');
        var languageSelect = document.getElementById('subtitle_language');
        if (!fileInput || !languageSelect || !Array.isArray(languages)) {
            return;
        }

        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file) {
                return;
            }

            var detected = detectSubtitleLanguageFromFilename(file.name, languages);
            if (!detected) {
                return;
            }

            if ([].some.call(languageSelect.options, function (opt) { return opt.value === detected; })) {
                languageSelect.value = detected;
            }
        });
    }

    window.TranscriptionIntake = {
        detectSubtitleLanguageFromFilename: detectSubtitleLanguageFromFilename,
        initIntakeLanguageDetection: initIntakeLanguageDetection,
    };
}());
