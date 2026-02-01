#!/usr/bin/env bash
# Power up observatory equipment for an observing session.
set -euo pipefail
# Ensure a predictable PATH for system utilities like mosquitto_pub.
export PATH="/usr/bin:/bin"

MOSQUITTO_PUB_BIN="${MOSQUITTO_PUB_BIN:-mosquitto_pub}"
MQTT_HOST="${MQTT_HOST:-localhost}"
MQTT_PORT="${MQTT_PORT:-1883}"
MQTT_USERNAME="${MQTT_USERNAME:-}"
MQTT_PASSWORD="${MQTT_PASSWORD:-}"
MQTT_BASE_TOPIC="${MQTT_BASE_TOPIC:-Observatory/roof-esp/command}"
MQTT_QOS="${MQTT_QOS:-1}"
MQTT_ON_PAYLOAD="${MQTT_ON_PAYLOAD:-1}"

if ! command -v "${MOSQUITTO_PUB_BIN}" >/dev/null 2>&1; then
  echo "mosquitto_pub not found; set MOSQUITTO_PUB_BIN or install mosquitto clients." >&2
  exit 1
fi

publish_command() {
  local topic="$1"
  local payload="$2"
  local args=("-h" "$MQTT_HOST" "-p" "$MQTT_PORT" "-q" "$MQTT_QOS" "-t" "$topic" "-m" "$payload")
  if [[ -n "$MQTT_USERNAME" ]]; then
    args+=("-u" "$MQTT_USERNAME")
  fi
  if [[ -n "$MQTT_PASSWORD" ]]; then
    args+=("-P" "$MQTT_PASSWORD")
  fi
  "$MOSQUITTO_PUB_BIN" "${args[@]}"
}

publish_command "${MQTT_BASE_TOPIC}/relay3" "$MQTT_ON_PAYLOAD"
publish_command "${MQTT_BASE_TOPIC}/relay7" "$MQTT_ON_PAYLOAD"
publish_command "${MQTT_BASE_TOPIC}/relay8" "$MQTT_ON_PAYLOAD"
