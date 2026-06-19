'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');
const fs = require('fs');

// Minimal DOM good enough for the data-model paths in caption-table.js.
function makeDocument() {
    function el(tag) {
        const node = {
            tagName: (tag || '').toUpperCase(),
            className: '',
            id: '',
            type: '',
            name: '',
            value: '',
            title: '',
            accept: '',
            multiple: false,
            hidden: false,
            checked: false,
            disabled: false,
            selected: false,
            textContent: '',
            _children: [],
            parentNode: null,
            style: {},
            dataset: {},
            classList: {
                add() {}, remove() {}, toggle() {}, contains() { return false; },
            },
            get firstChild() { return node._children[0] || null; },
            get children() { return node._children; },
            set innerHTML(v) { if (v === '') node._children = []; },
            get innerHTML() { return ''; },
            appendChild(child) { child.parentNode = node; node._children.push(child); return child; },
            removeChild(child) {
                const i = node._children.indexOf(child);
                if (i !== -1) node._children.splice(i, 1);
                return child;
            },
            remove() { if (node.parentNode) node.parentNode.removeChild(node); },
            addEventListener() {},
            setAttribute() {},
            querySelector() { return null; },
            querySelectorAll() { return []; },
        };
        return node;
    }
    return { createElement: function (tag) { return el(tag); } };
}

function load(documentObj) {
    const context = { window: {}, document: documentObj };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '..', 'js', 'transcription-intake.js'), 'utf8'), context);
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '..', 'js', 'caption-table.js'), 'utf8'), context);
    return context.window.CaptionTable;
}

const languages = [
    { id: 'es', label: 'Spanish', vimeo_code: 'es' },
    { id: 'en', label: 'English', vimeo_code: 'en' },
    { id: 'ca', label: 'Catalan', vimeo_code: 'ca' },
];

const documentObj = makeDocument();
const CaptionTable = load(documentObj);

const fileEs = { name: 'clip_es.vtt', size: 1024 };
const fileEn = { name: 'clip_en.srt', size: 2048 };
const fileCa = { name: 'clip_ca.vtt', size: 512 };

// ── intake mode: master default rule ───────────────────────────────────────
(function () {
    const t = CaptionTable.create(documentObj.createElement('div'), languages, { mode: 'intake' });
    assert.strictEqual(t.isValid(), true);
    assert.strictEqual(t.getEntries().length, 0);
    assert.strictEqual(t.getMasterLang(), '');

    // one file → it is master
    t.addFiles([fileEs]);
    assert.strictEqual(t.getMasterLang(), 'es');

    // multiple files with English → English is master
    t.addFiles([fileEn]);
    assert.strictEqual(t.getEntries().length, 2);
    assert.strictEqual(t.getMasterLang(), 'en');
    assert.strictEqual(t.isValid(), true);

    // two pending rows sharing a language → invalid
    t.setLangId(1, 'es');
    assert.strictEqual(t.isValid(), false);
    t.setLangId(1, 'en');
    assert.strictEqual(t.isValid(), true);

    // require master, satisfied by 'en'
    t.setRequireMaster(true);
    assert.strictEqual(t.isValid(), true);

    // multiple files, no English, none picked → no default master
    t.clear();
    t.addFiles([fileEs, fileCa]);
    assert.strictEqual(t.getMasterLang(), '');
    assert.strictEqual(t.isValid(), false);          // requireMaster still on
    t.setRequireMaster(false);
    assert.strictEqual(t.isValid(), true);

    // rejections: wrong type and oversize
    assert.strictEqual(t.addFiles([{ name: 'notes.txt', size: 100 }]).length, 1);
    assert.strictEqual(t.addFiles([{ name: 'big_ca.vtt', size: 6 * 1024 * 1024 }]).length, 1);
    assert.strictEqual(t.getEntries().length, 2);

    t.removeAt(0);
    assert.strictEqual(t.getEntries().length, 1);
    t.clear();
    assert.strictEqual(t.getEntries().length, 0);
}());

// ── detail mode: persisted rows + pending uploads ──────────────────────────
(function () {
    const calls = { master: [] };
    const d = CaptionTable.create(documentObj.createElement('div'), languages, {
        mode: 'detail',
        existingCaptions: [{ lang: 'es', file: '1.es.vtt' }],
        master: 'es',
        vimeoId: '1',
        onMasterChange: function (lang) { calls.master.push(lang); },
        onEdit: function () {},
        onReplace: function () {},
        onDelete: function () {},
    });

    assert.strictEqual(d.getMasterLang(), 'es');
    assert.deepStrictEqual(d.getExistingLangs(), ['es']);
    assert.strictEqual(d.getEntries().length, 0);

    // a pending upload whose language duplicates a persisted one is a legal replace
    d.addFiles([fileEs]);
    assert.strictEqual(d.getEntries().length, 1);
    assert.strictEqual(d.isValid(), true);

    // detail mode does not auto-pick master from pending uploads
    d.addFiles([fileEn]);
    assert.strictEqual(d.getMasterLang(), 'es');

    // host-driven mutations
    d.setExisting([{ lang: 'en', file: '1.en.vtt' }], 'en');
    assert.strictEqual(d.getMasterLang(), 'en');
    assert.deepStrictEqual(d.getExistingLangs(), ['en']);
    assert.strictEqual(d.getEntries().length, 0);     // setExisting clears pending

    d.updateExistingFile('en', '1.en.v2.vtt');
    d.removeExisting('en');
    assert.deepStrictEqual(d.getExistingLangs(), []);
}());

console.log('caption-table.test.js: ok');
