#!/usr/bin/env bash
set -euo pipefail
export PATH="/usr/bin:/bin"

# INDI Dome Scripting Gateway: exits 0 only when the roof API returns ok=true.
ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

response="$(curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"park"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}")"

ok="$(python3 -c 'import json, sys
try:
    data = json.loads(sys.argv[1])
except Exception:
    sys.exit(1)
print("true" if data.get("ok") is True else "false")
' "${response}")"

if [[ "${ok}" != "true" ]]; then
  exit 1
fi
