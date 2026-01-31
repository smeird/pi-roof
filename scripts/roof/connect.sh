#!/usr/bin/env bash
# Call the roof API to connect to the controller and exit based on success.
set -euo pipefail
# Ensure a predictable PATH for system utilities like curl and python3.
export PATH="/usr/bin:/bin"

# INDI Dome Scripting Gateway: exits 0 only when the roof API returns ok=true.
# Allow the base URL/path to be overridden by environment variables.
ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
# Cap how long the HTTP request can take.
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

# Send the connect action and parse the JSON response for ok=true.
if ! curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"connect"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}" | python3 -c 'import json, sys
try:
    data = json.load(sys.stdin)
except Exception:
    sys.exit(1)
sys.exit(0 if data.get("ok") is True else 1)
'; then
  # Propagate failure when the API is unreachable or returns ok=false.
  exit 1
fi
