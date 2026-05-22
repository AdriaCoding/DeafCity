/* subtitle-editor.js — vanilla JS, no build step */
(function () {
    'use strict';

    // ─── State ───────────────────────────────────────────────────────────────
    let cues = JSON.parse(JSON.stringify(window.__cues));
    let savedSnapshot = JSON.stringify(cues);
    let player = null;
    let activeCueIndex = -1;

    // ─── DOM refs (assigned after DOMContentLoaded) ───────────────────────
    let cueList, saveBtn, saveError, skipBtn, liveCaption;

    function findActiveCueIndex(currentTime) {
        for (let i = 0; i < cues.length; i++) {
            if (currentTime >= cues[i].start && currentTime < cues[i].end) {
                return i;
            }
        }
        return -1;
    }

    function updateLiveCaption(cueIndex) {
        if (!liveCaption) return;
        liveCaption.textContent = cueIndex >= 0 ? cues[cueIndex].text : '';
    }

    // ─── Vimeo player ────────────────────────────────────────────────────────
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
        }).catch(function () {
            // Browser may block unmuted autoplay; Vimeo controls remain for manual play/scrub.
        });
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

    // ─── Integrity check ─────────────────────────────────────────────────────
    function checkIntegrity() {
        const errors = [];

        for (let i = 0; i < cues.length; i++) {
            const c = cues[i];
            const n = i + 1;
            if (c.start < 0) errors.push('Subtítol ' + n + ': l\'hora d\'inici no pot ser negativa.');
            if (c.start >= c.end) errors.push('Subtítol ' + n + ': l\'hora d\'inici ha de ser anterior a l\'hora de fi.');
        }

        for (let i = 0; i < cues.length - 1; i++) {
            for (let j = i + 1; j < cues.length; j++) {
                if (cues[i].end > cues[j].start) {
                    errors.push('Els subtítols ' + (i + 1) + ' i ' + (j + 1) + ' se superposen.');
                }
            }
        }

        return errors;
    }

    function updateIntegrityUI() {
        const errors = checkIntegrity();
        const hasErrors = errors.length > 0;

        saveBtn.disabled = hasErrors;
        saveBtn.title = hasErrors ? 'Corregiu els errors d\'integritat abans de desar.' : '';

        saveError.textContent = errors.join('\n');
        saveError.hidden = !hasErrors;

        // highlight overlapping rows
        const overlapRows = new Set();
        for (let i = 0; i < cues.length - 1; i++) {
            for (let j = i + 1; j < cues.length; j++) {
                if (cues[i].end > cues[j].start) {
                    overlapRows.add(i);
                    overlapRows.add(j);
                }
            }
        }
        document.querySelectorAll('.cue-row').forEach(function (row, idx) {
            row.classList.toggle('cue-overlap', overlapRows.has(idx));
        });
    }

    // ─── Time helpers ─────────────────────────────────────────────────────────
    function formatTime(seconds) {
        if (typeof seconds !== 'number' || isNaN(seconds)) return '00:00:00.000';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return (
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            s.toFixed(3).padStart(6, '0')
        );
    }

    function parseTime(str) {
        str = str.trim();
        const parts = str.split(':');
        if (parts.length === 3) {
            return parseFloat(parts[0]) * 3600 + parseFloat(parts[1]) * 60 + parseFloat(parts[2]);
        } else if (parts.length === 2) {
            return parseFloat(parts[0]) * 60 + parseFloat(parts[1]);
        }
        return parseFloat(str);
    }

    // ─── Rendering ───────────────────────────────────────────────────────────
    function render() {
        cueList.innerHTML = '';
        cues.forEach(function (cue, idx) {
            cueList.appendChild(buildCueRow(cue, idx));
        });
        updateIntegrityUI();
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

        // index label
        const label = document.createElement('span');
        label.className = 'cue-index';
        label.textContent = idx + 1;
        row.appendChild(label);

        // seek button
        const seekBtn = document.createElement('button');
        seekBtn.className = 'cue-seek';
        seekBtn.type = 'button';
        seekBtn.title = 'Anar al subtítol';
        seekBtn.textContent = '▶';
        seekBtn.addEventListener('click', function () {
            if (player) player.setCurrentTime(cue.start);
        });
        row.appendChild(seekBtn);

        // timestamps
        row.appendChild(buildTimestampField(cue, idx, 'start'));
        const arrow = document.createElement('span');
        arrow.className = 'cue-arrow';
        arrow.textContent = '→';
        row.appendChild(arrow);
        row.appendChild(buildTimestampField(cue, idx, 'end'));

        // text
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

        // delete button
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

        // insert-after button
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
            updateIntegrityUI();
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
                updateIntegrityUI();
            });
        });
        wrap.appendChild(setBtn);

        return wrap;
    }

    // ─── Save ─────────────────────────────────────────────────────────────────
    function save() {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Desant…';

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ cues: cues }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    savedSnapshot = JSON.stringify(cues);
                    window.location.href = '?action=translation';
                } else {
                    saveError.textContent = (data.errors || ['Error desconegut.']).join('\n');
                    saveError.hidden = false;
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Desa i finalitza';
                }
            })
            .catch(function () {
                saveError.textContent = 'Error de xarxa. Torneu-ho a provar.';
                saveError.hidden = false;
                saveBtn.disabled = false;
                saveBtn.textContent = 'Desa i finalitza';
            });
    }

    // ─── Unsaved-changes guard ────────────────────────────────────────────────
    function isDirty() {
        return JSON.stringify(cues) !== savedSnapshot;
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        cueList = document.getElementById('cue-list');
        saveBtn = document.getElementById('save-btn');
        saveError = document.getElementById('save-error');
        skipBtn = document.getElementById('skip-btn');
        liveCaption = document.getElementById('live-caption');

        initPlayer(window.__vimeoId);
        render();

        saveBtn.addEventListener('click', save);

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

        window.addEventListener('beforeunload', function (e) {
            if (isDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    });
})();
