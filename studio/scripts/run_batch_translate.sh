#!/usr/bin/env bash
# Batch-translate every catalog video's captions into any missing subtitle
# language. Args: --status-file <path>

LOG_FILE="/srv/www/deaf.city/public_html/data/logs/studio.log"
SCRIPTS_DIR="$(dirname "$0")"

STATUS_FILE=""
PREV=""
for ARG in "$@"; do
    case "$PREV" in
        --status-file) STATUS_FILE="$ARG" ;;
    esac
    PREV="$ARG"
done

php "$SCRIPTS_DIR/batch_translate_captions.php" "$@"
EXIT=$?

if [ $EXIT -ne 0 ]; then
    mkdir -p "$(dirname "$LOG_FILE")"
    printf '%s [run_batch_translate.sh] ERROR: batch_translate_captions.php exited %s\n' \
        "$(date '+%Y-%m-%d %H:%M:%S')" "$EXIT" >> "$LOG_FILE"
    if [ -n "$STATUS_FILE" ]; then
        SF="$STATUS_FILE" php -r '
$f = getenv("SF");
$d = json_decode(file_get_contents($f) ?: "{}", true) ?: [];
$d["status"] = "error";
file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
' 2>/dev/null
    fi
fi

exit "$EXIT"
