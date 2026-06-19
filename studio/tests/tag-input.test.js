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
            classList: {
                add() {},
                remove() {},
            },
            dataset: {},
            style: {},
            children: [],
            parentNode: null,
            value: '',
            textContent: '',
            innerHTML: '',
            hidden: false,
            type: '',
            title: '',
            addEventListener() {},
            removeEventListener() {},
            appendChild(child) {
                child.parentNode = node;
                node.children.push(child);
                return child;
            },
            insertBefore(child, ref) {
                child.parentNode = node;
                const idx = ref ? node.children.indexOf(ref) : node.children.length;
                node.children.splice(idx, 0, child);
                return child;
            },
            removeChild(child) {
                const idx = node.children.indexOf(child);
                if (idx !== -1) {
                    node.children.splice(idx, 1);
                }
            },
            remove() {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            },
            querySelectorAll(sel) {
                const out = [];
                function walk(n) {
                    if (sel === '.chip' && n.className === 'chip') out.push(n);
                    if (sel === '.chip-remove' && n.className === 'chip-remove') out.push(n);
                    if (sel === '.tag-suggestion-item' && n.className === 'tag-suggestion-item') out.push(n);
                    n.children.forEach(walk);
                }
                walk(node);
                return out;
            },
            querySelector(sel) {
                return node.querySelectorAll(sel)[0] || null;
            },
            closest(sel) {
                if (sel === '.chip' && node.className === 'chip') return node;
                return node.parentNode && node.parentNode.closest ? node.parentNode.closest(sel) : null;
            },
            focus() {},
            click() {},
        };
        return node;
    }

    const chipBox = el('div', 'chip-input-box');
    const textInput = el('input', 'chip-text-input');
    chipBox.appendChild(textInput);
    const suggestions = el('div', 'tag-suggestions');

    return {
        chipBox,
        textInput,
        suggestions,
        document: {
            createElement: function (tag) { return el(tag, ''); },
        },
    };
}

const scriptPath = path.join(__dirname, '..', 'js', 'tag-input.js');
const script = fs.readFileSync(scriptPath, 'utf8');
const dom = makeDom();
const context = { window: {}, document: dom.document };
vm.runInNewContext(script, context);

const TagInput = context.window.TagInput;

TagInput.init(dom.chipBox, dom.textInput, dom.suggestions, ['humor', 'deaf', 'installation']);

assert.strictEqual(TagInput.getTags(dom.chipBox).length, 0);

TagInput.addChip('humor', dom.chipBox, dom.textInput);
TagInput.addChip('custom', dom.chipBox, dom.textInput);
assert.strictEqual(TagInput.getTags(dom.chipBox).join(','), 'humor,custom');

TagInput.addChip('humor', dom.chipBox, dom.textInput);
assert.strictEqual(TagInput.getTags(dom.chipBox).join(','), 'humor,custom');

TagInput.clear(dom.chipBox, dom.textInput);
assert.strictEqual(TagInput.getTags(dom.chipBox).length, 0);

TagInput.setTags(['deaf', 'art'], dom.chipBox, dom.textInput);
assert.strictEqual(TagInput.getTags(dom.chipBox).join(','), 'deaf,art');

console.log('tag-input.test.js: ok');
