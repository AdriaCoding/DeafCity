#!/usr/bin/env python3
"""
One-off benchmark: local faster-whisper (int8) vs Groq API
(whisper-large-v3 and whisper-large-v3-turbo).

Outputs land in studio/audio_samples/benchmark/ for qualitative comparison:
  {sample}.{engine}.txt   full transcript text
  {sample}.{engine}.vtt   timestamped cues (WebVTT)
  RESULTS.md              timing table + transcripts side by side

GROQ_API_KEY must be present in the environment (sourced from config/config.php
by the launcher; never printed here).

Timing note: the local number is pure inference; the Groq numbers are full
round-trip (file upload + network + inference) — the real latency a Producer
would feel, but NOT a pure inference comparison. Labelled as such in RESULTS.md.
"""

import os
import subprocess
import time
from pathlib import Path

ROOT = Path("/srv/www/deaf.city/public_html/studio")
SAMPLES = ROOT / "audio_samples"
OUT = SAMPLES / "benchmark"
MODELS = ROOT / "models"

# (basename without extension, Producer-supplied source language)
JOBS = [
    ("ALGER_Mahieddine_6", "fr"),
    ("Roma_Serena_3", "it"),
    ("BCN_Raquel_3", "ca"),
]

LOCAL = "local-fw-turbo-int8"
GROQ_MODELS = ["whisper-large-v3", "whisper-large-v3-turbo"]


def duration(path: Path) -> float:
    out = subprocess.check_output(
        ["ffprobe", "-v", "error", "-show_entries", "format=duration",
         "-of", "default=noprint_wrappers=1:nokey=1", str(path)]
    )
    return float(out.strip())


def fmt_ts(seconds: float) -> str:
    h = int(seconds // 3600)
    m = int((seconds % 3600) // 60)
    s = seconds % 60
    return f"{h:02d}:{m:02d}:{s:06.3f}"


def segs_to_vtt(segs: list) -> str:
    parts = ["WEBVTT"]
    prev_end = 0.0
    for start, end, text in segs:
        text = (text or "").strip()
        if start is None or not text:
            continue
        start = max(float(start), prev_end)
        end = float(end) if end is not None else start + 2.0
        if start >= end:
            continue
        parts.append(f"\n{fmt_ts(start)} --> {fmt_ts(end)}\n{text}")
        prev_end = end
    return "\n".join(parts) + "\n"


def write_outputs(sample: str, engine: str, segs: list, text: str) -> None:
    (OUT / f"{sample}.{engine}.txt").write_text(text.strip() + "\n", encoding="utf-8")
    (OUT / f"{sample}.{engine}.vtt").write_text(segs_to_vtt(segs), encoding="utf-8")


def run_local(model, audio: Path, lang: str):
    t = time.time()
    segments, info = model.transcribe(
        str(audio), language=lang, vad_filter=True,
        vad_parameters=dict(min_silence_duration_ms=500),
    )
    segs = [(s.start, s.end, s.text.strip()) for s in segments]
    dt = time.time() - t
    text = " ".join(s[2] for s in segs)
    return dt, segs, text


def run_groq(client, audio: Path, lang: str, model_name: str):
    t = time.time()
    with open(audio, "rb") as f:
        tr = client.audio.transcriptions.create(
            file=(audio.name, f.read()),
            model=model_name,
            language=lang,
            temperature=0,
            response_format="verbose_json",
        )
    dt = time.time() - t
    segs = [(s["start"], s["end"], (s["text"] or "").strip())
            for s in (tr.segments or [])]
    return dt, segs, tr.text


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    results = []  # (sample, lang, engine, audio_dur, wall, rtf, n_cues, err)
    texts = {}    # (sample, engine) -> text

    # Load local model once.
    from faster_whisper import WhisperModel
    print("Loading local faster-whisper large-v3-turbo (int8)...")
    t0 = time.time()
    local_model = WhisperModel("large-v3-turbo", device="cpu",
                               compute_type="int8", download_root=str(MODELS))
    print(f"  loaded in {time.time()-t0:.1f}s")

    from groq import Groq
    client = Groq(api_key=os.environ["GROQ_API_KEY"])

    for sample, lang in JOBS:
        audio = SAMPLES / f"{sample}.mp3"
        dur = duration(audio)
        print(f"\n=== {sample} ({lang}) audio={dur:.1f}s ===")

        # Local
        try:
            dt, segs, text = run_local(local_model, audio, lang)
            write_outputs(sample, LOCAL, segs, text)
            results.append((sample, lang, LOCAL, dur, dt, dt / dur, len(segs), ""))
            texts[(sample, LOCAL)] = text
            print(f"  {LOCAL:24s} wall={dt:6.1f}s rtf={dt/dur:.2f} cues={len(segs)}")
        except Exception as e:
            results.append((sample, lang, LOCAL, dur, 0, 0, 0, str(e)))
            print(f"  {LOCAL} ERROR: {e}")

        # Groq models
        for gm in GROQ_MODELS:
            engine = f"groq-{gm}"
            try:
                dt, segs, text = run_groq(client, audio, lang, gm)
                write_outputs(sample, engine, segs, text)
                results.append((sample, lang, engine, dur, dt, dt / dur, len(segs), ""))
                texts[(sample, engine)] = text
                print(f"  {engine:24s} wall={dt:6.1f}s rtf={dt/dur:.2f} cues={len(segs)}")
            except Exception as e:
                results.append((sample, lang, engine, dur, 0, 0, 0, str(e)))
                print(f"  {engine} ERROR: {e}")

    write_results_md(results, texts)
    print(f"\nWrote {OUT/'RESULTS.md'}")


def write_results_md(results: list, texts: dict) -> None:
    lines = []
    lines.append("# Transcription benchmark — local faster-whisper vs Groq API\n")
    lines.append("**Host:** 2-core CPU, 8 GB RAM, no GPU, no swap.\n")
    lines.append(
        "**Timing caveat:** `local-fw-turbo-int8` is pure on-CPU inference. "
        "`groq-*` is full round-trip (file upload + network + cloud inference) — "
        "the real latency a Producer feels, not a pure inference number. "
        "RTF = wall time ÷ audio duration (lower is faster).\n"
    )
    lines.append("## Timings\n")
    lines.append("| Sample | Lang | Engine | Audio (s) | Wall (s) | RTF | Cues | Error |")
    lines.append("|---|---|---|---|---|---|---|---|")
    for sample, lang, engine, dur, wall, rtf, n, err in results:
        if err:
            lines.append(f"| {sample} | {lang} | {engine} | {dur:.1f} | — | — | — | {err[:60]} |")
        else:
            lines.append(f"| {sample} | {lang} | {engine} | {dur:.1f} | {wall:.1f} | {rtf:.2f} | {n} | |")
    lines.append("")

    lines.append("## Transcripts side by side\n")
    samples = []
    for sample, lang, *_ in results:
        if sample not in samples:
            samples.append(sample)
    for sample in samples:
        lines.append(f"### {sample}\n")
        for engine in [LOCAL] + [f"groq-{m}" for m in GROQ_MODELS]:
            txt = texts.get((sample, engine), "_(no output / error)_")
            lines.append(f"**{engine}**\n")
            lines.append(f"> {txt}\n")
    (OUT / "RESULTS.md").write_text("\n".join(lines), encoding="utf-8")


if __name__ == "__main__":
    main()
