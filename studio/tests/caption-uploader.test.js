'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');
const fs = require('fs');

function makeDom() {
    function el(tag, className) {
        const node = {
            tagName: tag.toUpperCase(),
            className: className || '',
            classList: { add() {}, remove() {} },
            dataset: {},
            style: {},
            children: [],
            parentNode: null,
            value: '',
            textContent: '',
            innerHTML: '',
            files: null,
            accept: '',
            type: '',
            addEventListener() {},
            appendChild(child) {
                child.parentNode = node;
                node.children.push(child);
                return child;
            },
            remove() {
                if (node.parentNode) node.parentNode.removeChild(node);
            },
            removeChild(child) {
                const idx = node.children.indexOf(child);
                if (idx !== -1) node.children.splice(idx, 1);
            },
            querySelector(sel) {
                function walk(n) {
                    if (sel === '.caption-uploader-dropzone' && n.className === 'caption-uploader-dropzone') return n;
                    if (sel === '.caption-uploader-rows' && n.className === 'caption-uploader-rows') return n;
                    if (sel === '.caption-uploader-file-input' && n.className === 'caption-uploader-file-input') return n;
                    for (const c of n.children) {
                        const found = walk(c);
                        if (found) return found;
                    }
                    return null;
                }
                return walk(node);
            },
            querySelectorAll(sel) {
                const out = [];
                function walk(n) {
                    if (sel === '.caption-uploader-row' && n.className === 'caption-uploader-row') out.push(n);
                    n.children.forEach(walk);
                }
                walk(node);
                return out;
            },
        };
        return node;
    }

    const container = el('div', 'caption-uploader');
    const dropzone = el('div', 'caption-uploader-dropzone');
    const fileInput = el('input', 'caption-uploader-file-input');
    const rows = el('div', 'caption-uploader-rows');
    container.appendChild(dropzone);
    container.appendChild(fileInput);
    container.appendChild(rows);

    return {
        container,
        document: {
            createElement: function (tag) { return el(tag, ''); },
        },
    };
}

function loadModules(dom) {
    const intakePath = path.join(__dirname, '..', 'js', 'transcription-intake.js');
    const uploaderPath = path.join(__dirname, '..', 'js', 'caption-uploader.js');
    const context = { window: {}, document: dom.document };
    vm.runInNewContext(fs.readFileSync(intakePath, 'utf8'), context);
    vm.runInNewContext(fs.readFileSync(uploaderPath, 'utf8'), context);
    return context.window.CaptionUploader;
}

const languages = [
    { id: 'es', label: 'Spanish', vimeo_code: 'es' },
    { id: 'en', label: 'English', vimeo_code: 'en' },
    { id: 'ca', label: 'Catalan', vimeo_code: 'ca' },
];

const dom = makeDom();
const CaptionUploader = loadModules(dom);
const uploader = CaptionUploader.create(dom.container, languages);

assert.strictEqual(uploader.isValid(), true);
assert.strictEqual(uploader.getEntries().length, 0);

const fileEs = { name: 'clip_es.vtt', size: 1024 };
const fileEn = { name: 'clip_en.srt', size: 2048 };
uploader.addFiles([fileEs, fileEn]);

const entries = uploader.getEntries();
assert.strictEqual(entries.length, 2);
assert.strictEqual(entries[0].file, fileEs);
assert.strictEqual(entries[0].langId, 'es');
assert.strictEqual(entries[1].langId, 'en');
assert.strictEqual(uploader.isValid(), true);

uploader.setLangId(1, 'es');
assert.strictEqual(uploader.isValid(), false);

uploader.setLangId(1, 'en');
assert.strictEqual(uploader.isValid(), true);

const rejected = uploader.addFiles([{ name: 'notes.txt', size: 100 }]);
assert.strictEqual(rejected.length, 1);
assert.strictEqual(uploader.getEntries().length, 2);

const bigFile = { name: 'big_ca.vtt', size: 6 * 1024 * 1024 };
const bigRejected = uploader.addFiles([bigFile]);
assert.strictEqual(bigRejected.length, 1);

uploader.removeAt(0);
assert.strictEqual(uploader.getEntries().length, 1);
assert.strictEqual(uploader.getEntries()[0].langId, 'en');

uploader.clear();
assert.strictEqual(uploader.getEntries().length, 0);

console.log('caption-uploader.test.js: ok');
