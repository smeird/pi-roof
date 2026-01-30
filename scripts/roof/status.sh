#!/usr/bin/env bash
set -euo pipefail
export PATH="/usr/bin:/bin"

# INDI Dome Scripting Gateway: write "<parked> <shutter> <az>" to argv[1] on success.
ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

status_file="${1:?Missing status file path}"

response="$(curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"status"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}")"

status_line="$(python3 -c 'import json, sys
try:
    data = json.loads(sys.argv[1])
except Exception:
    sys.exit(1)
if data.get("ok") is not True:
    sys.exit(1)
state = data.get("state") or "UNKNOWN"
parked = 1 if state == "CLOSED" else 0
shutter = 1 if state == "OPEN" else 0
az_value = data.get("az")
try:
    az = float(az_value)
except Exception:
    az = 0.0
print(f"{parked} {shutter} {az}")
' "${response}")"

printf '%s\n' "${status_line}" > "${status_file}"
