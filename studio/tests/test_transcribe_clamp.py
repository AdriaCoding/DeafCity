"""Deterministic tests for the word→VTT helper in transcribe.py.

No model inference — feeds plain word dicts and asserts correct WebVTT output.
CueChunker groups words into cues; these tests verify the integration at the
transcribe.py boundary (timestamp formatting, empty-stream handling).

Run with the Studio venv:
    studio/.venv/bin/python -m pytest studio/tests/test_transcribe_clamp.py
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))

from transcribe import words_to_vtt  # noqa: E402


def test_basic_cue_emitted():
    vtt = words_to_vtt([{"start": 0.0, "end": 1.5, "text": "Hola"}])
    assert vtt.startswith("WEBVTT")
    assert "00:00:00.000 --> 00:00:01.500" in vtt
    assert "Hola" in vtt


def test_multiple_words_joined_in_one_cue():
    vtt = words_to_vtt([
        {"start": 0.0, "end": 0.5, "text": "one"},
        {"start": 0.6, "end": 1.0, "text": "two"},
    ])
    assert "one two" in vtt
    assert vtt.count("-->") == 1


def test_pause_creates_separate_cues():
    vtt = words_to_vtt([
        {"start": 0.0, "end": 0.5, "text": "one"},
        {"start": 1.5, "end": 2.0, "text": "two"},  # 1s gap > pause_threshold
    ])
    assert "one" in vtt
    assert "two" in vtt
    assert vtt.count("-->") == 2


def test_empty_words_produces_webvtt_only():
    vtt = words_to_vtt([])
    assert vtt.strip() == "WEBVTT"
