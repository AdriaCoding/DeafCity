/* subtitle-editor.js — vanilla JS, no build step */
(function () {
    'use strict';

    var formatTime    = CaptionUtils.formatTime;
    var parseTime     = CaptionUtils.parseTime;
    var formatTimeSrt = CaptionUtils.formatTimeSrt;

    let cues = JSON.parse(JSON.stringify(window.__cues));
    let savedSnapshot = JSON.stringify(cues);
    let player = null;
    let activeCueIndex = -1;
    const editorMode = window.__editorMode || 'master';

    let cueList, saveBtn, saveDraftBtn, saveTranslateBtn, saveError, skipBtn, liveCaption, downloadVttBtn, downloadSrtBtn;

    /* Binary search — cues are always sorted by start time */
    function findActiveCueIndex(currentTime) {
        var lo = 0, hi = cues.length - 1;
        while (lo <= hi) {
            var mid = (lo + hi) >> 1;
            if (currentTime < cues[mid].start)     { hi = mid - 1; }
            else if (currentTime >= cues[mid].end) { lo = mid + 1; }
            else { return mid; }
        }
        return -1;
    }

    function updateLiveCaption(cueIndex) {
        if (!liveCaption) return;
        liveCaption.textContent = cueIndex >= 0 ? cues[cueIndex].text : '';
    }

    function initPlayer(vimeoId) {
        player = new Vimeo.Player('vimeo-player', {
            id: vimeoId,
            responsive: true,
            autoplay: true,
            controls: true,
            progress_bar: true,
            preload: 'auto',
            title: false,
            byline: false,
            portrait: false,
            vimeo_logo: false,
        });

        player.on('timeupdate', function (data) {
            highlightActiveCue(data.seconds);
        });

        player.ready().then(function () {
            return player.play();
        }).catch(function () {});
    }

    function highlightActiveCue(currentTime) {
        const found = findActiveCueIndex(currentTime);
        updateLiveCaption(found);

        if (found === activeCueIndex) return;
        activeCueIndex = found;

        document.querySelectorAll('.cue-row').forEach(function (row, i) {
            row.classList.toggle('cue-active', i === found);
        });

        if (found !== -1) {
            const activeRow = document.querySelectorAll('.cue-row')[found];
            if (activeRow) {
                activeRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }

    function setButtonsDisabled(disabled) {
        if (saveBtn) saveBtn.disabled = disabled;
        if (saveDraftBtn) saveDraftBtn.disabled = disabled;
        if (saveTranslateBtn) saveTranslateBtn.disabled = disabled;
    }

    // Visual hint only — not a gate. Server is the authority for what can be saved.
    function updateOverlapHighlights() {
        CaptionUtils.updateOverlapHighlights(cues, '.cue-row');
    }

    function showCueErrors(cueErrors) {
        document.querySelectorAll('.cue-row').forEach(function (row) {
            row.classList.remove('cue-error');
        });
        if (!cueErrors || cueErrors.length === 0) return;
        const errorIndices = new Set(cueErrors.map(function (e) { return e.cueIndex; }));
        document.querySelectorAll('.cue-row').forEach(function (row, idx) {
            if (errorIndices.has(idx)) row.classList.add('cue-error');
        });
    }

    function render() {
        cueList.innerHTML = '';
        cues.forEach(function (cue, idx) {
            cueList.appendChild(buildCueRow(cue, idx));
        });
        updateOverlapHighlights();
        if (player) {
            player.getCurrentTime().then(highlightActiveCue).catch(function () {});
        } else {
            updateLiveCaption(-1);
        }
    }

    function buildCueRow(cue, idx) {
        const row = document.createElement('div');
        row.className = 'cue-row';
        row.dataset.idx = idx;

        const label = document.createElement('span');
        label.className = 'cue-index';
        label.textContent = idx + 1;
        row.appendChild(label);

        const seekBtn = document.createElement('button');
        seekBtn.className = 'cue-seek';
        seekBtn.type = 'button';
        seekBtn.title = 'Anar al subtítol';
        seekBtn.textContent = '▶';
        seekBtn.addEventListener('click', function () {
            if (player) player.setCurrentTime(cue.start);
        });
        row.appendChild(seekBtn);

        row.appendChild(buildTimestampField(cue, idx, 'start'));
        const arrow = document.createElement('span');
        arrow.className = 'cue-arrow';
        arrow.textContent = '→';
        row.appendChild(arrow);
        row.appendChild(buildTimestampField(cue, idx, 'end'));

        const textarea = document.createElement('textarea');
        textarea.className = 'cue-text';
        textarea.rows = 2;
        textarea.value = cue.text;
        textarea.addEventListener('input', function () {
            cues[idx].text = textarea.value;
            if (idx === activeCueIndex) {
                updateLiveCaption(idx);
            }
        });
        row.appendChild(textarea);

        const delBtn = document.createElement('button');
        delBtn.className = 'cue-delete';
        delBtn.type = 'button';
        delBtn.title = 'Suprimeix el subtítol';
        delBtn.textContent = '✕';
        delBtn.addEventListener('click', function () {
            cues.splice(idx, 1);
            render();
        });
        row.appendChild(delBtn);

        const addBtn = document.createElement('button');
        addBtn.className = 'cue-add';
        addBtn.type = 'button';
        addBtn.title = 'Insereix un subtítol després';
        addBtn.textContent = '+ subtítol';
        addBtn.addEventListener('click', function () {
            const newStart = cue.end;
            const newEnd = cue.end + 2.0;
            cues.splice(idx + 1, 0, { start: newStart, end: newEnd, text: '', opaque: '' });
            render();
        });
        row.appendChild(addBtn);

        return row;
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
                cues[idx][field] = val;
                input.value = formatTime(val);
            }
            updateOverlapHighlights();
        });
        wrap.appendChild(input);

        const setBtn = document.createElement('button');
        setBtn.type = 'button';
        setBtn.className = 'cue-settime';
        setBtn.title = 'Estableix ' + (field === 'start' ? 'l\'inici' : 'el final') + ' des del cursor de reproducció';
        setBtn.textContent = '⏺';
        setBtn.addEventListener('click', function () {
            if (!player) return;
            player.getCurrentTime().then(function (t) {
                cues[idx][field] = t;
                input.value = formatTime(t);
                updateOverlapHighlights();
            });
        });
        wrap.appendChild(setBtn);

        return wrap;
    }

    function redirectAfterSave(translate) {
        if (editorMode === 'translation') {
            window.location.href = '?action=translation';
            return;
        }
        if (translate) {
            window.location.href = '?action=translation';
            return;
        }
        resetSaveButtons();
    }

    function save(options) {
        options = options || {};
        const translate = !!options.translate;

        setButtonsDisabled(true);
        if (saveBtn) saveBtn.textContent = 'Desant…';
        if (saveDraftBtn) saveDraftBtn.textContent = 'Desant…';
        if (saveTranslateBtn) saveTranslateBtn.textContent = 'Desant…';

        const payload = { cues: cues };
        if (translate) {
            payload.translate = true;
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    savedSnapshot = JSON.stringify(cues);
                    redirectAfterSave(translate);
                } else {
                    saveError.textContent = (data.errors || ['Error desconegut.']).join('\n');
                    saveError.hidden = false;
                    showCueErrors(data.cueErrors);
                    resetSaveButtons();
                }
            })
            .catch(function () {
                saveError.textContent = 'Error de xarxa. Torneu-ho a provar.';
                saveError.hidden = false;
                resetSaveButtons();
            });
    }

    function resetSaveButtons() {
        updateOverlapHighlights();
        if (saveBtn) saveBtn.textContent = 'Desa';
        if (saveDraftBtn) saveDraftBtn.textContent = 'Desa esborrany';
        if (saveTranslateBtn) saveTranslateBtn.textContent = 'Desa i tradueix';
    }

    function isDirty() {
        return JSON.stringify(cues) !== savedSnapshot;
    }

    function downloadVtt() {
        var lang = window.__lang || '';
        var filename = (window.__vimeoId || 'draft') + (lang ? '_' + lang : '') + '.vtt';
        CaptionUtils.triggerDownload(CaptionUtils.generateVtt(cues), filename, 'text/vtt');
    }

    function downloadSrt() {
        var lang = window.__lang || '';
        var filename = (window.__vimeoId || 'draft') + (lang ? '_' + lang : '') + '.srt';
        CaptionUtils.triggerDownload(CaptionUtils.generateSrt(cues), filename, 'application/x-subrip');
    }

    document.addEventListener('DOMContentLoaded', function () {
        cueList = document.getElementById('cue-list');
        saveBtn = document.getElementById('save-btn');
        saveDraftBtn = document.getElementById('save-draft-btn');
        saveTranslateBtn = document.getElementById('save-translate-btn');
        saveError = document.getElementById('save-error');
        skipBtn = document.getElementById('skip-btn');
        liveCaption = document.getElementById('live-caption');
        downloadVttBtn = document.getElementById('download-vtt-btn');
        downloadSrtBtn = document.getElementById('download-srt-btn');

        initPlayer(window.__vimeoId);
        render();

        if (saveBtn) {
            saveBtn.addEventListener('click', function () { save(); });
        }
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', function () { save(); });
        }
        if (saveTranslateBtn) {
            saveTranslateBtn.addEventListener('click', function () { save({ translate: true }); });
        }

        if (skipBtn) {
            skipBtn.addEventListener('click', function () {
                if (isDirty()) {
                    if (!confirm('Teniu canvis sense desar. Voleu ometre igualment?')) return;
                }
                fetch('?action=skip-to-tagging', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function () {
                    window.location.href = '?action=tagging';
                });
            });
        }

        if (downloadVttBtn) {
            downloadVttBtn.addEventListener('click', downloadVtt);
        }
        if (downloadSrtBtn) {
            downloadSrtBtn.addEventListener('click', downloadSrt);
        }

        window.addEventListener('beforeunload', function (e) {
            if (isDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    });
})();
