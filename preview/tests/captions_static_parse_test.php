<?php

require dirname(dirname(__FILE__)) . '/lib/caption_cues.php';

function ccp_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/* --- SubRip: the format the player must handle after the migration --- */

$srt = "1\n00:00:01,500 --> 00:00:03,250\nHola a tothom\n\n"
     . "2\n00:00:04,000 --> 00:00:06,750\nSegona línia\n";

$cues = vpc_parse_caption_cues($srt);

ccp_assert(count($cues) === 2, 'parses both SubRip cues');
ccp_assert($cues[0]['text'] === 'Hola a tothom', 'reads SubRip cue text');

/*
 * The regression this endpoint shipped with: the timing regex had no comma in
 * its character class, so every SubRip cue was silently dropped and the player
 * rendered nothing at all.
 */
ccp_assert($cues !== array(), 'SubRip timings are matched at all');

/*
 * And the second: (float)"01,500" is 1.0 in PHP, so a naive cast discarded the
 * milliseconds entirely and desynced every caption.
 */
ccp_assert($cues[0]['start'] === 1500, 'keeps SubRip milliseconds on start (got ' . $cues[0]['start'] . ')');
ccp_assert($cues[0]['end'] === 3250, 'keeps SubRip milliseconds on end (got ' . $cues[0]['end'] . ')');
ccp_assert($cues[1]['start'] === 4000, 'keeps second cue start');
ccp_assert($cues[1]['end'] === 6750, 'keeps second cue end');

/* --- WebVTT: must keep working unchanged while data/ is mid-migration --- */

$vtt = "WEBVTT\n\n1\n00:00:01.500 --> 00:00:03.250\nHola a tothom\n\n"
     . "2\n00:00:04.000 --> 00:00:06.750\nSegona línia\n";

$vttCues = vpc_parse_caption_cues($vtt);

ccp_assert(count($vttCues) === 2, 'parses both WebVTT cues');
ccp_assert($vttCues === $cues, 'WebVTT and SubRip of the same captions produce identical cues');

/* --- Shared behaviours --- */

$noHeader = vpc_parse_caption_cues("WEBVTT\n00:00:01.000 --> 00:00:02.000\nInline header\n");
ccp_assert(count($noHeader) === 1, 'skips a WEBVTT header sharing a block with the first cue');

$bom = vpc_parse_caption_cues("\xEF\xBB\xBF1\n00:00:01,000 --> 00:00:02,000\nAmb BOM\n");
ccp_assert(count($bom) === 1, 'tolerates a UTF-8 BOM');
ccp_assert($bom[0]['text'] === 'Amb BOM', 'reads text after a BOM');

$crlf = vpc_parse_caption_cues("1\r\n00:00:01,000 --> 00:00:02,000\r\nCRLF\r\n");
ccp_assert(count($crlf) === 1, 'tolerates CRLF line endings');

$multiline = vpc_parse_caption_cues("1\n00:00:01,000 --> 00:00:02,000\nPrimera\nSegona\n");
ccp_assert($multiline[0]['text'] === 'Primera Segona', 'joins multi-line cue text onto one line');

$markup = vpc_parse_caption_cues("1\n00:00:01,000 --> 00:00:02,000\n<i>Ital&agrave;</i>\n");
ccp_assert($markup[0]['text'] === 'Italà', 'strips tags and decodes entities');

$hoursless = vpc_parse_caption_cues("1\n00:01,000 --> 00:04,500\nCurt\n");
ccp_assert($hoursless[0]['start'] === 1000, 'parses MM:SS timestamps');
ccp_assert($hoursless[0]['end'] === 4500, 'parses MM:SS end timestamps');

$empty = vpc_parse_caption_cues("1\n00:00:01,000 --> 00:00:02,000\n\n2\n00:00:03,000 --> 00:00:04,000\nReal\n");
ccp_assert(count($empty) === 1, 'drops cues with no text');

ccp_assert(vpc_parse_caption_cues('') === array(), 'returns no cues for empty input');

echo "PASS: captions-static caption parsing\n";
