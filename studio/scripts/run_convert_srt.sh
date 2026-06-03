#!/usr/bin/env bash
LOG_FILE="/srv/www/deaf.city/public_html/data/logs/studio.log"

php /srv/www/deaf.city/public_html/studio/scripts/convert_srt.php "$@"
EXIT=$?

if [ $EXIT -ne 0 ]; then
    STATUS_FILE=""
    PREV=""
    for ARG in "$@"; do
        if [ "$PREV" = "--status_file" ]; then STATUS_FILE="$ARG"; fi
        PREV="$ARG"
    done
    if [ -n "$STATUS_FILE" ] && grep -q '"running"' "$STATUS_FILE" 2>/dev/null; then
        mkdir -p "$(dirname "$LOG_FILE")"
        printf '%s [run_convert_srt.sh] ERROR: Script exited %s before updating status\n' \
            "$(date '+%Y-%m-%d %H:%M:%S')" "$EXIT" >> "$LOG_FILE"
        echo '{"status":"error","message":"Error en la conversió de subtítols"}' > "$STATUS_FILE"
    fi
fi
