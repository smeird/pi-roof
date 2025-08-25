# pi-roof

Web interface for monitoring and controlling a small observatory and related hardware.

## Features

- **Observatory control panel** (`index.html`): displays sensor values such as clouds, rain, light, dew point, SQM and star count via MQTT and provides toggle buttons for devices.
- **Roof and GPIO control** (`script.php`, `manualjob.php`): PHP endpoints that run system scripts to open/close the roof or toggle specific GPIO pins on the Raspberry Pi.

The site depends on MQTT for live updates and on external Python scripts for hardware actions.

## MQTT Configuration

Connection details are centralised in `js/mqttConfig.js`, which reads values from environment variables or a `.env` file that is loaded at build time.

Supported variables:

```
MQTT_BROKER_URL=ws://homeassistant.smeird.com
MQTT_PORT=1884
MQTT_USERNAME=your-user
MQTT_PASSWORD=your-pass
MQTT_DASHBOARD_TOPICS=topic1,topic2
```

During deployment, expose these variables in the environment or inject them into the page via a global `window.__ENV` object before loading scripts:

```
<script>
  window.__ENV = {
    MQTT_BROKER_URL: 'wss://mqtt.example.com',
    MQTT_PORT: '8083',
    MQTT_USERNAME: 'user',
    MQTT_PASSWORD: 'pass'
  };
</script>
```

Tools like [dotenv](https://github.com/motdotla/dotenv) can load the variables from a `.env` file during a build step.
