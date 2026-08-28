#!/bin/bash
set -euo pipefail

CONF=/etc/emonhub/emonhub.conf
TYPE_RE='EmonHubMinimalModbusInterfacer'
GLOB='/dev/serial/by-id/usb-1a86_USB_Single_Serial_*-if00'

mapfile -t DEVS < <(ls -1 $GLOB 2>/dev/null || true)

if [ "${#DEVS[@]}" -eq 0 ]; then
    echo "No adapter matching $GLOB found" >&2
    exit 1
elif [ "${#DEVS[@]}" -gt 1 ]; then
    echo "More than one adapter found:" >&2
    printf '  %s\n' "${DEVS[@]}" >&2
    exit 1
fi

DEV="${DEVS[0]}"
TMP=$(mktemp)

awk -v dev="$DEV" -v typere="$TYPE_RE" '
function flush_pending() {
    if (in_init && !placed) { print indent "device = " dev; placed=1; count++ }
}
/^[ \t]*\[[^[]/         { flush_pending(); iface=0; in_init=0; placed=0; print; next }
/^[ \t]*\[\[[^[]/       { flush_pending(); iface=0; in_init=0; placed=0; print; next }
/^[ \t]*\[\[\[[^[]/     {
    flush_pending()
    in_init = (iface && $0 ~ /\[\[\[init_settings\]\]\]/)
    if (in_init) { match($0,/^[ \t]*/); indent = substr($0,1,RLENGTH) "    " }
    print; next
}
/^[ \t]*Type[ \t]*=/    { if ($0 ~ typere) iface=1; print; next }
in_init && /^[ \t]*#?[ \t]*device[ \t]*=/ {
    match($0,/^[ \t]*/); ind = substr($0,1,RLENGTH)
    if (!placed) { print ind "device = " dev; placed=1; count++ }
    else { if ($0 ~ /^[ \t]*device/) sub(/device/,"#device"); print }
    next
}
{ if (in_init && $0 ~ /[^ \t]/) { match($0,/^[ \t]*/); indent = substr($0,1,RLENGTH) }
  print }
END { printf "%d section(s) updated\n", count > "/dev/stderr"; exit count ? 0 : 3 }
' "$CONF" > "$TMP" || { echo "No matching interfacer found" >&2; rm -f "$TMP"; exit 1; }

if cmp -s "$CONF" "$TMP"; then
    echo "Already set to: $DEV"; rm -f "$TMP"; exit 0
fi

diff -u "$CONF" "$TMP" || true
sudo cp -a "$CONF" "$CONF.bak"
sudo cp "$TMP" "$CONF"
rm -f "$TMP"

sudo systemctl restart emonhub
