"""Deterministic test for the segments→VTT clamp helper in transcribe.py.

No model inference — feeds plain segment tuples and asserts the WebVTT output
applies the same monotonic clamp as the PHP Groq path:
  start >= prev_end ; drop a cue whose start >= end after clamping.

Run with the Studio venv:
    studio/.venv/bin/python -m pytest studio/tests/test_transcribe_clamp.py
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))

from transcribe import segments_to_vtt  # noqa: E402


class Seg:
    """Minimal stand-in for a faster-whisper Segment."""

    def __init__(self, start, end, text):
        self.start = start
        self.end = end
        self.text = text


def test_basic_cue_emitted():
    vtt = segments_to_vtt([Seg(0.0, 1.5, " Hola ")])
    assert vtt.startswith("WEBVTT")
    assert "00:00:00.000 --> 00:00:01.500" in vtt
    assert "Hola" in vtt


def test_start_clamped_to_prev_end():
    vtt = segments_to_vtt([
        Seg(0.0, 1.5, "one"),
        Seg(1.4, 3.0, "two"),  # starts before prev_end → clamped to 1.5
    ])
    assert "00:00:01.500 --> 00:00:03.000" in vtt


def test_segment_swallowed_by_previous_is_dropped():
    vtt = segments_to_vtt([
        Seg(0.0, 5.0, "one"),
        Seg(2.0, 4.0, "swallowed"),  # start(5.0 after clamp) >= end(4.0) → dropped
        Seg(6.0, 7.0, "two"),
    ])
    assert "swallowed" not in vtt
    assert "one" in vtt
    assert "two" in vtt


def test_empty_text_skipped():
    vtt = segments_to_vtt([Seg(0.0, 1.0, "   ")])
    assert vtt.strip() == "WEBVTT"


def test_missing_end_defaults_to_start_plus_two():
    vtt = segments_to_vtt([Seg(3.0, None, "x")])
    assert "00:00:03.000 --> 00:00:05.000" in vtt
