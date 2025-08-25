# Agent Guidelines

- Tailwind CSS (via CDN) is used for styling across the site. Prefer Tailwind utility classes and avoid other CSS frameworks.
- Record any additional project decisions or conventions in this file.
- No automated tests are configured; run `npm test` to confirm.
- Removed obsolete `cold.html` panel.
- Navigation links now live in a Tailwind-styled sidebar instead of a top bar.
- Toggle controls should use Tailwind switch-style buttons with a sliding knob.
- Main layout uses a full-height flex container with a sticky sidebar and wider grid spacing.

- Sensor data uses Highcharts solid gauges with Tailwind indicators; SkyCam image shares a two-column layout with the real-time graph.

- Use `js/mqttClient.js` for all MQTT connections instead of direct library calls.

- Highcharts solid gauges require `highcharts-more.js` and are placed inside Tailwind card wrappers.
- Main page background uses a subtle top-to-bottom gray gradient.
- SkyCam image and real-time graph sit in a bottom card section.
