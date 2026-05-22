#!/usr/bin/env bash
export LD_PRELOAD=/usr/lib/x86_64-linux-gnu/libgomp.so.1
export OMP_NUM_THREADS=1
export TRANSFORMERS_CACHE=/srv/www/blind.wiki/public_html/Tagger/cache
export HF_HOME=/srv/www/blind.wiki/public_html/Tagger/cache

# shellcheck source=/dev/null
source /srv/www/blind.wiki/public_html/.venv/bin/activate

python /srv/www/deaf.city/public_html/studio/scripts/transcribe.py "$@"
EXIT=$?

# Fallback: if Python crashed before writing an error status, write one now
if [ $EXIT -ne 0 ]; then
    STATUS_FILE=""
    PREV=""
    for ARG in "$@"; do
        if [ "$PREV" = "--status_file" ]; then STATUS_FILE="$ARG"; fi
        PREV="$ARG"
    done
    if [ -n "$STATUS_FILE" ] && grep -q '"running"' "$STATUS_FILE" 2>/dev/null; then
        echo '{"status":"error","message":"Error en la generació de subtítols"}' > "$STATUS_FILE"
    fi
fi
