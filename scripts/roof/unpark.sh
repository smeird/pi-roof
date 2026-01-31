#!/usr/bin/env bash
# Run the roof "unpark" command via the roof API and exit immediately once queued.
set -euo pipefail
# Ensure a predictable PATH for system utilities like curl.
export PATH="/usr/bin:/bin"

# INDI Dome Scripting Gateway: exit 0 once the command request is sent.
# Allow the base URL/path to be overridden by environment variables.
ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
# Cap how long the HTTP request can take.
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

# Fire-and-forget the unpark command so INDI can continue without waiting.
curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"unpark"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}" > /dev/null 2>&1 &
# Detach the background curl from this shell.
disown
# Always exit successfully after queueing the request.
exit 0
