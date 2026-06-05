#!/usr/bin/env bash
# Fire-and-forget pipeline: transcribe audio then chain translation to a target language.
#
# Args: --audio_file, --vtt_output, --status_file, --translation_status,
#       --job_dir, --source_lang, --target_lang, --model

export LD_PRELOAD=/usr/lib/x86_64-linux-gnu/libgomp.so.1
export OMP_NUM_THREADS=1
export HF_HOME=/srv/www/deaf.city/public_html/studio/models

LOG_FILE="/srv/www/deaf.city/public_html/data/logs/studio.log"
SCRIPTS_DIR="$(dirname "$0")"

# ── Parse args ───────────────────────────────────────────────────────────────
AUDIO_FILE="" VTT_OUTPUT="" STATUS_FILE="" TRANSLATION_STATUS=""
JOB_DIR="" SOURCE_LANG="" TARGET_LANG="" MODEL="whisper-large-v3-turbo"
PREV=""
for ARG in "$@"; do
    case "$PREV" in
        --audio_file)         AUDIO_FILE="$ARG" ;;
        --vtt_output)         VTT_OUTPUT="$ARG" ;;
        --status_file)        STATUS_FILE="$ARG" ;;
        --translation_status) TRANSLATION_STATUS="$ARG" ;;
        --job_dir)            JOB_DIR="$ARG" ;;
        --source_lang)        SOURCE_LANG="$ARG" ;;
        --target_lang)        TARGET_LANG="$ARG" ;;
        --model)              MODEL="$ARG" ;;
    esac
    PREV="$ARG"
done

# ── Step 1: transcribe ───────────────────────────────────────────────────────
# shellcheck source=/dev/null
source /srv/www/deaf.city/public_html/studio/.venv/bin/activate

python /srv/www/deaf.city/public_html/studio/scripts/transcribe.py \
    --audio_file  "$AUDIO_FILE" \
    --vtt_output  "$VTT_OUTPUT" \
    --status_file "$STATUS_FILE" \
    --language    "$SOURCE_LANG" \
    --model       "$MODEL"
EXIT=$?

if [ $EXIT -ne 0 ]; then
    if [ -n "$STATUS_FILE" ] && grep -q '"running"' "$STATUS_FILE" 2>/dev/null; then
        mkdir -p "$(dirname "$LOG_FILE")"
        printf '%s [run_transcription_pipeline.sh] ERROR: transcribe.py exited %s; wrote fallback error\n' \
            "$(date '+%Y-%m-%d %H:%M:%S')" "$EXIT" >> "$LOG_FILE"
        echo '{"status":"error","message":"Error en la transcripció"}' > "$STATUS_FILE"
    fi
    exit "$EXIT"
fi

# ── Step 2: chain translation (fire-and-forget) ──────────────────────────────
mkdir -p "$(dirname "$LOG_FILE")"
printf '%s [run_transcription_pipeline.sh] Transcription done, spawning translation %s -> %s\n' \
    "$(date '+%Y-%m-%d %H:%M:%S')" "$SOURCE_LANG" "$TARGET_LANG" >> "$LOG_FILE"

GEMINI_API_KEY="$GEMINI_API_KEY" nohup bash "$SCRIPTS_DIR/run_translate.sh" \
    --master_vtt  "$VTT_OUTPUT" \
    --status_file "$TRANSLATION_STATUS" \
    --source_lang "$SOURCE_LANG" \
    --job_dir     "$JOB_DIR" \
    --target_langs "$TARGET_LANG" \
    > /dev/null 2>&1 &
