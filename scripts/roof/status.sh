#!/usr/bin/env bash
set -euo pipefail
export PATH="/usr/bin:/bin"

# INDI Dome Scripting Gateway: write "<parked> <shutter> <az>" to argv[1] on success.
ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

status_file="${1:?Missing status file path}"

response="$(curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"status"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}")"

parsed="$(printf '%s' "${response}" | python3 - <<'PY'
import json
import sys

try:
    data = json.load(sys.stdin)
except Exception:
    print("false\tUNKNOWN")
    sys.exit(0)

ok = data.get("ok") is True
state = data.get("state") or "UNKNOWN"
print("true" if ok else "false", state, sep="\t")
PY
)"

ok="${parsed%%$'\t'*}"
state="${parsed#*$'\t'}"

if [[ "${ok}" != "true" ]]; then
  exit 1
fi

parked=0
shutter=0
case "${state}" in
  CLOSED)
    parked=1
    ;;
  OPEN)
    shutter=1
    ;;
esac

printf '%d %d 0.0\n' "${parked}" "${shutter}" > "${status_file}"
