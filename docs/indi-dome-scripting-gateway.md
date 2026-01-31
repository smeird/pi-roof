# INDI Dome Scripting Gateway (RoRo roof)

This project implements the INDI Dome Scripting Gateway pattern for a roll-off roof. Point the INDI driver at the scripts in `scripts/roof/` and the driver will call the shell scripts with **no arguments** (except for `status.sh`, which receives a temporary status file path as its first argument).

## INDI setup

1. Copy the `scripts/roof/` directory to the machine running the INDI Dome Scripting Gateway.
2. Ensure each script is executable:
   ```bash
   chmod +x scripts/roof/*.sh
   ```
3. In the INDI Dome Scripting Gateway driver, set the script paths as follows:
   - Open: `scripts/roof/open.sh`
   - Close: `scripts/roof/close.sh`
   - Abort: `scripts/roof/abort.sh`
   - Status: `scripts/roof/status.sh`
   - Connect: `scripts/roof/connect.sh`
   - Disconnect: `scripts/roof/disconnect.sh`
   - Park: `scripts/roof/park.sh`
   - Unpark: `scripts/roof/unpark.sh`

## Status contract

The INDI driver expects the status script to **write a single line to the status file path passed as argument 1**. The format is:

```
<parked> <shutter> <az>
```

- `parked`: `1` when the roof is parked/closed, otherwise `0`.
- `shutter`: `1` when the roof is open, otherwise `0`.
- `az`: a floating-point azimuth value (use `0` for roll-off roofs).

The script should exit non-zero if it cannot produce a valid line.

## Environment variables

The scripts use these environment variables (defaults shown):

- `ROOF_BASE_URL` (default: `http://data.smeird.com:1880`)
- `ROOF_HTTP_PATH` (default: `/api/roof`)
- `CURL_TIMEOUT_SECS` (default varies by script)

See the `README.md` and `.env.example` for a concise summary.
