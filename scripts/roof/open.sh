#!/usr/bin/env bash
set -euo pipefail
export PATH="/usr/bin:/bin"

# INDI Dome Scripting Gateway: exits 0 only when the roof API returns ok=true.
ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

response="$(curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"open"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}")"

ok="$(printf '%s' "${response}" | python3 - <<'PY'
import json
import sys

try:
    data = json.load(sys.stdin)
except Exception:
    print("false")
    sys.exit(0)

print("true" if data.get("ok") is True else "false")
PY
)"

if [[ "${ok}" != "true" ]]; then
  exit 1
fi
