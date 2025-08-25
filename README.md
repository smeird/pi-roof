# pi-roof

Web interface for monitoring and controlling a small observatory and related hardware.

## Features

- **Observatory control panel** (`index.html`): displays sensor values such as clouds, rain, light, dew point, SQM and star count via MQTT and provides toggle buttons for devices.
- **Real-time bullet charts**: the main dashboard now shows live conditions like clouds, light, rain, humidity and sky darkness with Highcharts bullet graphs driven by MQTT.
- **Roof and GPIO control** (`script.php`, `manualjob.php`): PHP endpoints that run system scripts to open/close the roof or toggle specific GPIO pins on the Raspberry Pi.

The site depends on MQTT for live updates and on external Python scripts for hardware actions.
