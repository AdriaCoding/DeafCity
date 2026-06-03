/* caption-utils.js — shared caption helpers, no build step */
(function () {
    'use strict';

    function formatTime(seconds) {
        if (typeof seconds !== 'number' || isNaN(seconds)) return '00:00:00.000';
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        return (
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            s.toFixed(3).padStart(6, '0')
        );
    }

    function parseTime(str) {
        str = str.trim();
        var parts = str.split(':');
        if (parts.length === 3) {
            return parseFloat(parts[0]) * 3600 + parseFloat(parts[1]) * 60 + parseFloat(parts[2]);
        } else if (parts.length === 2) {
            return parseFloat(parts[0]) * 60 + parseFloat(parts[1]);
        }
        return parseFloat(str);
    }

    function formatTimeSrt(seconds) {
        if (typeof seconds !== 'number' || isNaN(seconds)) return '00:00:00,000';
        var h  = Math.floor(seconds / 3600);
        var m  = Math.floor((seconds % 3600) / 60);
        var s  = Math.floor(seconds % 60);
        var ms = Math.round((seconds % 1) * 1000);
        return (
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0') + ',' +
            String(ms).padStart(3, '0')
        );
    }

    function generateVtt(cues) {
        var parts = ['WEBVTT'];
        cues.forEach(function (cue) {
            var opaque = cue.opaque ? ' ' + cue.opaque : '';
            var timing = formatTime(cue.start) + ' --> ' + formatTime(cue.end) + opaque;
            var block = timing + '\n' + cue.text;
            if (cue.id && cue.id !== '') {
                block = cue.id + '\n' + block;
            }
            parts.push(block);
        });
        return parts.join('\n\n') + '\n';
    }

    function generateSrt(cues) {
        var blocks = [];
        cues.forEach(function (cue, i) {
            var timing = formatTimeSrt(cue.start) + ' --> ' + formatTimeSrt(cue.end);
            blocks.push((i + 1) + '\n' + timing + '\n' + cue.text);
        });
        return blocks.join('\n\n') + '\n';
    }

    function triggerDownload(content, filename, mimeType) {
        var blob = new Blob([content], { type: mimeType });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    /* O(n) adjacent-pair scan — requires cues sorted by start time */
    function updateOverlapHighlights(cues, rowSelector) {
        var overlapRows = new Set();
        for (var i = 0; i < cues.length - 1; i++) {
            if (cues[i].end > cues[i + 1].start) {
                overlapRows.add(i);
                overlapRows.add(i + 1);
            }
        }
        document.querySelectorAll(rowSelector).forEach(function (row, idx) {
            row.classList.toggle('cue-overlap', overlapRows.has(idx));
        });
    }

    window.CaptionUtils = {
        formatTime: formatTime,
        parseTime: parseTime,
        formatTimeSrt: formatTimeSrt,
        generateVtt: generateVtt,
        generateSrt: generateSrt,
        triggerDownload: triggerDownload,
        updateOverlapHighlights: updateOverlapHighlights,
    };
}());
