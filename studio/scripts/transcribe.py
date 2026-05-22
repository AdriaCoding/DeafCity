#!/usr/bin/env python3
"""
Transcribe interpreter audio to WebVTT using openai/whisper-large-v3-turbo.
Uses the Blind Wiki venv and model cache via run_transcribe.sh.

Arguments:
    --audio_file   Absolute path to the audio file
    --vtt_output   Absolute path where the WebVTT file will be written
    --status_file  Absolute path to the JSON status file (updated during run)
    --language     ISO 639-1 code for the spoken language (e.g. es, en, fr)
"""

import argparse
import json
import sys

from studio_log import LOG_FILE, setup_logging

logger = setup_logging("transcribe")


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


def chunks_to_vtt(chunks: list) -> str:
    parts = ["WEBVTT"]
    prev_end = 0.0
    for chunk in chunks:
        ts = chunk.get("timestamp") or (None, None)
        start, end = (ts[0], ts[1]) if len(ts) == 2 else (None, None)
        text = (chunk.get("text") or "").strip()
        if start is None or not text:
            continue
        start = max(float(start), prev_end)
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
    args = parser.parse_args()

    logger.info(
        "Starting transcription language=%s audio=%s output=%s log=%s",
        args.language,
        args.audio_file,
        args.vtt_output,
        LOG_FILE,
    )
    write_status(args.status_file, "running")

    try:
        from transformers import pipeline

        logger.info("Loading Whisper model")
        asr = pipeline(
            "automatic-speech-recognition",
            model="openai/whisper-large-v3-turbo",
            chunk_length_s=30,
            device="cpu",
        )

        logger.info("Running speech recognition")
        result = asr(
            args.audio_file,
            return_timestamps=True,
            generate_kwargs={"language": args.language},
        )

        chunks = result.get("chunks") or []
        if not chunks:
            logger.error("No speech chunks recognized in audio file")
            write_status(args.status_file, "error", "El format de l'àudio no es reconeix")
            sys.exit(1)

        vtt = chunks_to_vtt(chunks)
        with open(args.vtt_output, "w", encoding="utf-8") as f:
            f.write(vtt)

        logger.info("Transcription complete cues=%d output=%s", len(chunks), args.vtt_output)
        write_status(args.status_file, "done")

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
