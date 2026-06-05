"""Re-merge Whisper word-level timestamps into readable single-line WebVTT cues.

Pure and deterministic: word stream in, cue list out, no I/O. Mirrors
src/CueChunker.php exactly; both are pinned to the shared golden fixture
(tests/fixtures/cue_chunker_cases.json) so the local faster-whisper path and the
Groq (PHP) path produce identical cue shapes.

Character counts are Unicode code points (len()), so an accented glyph like "à"
counts as one — matching PHP's mb_strlen().
"""

from typing import List, Dict, Optional


class CueChunker:
    def __init__(self, params: Optional[Dict] = None):
        params = params or {}
        self.max_chars = int(params.get("max_chars", 50))
        self.pause_threshold = float(params.get("pause_threshold", 0.4))
        self.max_duration = float(params.get("max_duration", 6.0))

    def chunk(self, words: List[Dict]) -> List[Dict]:
        """words: [{"start": float, "end": float, "text": str}, ...]
        returns: [{"start": float, "end": float, "text": str}, ...] (single-line)
        """
        if not words:
            return []
        return self._split_phase(words)

    def _split_phase(self, words: List[Dict]) -> List[Dict]:
        """Phase 1 — split the word stream into cues, closing the current cue
        when a hard splitting constraint (so far: max chars) would be exceeded.
        """
        cues: List[Dict] = []
        cur: List[Dict] = []
        for w in words:
            # A pause is a forced break: close as-is, no retreat.
            if cur and self._pause_before(cur, w):
                cues.append(self._make_cue(cur))
                cur = [w]
                continue
            # Capacity exceeded: close at the latest good break point within the
            # accumulated words (strong punctuation), carrying the rest forward.
            if cur and (self._would_exceed_chars(cur, w) or self._would_exceed_duration(cur, w)):
                break_idx = self._last_break_index(cur)
                cues.append(self._make_cue(cur[: break_idx + 1]))
                cur = cur[break_idx + 1 :]
                cur.append(w)
                continue
            cur.append(w)
        if cur:
            cues.append(self._make_cue(cur))
        return cues

    @staticmethod
    def _last_break_index(cur: List[Dict]) -> int:
        """Latest word ending in strong punctuation (. ! ?). Falls back to the
        last index — a hard cut after the whole accumulation — when none found.
        """
        for i in range(len(cur) - 1, -1, -1):
            last = str(cur[i]["text"]).rstrip()[-1:]
            if last in (".", "!", "?"):
                return i
        return len(cur) - 1

    def _pause_before(self, cur: List[Dict], nxt: Dict) -> bool:
        return (float(nxt["start"]) - float(cur[-1]["end"])) > self.pause_threshold

    def _would_exceed_chars(self, cur: List[Dict], nxt: Dict) -> bool:
        return len(self._join_text(cur + [nxt])) > self.max_chars

    def _would_exceed_duration(self, cur: List[Dict], nxt: Dict) -> bool:
        return (float(nxt["end"]) - float(cur[0]["start"])) > self.max_duration

    def _make_cue(self, cur: List[Dict]) -> Dict:
        return {
            "start": float(cur[0]["start"]),
            "end": float(cur[-1]["end"]),
            "text": self._join_text(cur),
        }

    @staticmethod
    def _join_text(words: List[Dict]) -> str:
        return " ".join(str(w["text"]).strip() for w in words)


def chunk(words: List[Dict], params: Optional[Dict] = None) -> List[Dict]:
    return CueChunker(params).chunk(words)
