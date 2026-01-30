#!/usr/bin/env bash
set -euo pipefail
export PATH="/usr/bin:/bin"

# INDI Dome Scripting Gateway: exit 0 once the command request is sent.
ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

if ! curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"close"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}" > /dev/null; then
  exit 1
fi
