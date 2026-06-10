/* continguts-caption-editor.js — catalog caption editor (iframe embed) */
(function () {
    'use strict';

    var formatTime = CaptionUtils.formatTime;
    var parseTime = CaptionUtils.parseTime;

    var masterCues = window.__masterCues || [];
    var translatedCues = JSON.parse(JSON.stringify(window.__translatedCues || []));
    var savedSnapshot = JSON.stringify(translatedCues);

    var cueList, saveBtn, cancelBtn, saveError, downloadVttBtn, downloadSrtBtn;
    var exitingWithoutSave = false;

    function setButtonLoading(btn, loading, label) {
        if (!btn) return;
        var labelEl = btn.querySelector('.btn-label');
        if (loading) {
            if (labelEl && label) {
                if (!btn.dataset.defaultLabel) {
                    btn.dataset.defaultLabel = labelEl.textContent;
                }
                labelEl.textContent = label;
            }
            btn.classList.add('is-loading');
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
        } else {
            if (labelEl && btn.dataset.defaultLabel) {
                labelEl.textContent = btn.dataset.defaultLabel;
            }
            btn.classList.remove('is-loading');
            btn.disabled = false;
            btn.setAttribute('aria-busy', 'false');
        }
    }

    function setEditorBusy(loading, activeBtn, activeLabel) {
        document.body.classList.toggle('editor-busy', loading);
        [saveBtn, cancelBtn, downloadVttBtn, downloadSrtBtn].forEach(function (btn) {
            if (!btn) return;
            if (loading && btn === activeBtn) {
                setButtonLoading(btn, true, activeLabel);
            } else if (loading) {
                btn.disabled = true;
                btn.classList.remove('is-loading');
                btn.setAttribute('aria-busy', 'false');
            } else {
                setButtonLoading(btn, false);
            }
        });
    }

    function updateOverlapHighlights() {
        CaptionUtils.updateOverlapHighlights(translatedCues, '.pair-card');
    }

    function showCueErrors(cueErrors) {
        document.querySelectorAll('.pair-card').forEach(function (card) {
            card.classList.remove('cue-error');
        });
        if (!cueErrors || cueErrors.length === 0) return;
        var errorIndices = new Set(cueErrors.map(function (e) { return e.cueIndex; }));
        document.querySelectorAll('.pair-card').forEach(function (card, idx) {
            if (errorIndices.has(idx)) card.classList.add('cue-error');
        });
    }

    function buildTimestampField(cue, idx, field) {
        var wrap = document.createElement('span');
        wrap.className = 'ts-wrap';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'cue-ts';
        input.value = formatTime(cue[field]);
        input.addEventListener('change', function () {
            var val = parseTime(input.value);
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
        var card = document.createElement('div');
        card.className = 'pair-card pair-columns';
        card.dataset.idx = idx;

        var badge = document.createElement('span');
        badge.className = 'pair-index';
        badge.textContent = idx + 1;
        card.appendChild(badge);

        var origBlock = document.createElement('div');
        origBlock.className = 'orig-block';

        if (masterCue) {
            var origTs = document.createElement('span');
            origTs.className = 'orig-ts';
            origTs.textContent = formatTime(masterCue.start) + ' → ' + formatTime(masterCue.end);
            origBlock.appendChild(origTs);

            var origText = document.createElement('p');
            origText.className = 'orig-text';
            origText.textContent = masterCue.text;
            origBlock.appendChild(origText);
        } else {
            var emptyOrig = document.createElement('span');
            emptyOrig.className = 'orig-empty';
            emptyOrig.textContent = '—';
            origBlock.appendChild(emptyOrig);
        }
        card.appendChild(origBlock);

        var transBlock = document.createElement('div');
        transBlock.className = 'trans-block';

        if (translatedCue) {
            var tsRow = document.createElement('div');
            tsRow.className = 'ts-row';
            tsRow.appendChild(buildTimestampField(translatedCue, idx, 'start'));
            var arrow = document.createElement('span');
            arrow.className = 'cue-arrow';
            arrow.textContent = '→';
            tsRow.appendChild(arrow);
            tsRow.appendChild(buildTimestampField(translatedCue, idx, 'end'));
            transBlock.appendChild(tsRow);

            var textarea = document.createElement('textarea');
            textarea.className = 'cue-text';
            textarea.rows = 2;
            textarea.value = translatedCue.text;
            textarea.addEventListener('input', function () {
                translatedCues[idx].text = textarea.value;
            });
            transBlock.appendChild(textarea);
        } else {
            var emptyTrans = document.createElement('span');
            emptyTrans.className = 'orig-empty';
            emptyTrans.textContent = '—';
            transBlock.appendChild(emptyTrans);
        }
        card.appendChild(transBlock);

        return card;
    }

    function render() {
        cueList.innerHTML = '';
        var count = Math.max(masterCues.length, translatedCues.length);
        for (var i = 0; i < count; i++) {
            cueList.appendChild(buildPairCard(masterCues[i] || null, translatedCues[i] || null, i));
        }
        updateOverlapHighlights();
    }

    function isDirty() {
        return JSON.stringify(translatedCues) !== savedSnapshot;
    }

    function downloadVtt() {
        var lang = window.__lang || '';
        var base = (window.__vimeoId || 'caption') + (lang ? '_' + lang.toUpperCase() : '');
        CaptionUtils.triggerDownload(CaptionUtils.generateVtt(translatedCues), base + '.vtt', 'text/vtt');
    }

    function downloadSrt() {
        var lang = window.__lang || '';
        var base = (window.__vimeoId || 'caption') + (lang ? '_' + lang.toUpperCase() : '');
        CaptionUtils.triggerDownload(CaptionUtils.generateSrt(translatedCues), base + '.srt', 'application/x-subrip');
    }

    function exitWithoutSaving() {
        if (isDirty() && !window.confirm('Sortir sense desar els canvis?')) {
            return;
        }
        exitingWithoutSave = true;
        setEditorBusy(true, cancelBtn, 'Sortint…');
        window.location.href = window.__postSaveRedirect;
    }

    function save() {
        if (saveError) saveError.hidden = true;
        setEditorBusy(true, saveBtn, 'Desant…');

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ cues: translatedCues }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    savedSnapshot = JSON.stringify(translatedCues);
                    window.location.href = window.__postSaveRedirect;
                } else {
                    if (saveError) {
                        saveError.textContent = (data.errors || ['Error desconegut.']).join('\n');
                        saveError.hidden = false;
                    }
                    showCueErrors(data.cueErrors);
                    setEditorBusy(false);
                }
            })
            .catch(function () {
                if (saveError) {
                    saveError.textContent = 'Error de xarxa. Torneu-ho a provar.';
                    saveError.hidden = false;
                }
                setEditorBusy(false);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        cueList = document.getElementById('cue-list');
        saveBtn = document.getElementById('save-btn');
        cancelBtn = document.getElementById('cancel-btn');
        saveError = document.getElementById('save-error');
        downloadVttBtn = document.getElementById('download-vtt-btn');
        downloadSrtBtn = document.getElementById('download-srt-btn');

        render();

        saveBtn.addEventListener('click', save);
        if (cancelBtn) cancelBtn.addEventListener('click', exitWithoutSaving);
        if (downloadVttBtn) downloadVttBtn.addEventListener('click', downloadVtt);
        if (downloadSrtBtn) downloadSrtBtn.addEventListener('click', downloadSrt);

        window.addEventListener('beforeunload', function (e) {
            if (exitingWithoutSave || !isDirty()) return;
            e.preventDefault();
            e.returnValue = '';
        });
    });
})();
