'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');
const fs = require('fs');

function load() {
    const context = { window: {}, module: { exports: {} }, exports: {} };
    context.module.exports = context.exports;
    vm.runInNewContext(
        fs.readFileSync(path.join(__dirname, '..', 'js', 'caption-editor-host.js'), 'utf8'),
        context
    );
    return context.module.exports.StudioCaptionEditorHost
        || context.window.StudioCaptionEditorHost
        || context.module.exports;
}

const Host = load();

(function framedSaveDoesNotNavigate() {
    const posted = [];
    const parent = {
        postMessage: function (data, origin) { posted.push({ data: data, origin: origin }); },
    };
    const win = {
        parent: parent,
        location: { origin: 'https://deaf.city', href: 'https://deaf.city/studio/?action=continguts-caption-review' },
    };

    const result = Host.exitEditor(win, '?action=continguts-video&vimeo_id=1');

    assert.strictEqual(result, 'posted');
    assert.strictEqual(win.location.href, 'https://deaf.city/studio/?action=continguts-caption-review');
    assert.strictEqual(posted.length, 1);
    assert.strictEqual(posted[0].data.type, 'studio-caption-editor-done');
    assert.strictEqual(posted[0].origin, 'https://deaf.city');
}());

(function topLevelSaveStillRedirects() {
    const win = {
        location: { origin: 'https://deaf.city', href: 'https://deaf.city/studio/?action=continguts-caption-review' },
    };
    win.parent = win;

    const result = Host.exitEditor(win, '?action=continguts-video&vimeo_id=1');

    assert.strictEqual(result, 'redirected');
    assert.strictEqual(win.location.href, '?action=continguts-video&vimeo_id=1');
}());

(function hostClosesDialogOnTrustedMessage() {
    let closed = 0;
    const accepted = Host.handleHostMessage(
        { origin: 'https://deaf.city', data: { type: 'studio-caption-editor-done' } },
        'https://deaf.city',
        function () { closed += 1; }
    );
    assert.strictEqual(accepted, true);
    assert.strictEqual(closed, 1);
}());

(function hostIgnoresForeignOrigin() {
    let closed = 0;
    const accepted = Host.handleHostMessage(
        { origin: 'https://evil.example', data: { type: 'studio-caption-editor-done' } },
        'https://deaf.city',
        function () { closed += 1; }
    );
    assert.strictEqual(accepted, false);
    assert.strictEqual(closed, 0);
}());

console.log('caption-editor-host.test.js: ok');
