#!/usr/bin/env bash

LOG_FILE="/srv/www/deaf.city/public_html/data/logs/studio.log"

php /srv/www/deaf.city/public_html/studio/scripts/translate.php "$@"
EXIT=$?

if [ $EXIT -ne 0 ]; then
    STATUS_FILE=""
    PREV=""
    for ARG in "$@"; do
        if [ "$PREV" = "--status_file" ]; then STATUS_FILE="$ARG"; fi
        PREV="$ARG"
    done
    if [ -n "$STATUS_FILE" ] && grep -qE '"status"\s*:\s*"(running|pending)"' "$STATUS_FILE" 2>/dev/null; then
        mkdir -p "$(dirname "$LOG_FILE")"
        printf '%s [run_translate.sh] ERROR: Script exited %s before updating status; writing fallback error to %s\n' \
            "$(date '+%Y-%m-%d %H:%M:%S')" "$EXIT" "$STATUS_FILE" >> "$LOG_FILE"
        SF="$STATUS_FILE" php -r '
$f = getenv("SF");
$d = json_decode(file_get_contents($f) ?: "{}", true) ?: ["status" => "done", "languages" => []];
foreach (array_keys($d["languages"] ?? []) as $l) {
    $ls = $d["languages"][$l]["status"] ?? "";
    if (in_array($ls, ["running", "pending"])) {
        $d["languages"][$l] = ["status" => "error", "message" => "Error en la traduccio"];
    }
}
$d["status"] = "done";
file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
' 2>/dev/null || echo '{"status":"done","languages":{}}' > "$STATUS_FILE"
    fi
fi
