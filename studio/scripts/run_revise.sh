#!/usr/bin/env bash

LOG_FILE="/srv/www/deaf.city/public_html/data/logs/studio.log"
SCRIPTS_DIR="$(dirname "$0")"

php "$SCRIPTS_DIR/revise.php" "$@"
EXIT=$?

if [ $EXIT -ne 0 ]; then
    REVISION_STATUS=""
    PREV=""
    for ARG in "$@"; do
        if [ "$PREV" = "--revision_status" ]; then REVISION_STATUS="$ARG"; fi
        PREV="$ARG"
    done
    if [ -n "$REVISION_STATUS" ] && grep -qE '"status"\s*:\s*"(running|pending)"' "$REVISION_STATUS" 2>/dev/null; then
        mkdir -p "$(dirname "$LOG_FILE")"
        printf '%s [run_revise.sh] ERROR: Script exited %s before updating status; writing fallback error to %s\n' \
            "$(date '+%Y-%m-%d %H:%M:%S')" "$EXIT" "$REVISION_STATUS" >> "$LOG_FILE"
        echo '{"status":"error","message":"Error en la revisio"}' > "$REVISION_STATUS"
    fi
    exit "$EXIT"
fi

# Chain translation on success when target languages are provided.
VTT_PATH="" REVISION_STATUS="" SOURCE_LANG="" JOB_DIR=""
TRANSLATION_STATUS="" TARGET_LANGS=""
PREV=""
for ARG in "$@"; do
    case "$PREV" in
        --vtt_path)          VTT_PATH="$ARG" ;;
        --revision_status)   REVISION_STATUS="$ARG" ;;
        --source_lang)       SOURCE_LANG="$ARG" ;;
        --job_dir)           JOB_DIR="$ARG" ;;
        --translation_status) TRANSLATION_STATUS="$ARG" ;;
        --target_langs)      TARGET_LANGS="$ARG" ;;
    esac
    PREV="$ARG"
done

if [ -z "$TARGET_LANGS" ]; then
    exit 0
fi

mkdir -p "$(dirname "$LOG_FILE")"
printf '%s [run_revise.sh] Revision done, spawning translation %s -> %s\n' \
    "$(date '+%Y-%m-%d %H:%M:%S')" "$SOURCE_LANG" "$TARGET_LANGS" >> "$LOG_FILE"

GEMINI_API_KEY="$GEMINI_API_KEY" exec bash "$SCRIPTS_DIR/run_translate.sh" \
    --master_vtt  "$VTT_PATH" \
    --status_file "$TRANSLATION_STATUS" \
    --source_lang "$SOURCE_LANG" \
    --job_dir     "$JOB_DIR" \
    --target_langs "$TARGET_LANGS"
