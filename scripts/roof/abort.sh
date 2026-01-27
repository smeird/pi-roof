#!/usr/bin/env bash
set -euo pipefail

ROOF_BASE_URL="${ROOF_BASE_URL:-http://data.smeird.com:1880}"
ROOF_HTTP_PATH="${ROOF_HTTP_PATH:-/api/roof}"
CURL_TIMEOUT_SECS="${CURL_TIMEOUT_SECS:-90}"

response="$(curl -fsS --max-time "${CURL_TIMEOUT_SECS}" -H "Content-Type: application/json" -d '{"action":"abort"}' "${ROOF_BASE_URL}${ROOF_HTTP_PATH}")"

ok="$(printf '%s' "$response" | python3 - <<'PY'
import json
import sys
try:
    data = json.load(sys.stdin)
    print(str(bool(data.get("ok"))).lower())
except Exception:
    print("false")
PY
)"

if [[ "$ok" != "true" ]]; then
  exit 1
fi
