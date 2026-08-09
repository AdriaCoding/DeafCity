#!/usr/bin/env python3
"""
Transcribe interpreter audio to SubRip using local faster-whisper (CTranslate2,
int8) as the fallback engine for Subtitle Generation.

Runs in the dedicated Studio venv (studio/.venv) and reads CT2 models from
studio/models/ via run_transcribe.sh. The fast primary path is Groq (PHP);
this script only runs when the orchestrator falls back to the local engine.

Arguments:
    --audio_file   Absolute path to the audio file
    --draft_output Absolute path where the SubRip file will be written
    --status_file  Absolute path to the JSON status file (updated during run)
    --language     ISO 639-1 code for the spoken language (e.g. es, en, fr)
    --model        Model id (default: whisper-large-v3-turbo)
"""

import argparse
import json
import sys

from cue_chunker import chunk as chunk_words
from studio_log import LOG_FILE, REPO_ROOT, setup_logging

logger = setup_logging("transcribe")

MODELS_DIR = REPO_ROOT / "studio" / "models"

# Map the public model id (shared with the Groq engine) to the faster-whisper
# model name. faster-whisper resolves "large-v3-turbo" against its model index.
_MODEL_ALIASES = {
    "whisper-large-v3-turbo": "large-v3-turbo",
    "whisper-large-v3": "large-v3",
}


def resolve_model(model: str) -> str:
    return _MODEL_ALIASES.get(model, model)


def write_status(path: str, status: str, message: str = "") -> None:
    data = {"status": status}
    if message:
        data["message"] = message
    with open(path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False)


def fmt_ts(seconds: float) -> str:
    """SubRip timestamp: HH:MM:SS,mmm.

    Whole-millisecond arithmetic, mirroring SrtParser::formatTime() on the PHP
    side: a float landing a hair under a second boundary must not round up into
    a 60-second or 4-digit field and emit a timestamp SubRip cannot parse back.
    """
    total_ms = max(0, round(seconds * 1000))
    h, rem = divmod(total_ms, 3_600_000)
    m, rem = divmod(rem, 60_000)
    s, ms = divmod(rem, 1000)
    return f"{h:02d}:{m:02d}:{s:02d},{ms:03d}"


def words_to_srt(words: list) -> str:
    """Convert word-level timestamp dicts to SubRip via CueChunker.

    Each element: {"start": float, "end": float, "text": str}
    Mirrors the PHP Groq path so both engines produce identical-shaped cues.
    Indices are sequential from 1 and the millisecond separator is a comma,
    both of which SubRip requires and WebVTT did not.
    """
    cues = chunk_words(words)
    blocks = [
        f"{i}\n{fmt_ts(cue['start'])} --> {fmt_ts(cue['end'])}\n{cue['text']}"
        for i, cue in enumerate(cues, start=1)
    ]
    return "\n\n".join(blocks) + "\n"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--audio_file", required=True)
    parser.add_argument("--draft_output", required=True)
    parser.add_argument("--status_file", required=True)
    parser.add_argument("--language", required=True)
    parser.add_argument("--model", default="whisper-large-v3-turbo")
    parser.add_argument("--initial_prompt", default="")
    args = parser.parse_args()

    model_name = resolve_model(args.model)

    logger.info(
        "Starting local transcription engine=local:%s model=%s language=%s audio=%s output=%s log=%s",
        args.model,
        model_name,
        args.language,
        args.audio_file,
        args.draft_output,
        LOG_FILE,
    )
    write_status(args.status_file, "running")

    try:
        from faster_whisper import WhisperModel

        logger.info("Loading faster-whisper model from %s", MODELS_DIR)
        model = WhisperModel(
            model_name,
            device="cpu",
            compute_type="int8",
            download_root=str(MODELS_DIR),
        )

        logger.info("Running speech recognition")
        segments, info = model.transcribe(
            args.audio_file,
            language=args.language,
            word_timestamps=True,
            vad_filter=True,
            vad_parameters=dict(min_silence_duration_ms=500),
            initial_prompt=args.initial_prompt or None,
        )

        # Flatten word-level timestamps from all segments.
        flat_words = []
        for seg in segments:
            for w in (seg.words or []):
                flat_words.append({
                    "start": float(w.start),
                    "end": float(w.end),
                    "text": str(w.word),
                })

        srt = words_to_srt(flat_words)

        if not srt.strip():
            logger.error("No speech recognized in audio file")
            write_status(args.status_file, "error", "El format de l'àudio no es reconeix")
            sys.exit(1)

        with open(args.draft_output, "w", encoding="utf-8") as f:
            f.write(srt)

        logger.info(
            "Transcription complete engine=local:%s cues=%d output=%s",
            args.model,
            srt.count("-->"),
            args.draft_output,
        )
        write_status(args.status_file, "done")

    except SystemExit:
        raise
    except Exception as e:
        msg = str(e).lower()
        if any(k in msg for k in ("ffmpeg", "format", "codec", "audio", "decode")):
            logger.exception("Audio format/decode error")
            write_status(args.status_file, "error", "El format de l'àudio no es reconeix")
        else:
            logger.exception("Transcription failed")
            write_status(args.status_file, "error", "Error en la generació de subtítols")
        sys.exit(1)


if __name__ == "__main__":
    main()
