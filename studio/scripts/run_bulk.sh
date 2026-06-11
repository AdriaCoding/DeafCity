#!/usr/bin/env bash
# Process a bulk transcription intake queue sequentially.
#
# Args: --data_dir

LOG_FILE="/srv/www/deaf.city/public_html/data/logs/studio.log"
SCRIPTS_DIR="$(dirname "$0")"

DATA_DIR=""
PREV=""
for ARG in "$@"; do
    case "$PREV" in
        --data_dir) DATA_DIR="$ARG" ;;
    esac
    PREV="$ARG"
done

if [ -z "$DATA_DIR" ]; then
    echo "Missing --data_dir" >&2
    exit 1
fi

mkdir -p "$(dirname "$LOG_FILE")"
printf '%s [run_bulk.sh] Starting bulk queue processor for %s\n' \
    "$(date '+%Y-%m-%d %H:%M:%S')" "$DATA_DIR" >> "$LOG_FILE"

php "$SCRIPTS_DIR/process_bulk_queue.php" --data_dir "$DATA_DIR"
EXIT=$?

if [ $EXIT -ne 0 ]; then
    printf '%s [run_bulk.sh] ERROR: process_bulk_queue.php exited %s\n' \
        "$(date '+%Y-%m-%d %H:%M:%S')" "$EXIT" >> "$LOG_FILE"
fi

exit "$EXIT"
