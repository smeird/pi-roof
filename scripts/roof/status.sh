#!/usr/bin/env bash
set -euo pipefail

ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-20}"

response="$(curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"status"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}")"


python3 - <<<"$response" <<'PY'
import json
import sys

try:
    data = json.load(sys.stdin)
except Exception:
    print("ERROR")
    sys.exit(1)

ok = bool(data.get("ok"))
state = data.get("state", "UNKNOWN")

mapping = {
    "OPEN": "OPEN",
    "CLOSED": "CLOSED",
    "OPENING": "OPENING",
    "CLOSING": "CLOSING",
    "ABORTED": "ERROR",
    "FAULT": "ERROR",
    "UNKNOWN": "ERROR",
}

print(mapping.get(state, "ERROR"))

if not ok:
    sys.exit(1)
PY
