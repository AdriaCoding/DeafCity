/* translation-review.js — vanilla JS, no build step */
(function () {
    'use strict';

    var formatTime    = CaptionUtils.formatTime;
    var parseTime     = CaptionUtils.parseTime;

    let masterCues = window.__masterCues || [];
    let translatedCues = JSON.parse(JSON.stringify(window.__translatedCues || []));
    let savedSnapshot = JSON.stringify(translatedCues);

    let cueList, saveBtn, saveError, downloadVttBtn, downloadSrtBtn;

    function updateOverlapHighlights() {
        CaptionUtils.updateOverlapHighlights(translatedCues, '.pair-card');
    }

    function showCueErrors(cueErrors) {
        document.querySelectorAll('.pair-card').forEach(function (card) {
            card.classList.remove('cue-error');
        });
        if (!cueErrors || cueErrors.length === 0) return;
        const errorIndices = new Set(cueErrors.map(function (e) { return e.cueIndex; }));
        document.querySelectorAll('.pair-card').forEach(function (card, idx) {
            if (errorIndices.has(idx)) card.classList.add('cue-error');
        });
    }

    function buildTimestampField(cue, idx, field) {
        const wrap = document.createElement('span');
        wrap.className = 'ts-wrap';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'cue-ts';
        input.value = formatTime(cue[field]);
        input.addEventListener('change', function () {
            const val = parseTime(input.value);
            if (!isNaN(val)) {
                translatedCues[idx][field] = val;
                input.value = formatTime(val);
            }
            updateOverlapHighlights();
        });
        wrap.appendChild(input);

        return wrap;
    }

    function buildPairCard(masterCue, translatedCue, idx) {
        const card = document.createElement('div');
        card.className = 'pair-card pair-columns';
        card.dataset.idx = idx;

        const badge = document.createElement('span');
        badge.className = 'pair-index';
        badge.textContent = idx + 1;
        card.appendChild(badge);

        // Original block
        const origBlock = document.createElement('div');
        origBlock.className = 'orig-block';

        if (masterCue) {
            const origTs = document.createElement('span');
            origTs.className = 'orig-ts';
            origTs.textContent = formatTime(masterCue.start) + ' → ' + formatTime(masterCue.end);
            origBlock.appendChild(origTs);

            const origText = document.createElement('p');
            origText.className = 'orig-text';
            origText.textContent = masterCue.text;
            origBlock.appendChild(origText);
        } else {
            const empty = document.createElement('span');
            empty.className = 'orig-empty';
            empty.textContent = '—';
            origBlock.appendChild(empty);
        }
        card.appendChild(origBlock);

        // Translated block
        const transBlock = document.createElement('div');
        transBlock.className = 'trans-block';

        if (translatedCue) {
            const tsRow = document.createElement('div');
            tsRow.className = 'ts-row';
            tsRow.appendChild(buildTimestampField(translatedCue, idx, 'start'));
            const arrow = document.createElement('span');
            arrow.className = 'cue-arrow';
            arrow.textContent = '→';
            tsRow.appendChild(arrow);
            tsRow.appendChild(buildTimestampField(translatedCue, idx, 'end'));
            transBlock.appendChild(tsRow);

            const textarea = document.createElement('textarea');
            textarea.className = 'cue-text';
            textarea.rows = 2;
            textarea.value = translatedCue.text;
            textarea.addEventListener('input', function () {
                translatedCues[idx].text = textarea.value;
            });
            transBlock.appendChild(textarea);
        } else {
            const empty = document.createElement('span');
            empty.className = 'orig-empty';
            empty.textContent = '—';
            transBlock.appendChild(empty);
        }
        card.appendChild(transBlock);

        return card;
    }

    function render() {
        cueList.innerHTML = '';
        const count = Math.max(masterCues.length, translatedCues.length);
        for (let i = 0; i < count; i++) {
            cueList.appendChild(buildPairCard(masterCues[i] || null, translatedCues[i] || null, i));
        }
        updateOverlapHighlights();
    }

    function isDirty() {
        return JSON.stringify(translatedCues) !== savedSnapshot;
    }

    function downloadVtt() {
        var lang = window.__lang || '';
        var base = (window.__vimeoId || 'draft') + (lang ? '_' + lang.toUpperCase() : '');
        CaptionUtils.triggerDownload(CaptionUtils.generateVtt(translatedCues), base + '.vtt', 'text/vtt');
    }

    function downloadSrt() {
        var lang = window.__lang || '';
        var base = (window.__vimeoId || 'draft') + (lang ? '_' + lang.toUpperCase() : '');
        CaptionUtils.triggerDownload(CaptionUtils.generateSrt(translatedCues), base + '.srt', 'application/x-subrip');
    }

    function save() {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Desant…';
        if (saveError) saveError.hidden = true;

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ cues: translatedCues }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    savedSnapshot = JSON.stringify(translatedCues);
                    window.location.href = '?action=translation';
                } else {
                    if (saveError) {
                        saveError.textContent = (data.errors || ['Error desconegut.']).join('\n');
                        saveError.hidden = false;
                    }
                    showCueErrors(data.cueErrors);
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Desa i tanca';
                }
            })
            .catch(function () {
                if (saveError) {
                    saveError.textContent = 'Error de xarxa. Torneu-ho a provar.';
                    saveError.hidden = false;
                }
                saveBtn.disabled = false;
                saveBtn.textContent = 'Desa i tanca';
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        cueList = document.getElementById('cue-list');
        saveBtn = document.getElementById('save-btn');
        saveError = document.getElementById('save-error');
        downloadVttBtn = document.getElementById('download-vtt-btn');
        downloadSrtBtn = document.getElementById('download-srt-btn');

        render();

        saveBtn.addEventListener('click', save);
        if (downloadVttBtn) downloadVttBtn.addEventListener('click', downloadVtt);
        if (downloadSrtBtn) downloadSrtBtn.addEventListener('click', downloadSrt);

        window.addEventListener('beforeunload', function (e) {
            if (isDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    });
})();
