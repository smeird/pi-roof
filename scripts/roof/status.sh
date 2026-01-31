#!/usr/bin/env bash
# Query the roof API for status and write the INDI-formatted status line.
set -euo pipefail
# Ensure a predictable PATH for system utilities like curl and python3.
export PATH="/usr/bin:/bin"

# INDI Dome Scripting Gateway: write "<parked> <shutter> <az>" to argv[1] on success.
# Allow the base URL/path to be overridden by environment variables.
ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
# Cap how long the HTTP request can take.
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

# Require a path to write the status output.
status_file="${1:?Missing status file path}"

# Fetch JSON status and convert it into the INDI expected output format.
if ! status_line="$(curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"status"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}" | python3 -c 'import json, sys
try:
    data = json.load(sys.stdin)
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
')"; then
  # Exit with error if the API is unreachable or returns invalid data.
  exit 1
fi

# Write the status line to the requested file.
printf '%s\n' "${status_line}" > "${status_file}"
