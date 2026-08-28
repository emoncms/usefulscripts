#!/bin/bash
set -euo pipefail

CONF=/etc/emonhub/emonhub.conf
PATTERN='/dev/serial/by-id/usb-Prolific_Technology_Inc._USB-Serial_Controller_*-if00-port0'

mapfile -t DEVS < <(ls -1 $PATTERN 2>/dev/null || true)

if [ "${#DEVS[@]}" -eq 0 ]; then
    echo "No Prolific adapter found" >&2
    exit 1
elif [ "${#DEVS[@]}" -gt 1 ]; then
    echo "More than one Prolific adapter found:" >&2
    printf '  %s\n' "${DEVS[@]}" >&2
    exit 1
fi

DEV="${DEVS[0]}"
TMP=$(mktemp)

awk -v dev="$DEV" '
function flush_pending() {
    if (in_init && !placed) { print indent "device = " dev; placed=1 }
}
/^[ \t]*\[[^[]/ {                                   # [top_level] section
    flush_pending(); in_mbus=0; in_init=0; print; next
}
/^[ \t]*\[\[[^[]/ {                                 # [[Interfacer]]
    flush_pending()
    in_mbus = ($0 ~ /^[ \t]*\[\[MBUS\]\]/)
    in_init = 0
    print; next
}
/^[ \t]*\[\[\[[^[]/ {                               # [[[subsection]]]
    flush_pending()
    in_init = (in_mbus && $0 ~ /\[\[\[init_settings\]\]\]/)
    if (in_init) { match($0,/^[ \t]*/); indent = substr($0,1,RLENGTH) "    " }
    print; next
}
in_init && /^[ \t]*#?[ \t]*device[ \t]*=/ {         # active or commented device line
    match($0,/^[ \t]*/); ind = substr($0,1,RLENGTH)
    if (!placed) { print ind "device = " dev; placed=1 }
    else { if ($0 ~ /^[ \t]*device/) sub(/device/,"#device"); print }
    next
}
{ if (in_init && $0 ~ /[^ \t]/) { match($0,/^[ \t]*/); indent = substr($0,1,RLENGTH) }
  print }
END { flush_pending(); exit placed ? 0 : 3 }
' "$CONF" > "$TMP" || { echo "No [[MBUS]] init_settings section found" >&2; rm -f "$TMP"; exit 1; }

if cmp -s "$CONF" "$TMP"; then
    echo "Already set to: $DEV"
    rm -f "$TMP"
    exit 0
fi

diff -u "$CONF" "$TMP" || true
sudo cp -a "$CONF" "$CONF.bak"
sudo cp "$TMP" "$CONF"
rm -f "$TMP"

sudo systemctl restart emonhub