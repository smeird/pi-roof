# Agent Guidelines

- Tailwind CSS (via CDN) is used for styling across the site. Prefer Tailwind utility classes and avoid other CSS frameworks.
- Record any additional project decisions or conventions in this file.
- Font Awesome (via CDN) is used for interface icons across the site.
- No automated tests are configured; run `npm test` to confirm.
- Removed obsolete `cold.html` panel.
- Removed legacy PHP and HTML files (e.g. `script.php`, `manualjob.php`, `recipies.html`, `api.php.old`, `getvaluestatus.php.old`, `index.php.old`, `index2.php`, `base.css`).
- Navigation links now live in a Tailwind-styled sidebar instead of a top bar.
- Main header includes a telescope icon linking back to the home screen.
- Toggle controls should use Tailwind switch-style buttons with a sliding knob.
- Main layout uses a full-height flex container with a sticky sidebar and wider grid spacing.

- Sidebar collapses into a hamburger menu on small screens so mobile users can access controls.
- MQTT connection status indicator sits at the bottom of the sidebar.

- Sensor data uses Highcharts solid gauges with Tailwind indicators; the SkyCam image sits in its own card.
- Sensor values are rounded to at most one decimal place before display.

- Sensors include red/green status dots driven by per-sensor thresholds configurable in settings.

- Sensors may specify InfluxDB `influxMeasurement` and `influxField` values in settings to enable historical data charts. When present, the sensor card displays a history icon linking to `history.html`.
- Global InfluxDB connection settings (host, organization, bucket) are editable in settings and returned via `get_config.php`.
- InfluxDB queries must use `POST` requests to `/api/v2/query` with `Content-Type: application/vnd.flux` and `Accept: application/csv` headers.

- When all sensors indicate green, the sensors card shows a green border.

- Roof limit indicators show phrases like "Roof is open"/"Roof isn't open" and "Roof is closed"/"Roof isn't closed" based on switch state.

- Sensors now allow selecting if green status triggers when the value is above or below the threshold via a dropdown in settings.
- Graphs use a single Highcharts line chart with one series per sensor instead of individual bullet charts.
- Line chart gives each sensor its own Y axis and uses dotted segments when values fall outside the green threshold.
- Line charts display each sensor's unit in Y-axis titles and tooltips.

- Use `js/mqttClient.js` for all MQTT connections instead of direct library calls.
- Highcharts solid gauges require `highcharts-more.js` and are placed inside Tailwind card wrappers.
- Main page background uses a subtle top-to-bottom gray gradient.

- SkyCam image sits in a bottom card section.
- SkyCam image loads from an MQTT topic configured in settings (`MQTT_SKYCAM_TOPIC`).
- MQTT helper now emits `status` events (`connecting`, `connected`, `disconnected`, `reconnecting`, `error`) and uses exponential backoff reconnects up to 30s.
- MQTT helper now treats broker `offline` events the same as `close`, ensuring reconnection when the connection drops silently.
- MQTT helper's `error` handler forces a client close to trigger reconnection, calling `handleDisconnect` directly if already closed.
- MQTT topics should be derived from DOM elements with `data-topic`; flag topics without UI colour changes using `data-static`.
- MQTT connection settings now load from a SQLite `config.db` via `js/mqttConfig.js` fetching `/get_config.php`.
- A `settings.html` page allows editing these values and persists them through `/save_config.php`.
- Client-side config fetches should use absolute paths (e.g. `/get_config.php`) to ensure correct resolution from nested directories.

- Configuration endpoints respond with JSON and proper `Content-Type` headers; client pages should surface server error messages.
- Database migrations should verify column existence using `PRAGMA table_info` before attempting schema alterations.

- Sidebar includes a theme selector (Light/Dark/System) storing preference in `localStorage`. The `dark` class on `<html>` follows the selected theme, with `System` using `prefers-color-scheme`.
- Interactive elements like buttons and form inputs should provide matching `dark:` variants for colors and borders.
- Auxiliary switches on the dashboard are driven by the settings `switches` list and render in their own section below the primary roof relay switches.
- Switch settings now include separate `commandPath` and `statusPath` topics to support multi-topic MQTT control.
- RoRo roof controller scripts live in `scripts/roof/`, and the Node-RED flow export is stored at `nodered/flows/roof-api.json`.
- The roof API accepts a `fault_clear` action; the disconnect script triggers it after disconnect.
- Hue devices in Node-RED publish to MQTT status topics under `Observatory/hue/<device>/status` and accept commands at `Observatory/hue/<device>/command`.
