'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');
const fs = require('fs');

const scriptPath = path.join(__dirname, '..', 'js', 'transcription-intake.js');
const script = fs.readFileSync(scriptPath, 'utf8');
const context = { window: {}, document: { getElementById: () => null } };
vm.runInNewContext(script, context);

const detect = context.window.TranscriptionIntake.detectSubtitleLanguageFromFilename;

const languages = [
    { id: 'es' },
    { id: 'en' },
    { id: 'ca' },
    { id: 'ar' },
];

assert.strictEqual(detect('interview_es.mp3', languages), 'es');
assert.strictEqual(detect('My Recording.wav', languages), null);
assert.strictEqual(detect('session_ar.flac', languages), 'ar');
assert.strictEqual(detect('talk_arq.m4a', languages), null);
assert.strictEqual(detect('clip_mt.ogg', languages), null);
assert.strictEqual(detect('path/to/day1_ca.mp3', languages), 'ca');

// Source→target pair suffixes: use the source language for the dropdown seed only.
assert.strictEqual(detect('indy_3_ES_EN.mp3', languages), 'es');
assert.strictEqual(detect('Indy_4_ES-EN.wav', languages), 'es');
assert.strictEqual(detect('talk_ca_en.vtt', languages), 'ca');
assert.strictEqual(detect('talk_en_es.mp3', languages), 'en');
assert.strictEqual(detect('talk_ar_en.mp3', languages), 'ar');

assert.strictEqual(
    detect('session_ar.flac', [{ id: 'arq', vimeo_code: 'ar' }]),
    null,
    'does not treat a legacy Vimeo mapping as a filename suffix'
);

console.log('transcription-intake-language.test.js: ok');
