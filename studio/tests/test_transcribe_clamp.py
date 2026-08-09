"""Deterministic tests for the word→SubRip helper in transcribe.py.

No model inference — feeds plain word dicts and asserts correct SubRip output.
CueChunker groups words into cues; these tests verify the integration at the
transcribe.py boundary (timestamp formatting, empty-stream handling).

Run with the Studio venv:
    studio/.venv/bin/python -m pytest studio/tests/test_transcribe_clamp.py
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))

from transcribe import fmt_ts, words_to_srt  # noqa: E402


def test_basic_cue_emitted():
    srt = words_to_srt([{"start": 0.0, "end": 1.5, "text": "Hola"}])
    assert srt.startswith("1\n")
    assert "WEBVTT" not in srt
    assert "00:00:00,000 --> 00:00:01,500" in srt
    assert "Hola" in srt


def test_multiple_words_joined_in_one_cue():
    srt = words_to_srt([
        {"start": 0.0, "end": 0.5, "text": "one"},
        {"start": 0.6, "end": 1.0, "text": "two"},
    ])
    assert "one two" in srt
    assert srt.count("-->") == 1


def test_pause_creates_separate_cues():
    srt = words_to_srt([
        {"start": 0.0, "end": 0.5, "text": "one"},
        {"start": 1.5, "end": 2.0, "text": "two"},  # 1s gap > pause_threshold
    ])
    assert "one" in srt
    assert "two" in srt
    assert srt.count("-->") == 2


def test_empty_words_produces_empty_output():
    srt = words_to_srt([])
    assert srt.strip() == ""


def test_cues_are_numbered_sequentially_from_one():
    srt = words_to_srt([
        {"start": 0.0, "end": 0.5, "text": "one"},
        {"start": 1.5, "end": 2.0, "text": "two"},
    ])
    assert srt.startswith("1\n")
    assert "\n\n2\n" in srt


def test_timestamp_does_not_overflow_at_a_second_boundary():
    """A float just under a boundary must not become :60 or a 4-digit field."""
    assert fmt_ts(59.9999) == "00:01:00,000"
    assert fmt_ts(4.9999) == "00:00:05,000"
    assert fmt_ts(3661.5) == "01:01:01,500"
