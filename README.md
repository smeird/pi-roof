# pi-roof

Web interface for monitoring and controlling a small observatory and related hardware.

## Features

- **Observatory control panel** (`index.html`): displays sensor values such as clouds, rain, light, dew point, SQM and star count via MQTT and provides toggle buttons for devices.
- **Real-time graphs** (`realtimegraph.php`): uses Highcharts and MQTT to plot live conditions including clouds, light, rain, humidity and sky darkness.
- **Roof and GPIO control** (`script.php`, `manualjob.php`): PHP endpoints that run system scripts to open/close the roof or toggle specific GPIO pins on the Raspberry Pi.

The site depends on MQTT for live updates and on external Python scripts for hardware actions.
