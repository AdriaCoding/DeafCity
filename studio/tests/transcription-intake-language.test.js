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
    { id: 'es', vimeo_code: 'es' },
    { id: 'en', vimeo_code: 'en' },
    { id: 'ca', vimeo_code: 'ca' },
    { id: 'arq', vimeo_code: 'ar' },
    { id: 'aeb', vimeo_code: 'mt' },
];

assert.strictEqual(detect('interview_es.mp3', languages), 'es');
assert.strictEqual(detect('My Recording.wav', languages), null);
assert.strictEqual(detect('talk_arq.m4a', languages), 'arq');
assert.strictEqual(detect('session_ar.flac', languages), 'arq');
assert.strictEqual(detect('clip_mt.ogg', languages), 'aeb');
assert.strictEqual(detect('path/to/day1_ca.mp3', languages), 'ca');

console.log('transcription-intake-language.test.js: ok');
