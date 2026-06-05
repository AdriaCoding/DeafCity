"""Runs the shared golden fixture through the Python CueChunker.

The PHP suite (CueChunkerParityTest.php) runs the SAME fixture
(tests/fixtures/cue_chunker_cases.json) through the PHP chunker, so both
implementations are pinned to identical cue output. Timestamps are compared at
millisecond resolution (3dp), the WebVTT grid, to stay immune to cross-language
float formatting.

Run with the Studio venv:
    studio/.venv/bin/python -m pytest studio/tests/test_cue_chunker_parity.py
"""

import json
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))

from cue_chunker import chunk  # noqa: E402

FIXTURE = Path(__file__).resolve().parent / "fixtures" / "cue_chunker_cases.json"
CASES = json.loads(FIXTURE.read_text(encoding="utf-8"))


@pytest.mark.parametrize("case", CASES, ids=[c["name"] for c in CASES])
def test_fixture_case(case):
    cues = chunk(case["words"], case.get("params"))

    assert len(cues) == len(case["expected"])
    for got, want in zip(cues, case["expected"]):
        assert got["text"] == want["text"]
        assert round(got["start"], 3) == round(float(want["start"]), 3)
        assert round(got["end"], 3) == round(float(want["end"]), 3)
