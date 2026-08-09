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
DRAFT_PATH="" REVISION_STATUS="" SOURCE_LANG="" JOB_DIR=""
TRANSLATION_STATUS="" TARGET_LANGS="" TRANSLATE_SOURCE_LANG="" DIALECT_NAME=""
PREV=""
for ARG in "$@"; do
    case "$PREV" in
        --draft_path)        DRAFT_PATH="$ARG" ;;
        --revision_status)   REVISION_STATUS="$ARG" ;;
        --source_lang)       SOURCE_LANG="$ARG" ;;
        --job_dir)           JOB_DIR="$ARG" ;;
        --translation_status) TRANSLATION_STATUS="$ARG" ;;
        --target_langs)      TARGET_LANGS="$ARG" ;;
        --translate_source_lang) TRANSLATE_SOURCE_LANG="$ARG" ;;
        --dialect_name)       DIALECT_NAME="$ARG" ;;
    esac
    PREV="$ARG"
done

if [ -z "$TARGET_LANGS" ]; then
    exit 0
fi

# The revision leg above used $SOURCE_LANG (which may be a dialect id, e.g.
# es-mx) for prompt steering. Translation only ever targets base languages,
# so it gets $TRANSLATE_SOURCE_LANG instead — falling back to $SOURCE_LANG
# when the caller didn't distinguish the two (no dialect involved).
EFFECTIVE_TRANSLATE_SOURCE_LANG="${TRANSLATE_SOURCE_LANG:-$SOURCE_LANG}"

mkdir -p "$(dirname "$LOG_FILE")"
printf '%s [run_revise.sh] Revision done, spawning translation %s -> %s\n' \
    "$(date '+%Y-%m-%d %H:%M:%S')" "$EFFECTIVE_TRANSLATE_SOURCE_LANG" "$TARGET_LANGS" >> "$LOG_FILE"

GEMINI_API_KEY="$GEMINI_API_KEY" exec bash "$SCRIPTS_DIR/run_translate.sh" \
    --master_captions "$DRAFT_PATH" \
    --status_file "$TRANSLATION_STATUS" \
    --source_lang "$EFFECTIVE_TRANSLATE_SOURCE_LANG" \
    --job_dir     "$JOB_DIR" \
    --target_langs "$TARGET_LANGS"
