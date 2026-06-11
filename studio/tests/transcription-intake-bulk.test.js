'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');
const fs = require('fs');

const scriptPath = path.join(__dirname, '..', 'js', 'transcription-intake.js');
const script = fs.readFileSync(scriptPath, 'utf8');

function makeElement(tag) {
    const el = {
        tagName: tag.toUpperCase(),
        style: {},
        innerHTML: '',
        children: [],
        appendChild(child) { this.children.push(child); this.lastChild = child; },
        querySelectorAll() { return []; },
    };
    if (tag === 'select') {
        el.value = '';
        el.name = '';
        el.required = false;
        el.options = [];
        el.firstChild = null;
        el.removeChild = function (child) {
            var idx = this.children.indexOf(child);
            if (idx !== -1) {
                this.children.splice(idx, 1);
                this.options.splice(idx, 1);
            }
            if (this.firstChild === child) {
                this.firstChild = this.children[0] || null;
            }
        };
        var origAppend = el.appendChild.bind(el);
        el.appendChild = function (child) {
            origAppend(child);
            if (child.tagName === 'OPTION') {
                this.options.push(child);
                if (child.selected) {
                    this.value = child.value;
                }
            }
            return child;
        };
    }
    if (tag === 'option') {
        el.value = '';
        el.textContent = '';
        el.selected = false;
        Object.defineProperty(el, 'textContent', {
            set: function (text) { this._text = text; },
            get: function () { return this._text || ''; },
        });
    }
    return el;
}

const document = {
    getElementById: () => null,
    createElement: makeElement,
};

function loadContext() {
    const context = { window: {}, document: document };
    vm.runInNewContext(script, context);
    return context.window.TranscriptionIntake;
}

const languages = [
    { id: 'es', label: 'Espanyol', vimeo_code: 'es' },
    { id: 'en', label: 'Anglès', vimeo_code: 'en' },
    { id: 'ca', label: 'Català', vimeo_code: 'ca' },
];

const TI = loadContext();

function makeFileList(names) {
    return names.map(function (name) {
        return { name: name };
    });
}

function makeDom() {
    const singleField = { style: { display: '' } };
    const bulkContainer = {
        innerHTML: '',
        style: { display: '' },
        appendChild: function (el) { this.lastChild = el; },
    };
    const templateSelect = { required: true, options: [{ value: 'ca' }, { value: 'es' }, { value: 'en' }] };
    const fileInput = { files: null };
    return { singleField, bulkContainer, templateSelect, fileInput };
}

// Single-file mode keeps single dropdown visible
{
    const dom = makeDom();
    dom.fileInput.files = makeFileList(['talk_ca.mp3']);
    const mode = TI.renderBulkTable(dom.fileInput, languages, dom.singleField, dom.bulkContainer, dom.templateSelect);
    assert.strictEqual(mode, 'single');
    assert.strictEqual(dom.singleField.style.display, '');
    assert.strictEqual(dom.bulkContainer.innerHTML, '');
    assert.strictEqual(dom.templateSelect.required, true);
}

// Bulk mode renders table with correct row count
{
    const dom = makeDom();
    dom.fileInput.files = makeFileList(['talk_ca.mp3', 'session_es.wav']);
    const mode = TI.renderBulkTable(dom.fileInput, languages, dom.singleField, dom.bulkContainer, dom.templateSelect);
    assert.strictEqual(mode, 'bulk');
    assert.strictEqual(dom.singleField.style.display, 'none');
    assert.strictEqual(dom.templateSelect.required, false);
    const table = dom.bulkContainer.lastChild;
    assert.strictEqual(table.tagName, 'TABLE');
    const tbody = table.children.find(function (c) { return c.tagName === 'TBODY'; });
    assert.strictEqual(tbody.children.length, 2);
}

// Per-row language auto-detected from filename
{
    const dom = makeDom();
    dom.fileInput.files = makeFileList(['talk_ca.mp3', 'session_es.wav']);
    TI.renderBulkTable(dom.fileInput, languages, dom.singleField, dom.bulkContainer, dom.templateSelect);
    const table = dom.bulkContainer.lastChild;
    const tbody = table.children.find(function (c) { return c.tagName === 'TBODY'; });
    const selects = tbody.children.map(function (tr) {
        return tr.children.find(function (td) { return td.children[0] && td.children[0].tagName === 'SELECT'; }).children[0];
    });
    assert.strictEqual(selects[0].value, 'ca');
    assert.strictEqual(selects[1].value, 'es');
    assert.strictEqual(selects[0].name, 'bulk_languages[0]');
    assert.strictEqual(selects[1].name, 'bulk_languages[1]');
}

// Manual override reflected in serialised field names
{
    const dom = makeDom();
    dom.fileInput.files = makeFileList(['talk_ca.mp3', 'session_es.wav']);
    TI.renderBulkTable(dom.fileInput, languages, dom.singleField, dom.bulkContainer, dom.templateSelect);
    const table = dom.bulkContainer.lastChild;
    const tbody = table.children.find(function (c) { return c.tagName === 'TBODY'; });
    const select = tbody.children[0].children[1].children[0];
    select.options[2].selected = true;
    select.value = select.options[2].value;
    assert.strictEqual(select.value, 'en');
    assert.strictEqual(select.name, 'bulk_languages[0]');
}

// Malicious language config is not interpreted as HTML
{
    const select = makeElement('select');
    const malicious = [{
        id: 'ca"><img src=x onerror=alert(1)>',
        label: '<script>alert(1)</script>',
    }];
    TI.populateLanguageSelect(select, malicious, null);
    assert.strictEqual(select.children.length, 2);
    assert.strictEqual(select.children[1].value, 'ca"><img src=x onerror=alert(1)>');
    assert.strictEqual(select.children[1].textContent, '<script>alert(1)</script>');
    assert.strictEqual(select.children[1].tagName, 'OPTION');
}

console.log('transcription-intake-bulk.test.js: ok');
