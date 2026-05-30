#!/usr/bin/env python3
"""
Transcribe interpreter audio to WebVTT using local faster-whisper (CTranslate2,
int8) as the fallback engine for Subtitle Generation.

Runs in the dedicated Studio venv (studio/.venv) and reads CT2 models from
studio/models/ via run_transcribe.sh. The fast primary path is Groq (PHP);
this script only runs when the orchestrator falls back to the local engine.

Arguments:
    --audio_file   Absolute path to the audio file
    --vtt_output   Absolute path where the WebVTT file will be written
    --status_file  Absolute path to the JSON status file (updated during run)
    --language     ISO 639-1 code for the spoken language (e.g. es, en, fr)
    --model        Model id (default: whisper-large-v3-turbo)
"""

import argparse
import json
import sys

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
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = seconds % 60
    return f"{h:02d}:{m:02d}:{s:06.3f}"


def segments_to_vtt(segments) -> str:
    """Convert faster-whisper segments to WebVTT, applying the monotonic clamp.

    Each segment exposes .start, .end and .text. start is clamped up to the
    previous cue's end; a cue whose start lands at or after its end is dropped.
    Mirrors the PHP Groq path so both engines produce identical-shaped cues.
    """
    parts = ["WEBVTT"]
    prev_end = 0.0
    for seg in segments:
        text = (getattr(seg, "text", None) or "").strip()
        start = getattr(seg, "start", None)
        if start is None or not text:
            continue
        start = max(float(start), prev_end)
        end = getattr(seg, "end", None)
        end = float(end) if end is not None else start + 2.0
        if start >= end:
            continue
        parts.append(f"\n{fmt_ts(start)} --> {fmt_ts(end)}\n{text}")
        prev_end = end
    return "\n".join(parts) + "\n"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--audio_file", required=True)
    parser.add_argument("--vtt_output", required=True)
    parser.add_argument("--status_file", required=True)
    parser.add_argument("--language", required=True)
    parser.add_argument("--model", default="whisper-large-v3-turbo")
    args = parser.parse_args()

    model_name = resolve_model(args.model)

    logger.info(
        "Starting local transcription engine=local:%s model=%s language=%s audio=%s output=%s log=%s",
        args.model,
        model_name,
        args.language,
        args.audio_file,
        args.vtt_output,
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
            vad_filter=True,
            vad_parameters=dict(min_silence_duration_ms=500),
        )

        # faster-whisper yields segments lazily; materialise so we can both
        # build the VTT and check emptiness.
        cues = list(segments)
        vtt = segments_to_vtt(cues)

        if vtt.strip() == "WEBVTT":
            logger.error("No speech recognized in audio file")
            write_status(args.status_file, "error", "El format de l'àudio no es reconeix")
            sys.exit(1)

        with open(args.vtt_output, "w", encoding="utf-8") as f:
            f.write(vtt)

        logger.info(
            "Transcription complete engine=local:%s cues=%d output=%s",
            args.model,
            len(cues),
            args.vtt_output,
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
