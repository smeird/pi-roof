# Node-RED deployment

This project provides a single HTTP endpoint (`POST /api/roof`) that translates requests into MQTT commands and waits for roof state changes.

## Import the flow

1. Open Node-RED and import `nodered/flows/roof-api.json`.
2. The flow references an **existing MQTT broker configuration node** named "Existing MQTT Broker". Edit the MQTT config node to point at your broker (or map it to an existing broker node). The flow does **not** hardcode hostnames or credentials.
3. Deploy the flow.

## HTTP endpoint

```
POST /api/roof
Content-Type: application/json

{ "action": "open|close|abort|status|connect|disconnect|fault_clear|park|unpark" }
```

Responses are always JSON:

```json
{ "ok": true, "state": "OPEN", "message": "Open complete", "timestamp": "2024-01-01T00:00:00Z" }
```

## Environment configuration

The flow reads timeouts from environment variables (defaults shown):

- `OPEN_TIMEOUT_SECS=900`
- `CLOSE_TIMEOUT_SECS=900`
- `ABORT_TIMEOUT_SECS=90`
- `CONNECT_TIMEOUT_SECS=5`

These are loaded at startup by the "Init config" inject node.

## MQTT topics

Commands are published to:

- `Observatory/roof-esp/command/open` (`payload: "1"`)
- `Observatory/roof-esp/command/close` (`payload: "1"`)
- `Observatory/roof-esp/command/stop` (`payload: "1"`)
- `Observatory/roof-esp/command/snapshot` (`payload: "1"`)
- `Observatory/roof-esp/command/fault_clear` (`payload: "1"`)

Telemetry subscriptions include:

- `Observatory/roof-esp/online`
- `Observatory/roof-esp/open_limit`
- `Observatory/roof-esp/close_limit`
- `Observatory/roof-esp/moving`
- `Observatory/roof-esp/enabled`
- `Observatory/roof-esp/fault`
- `Observatory/roof-esp/fault_code`
- `Observatory/roof-esp/state/open_motor`
- `Observatory/roof-esp/state/close_motor`
