<?php
require_once __DIR__ . '/config.php';

// Always return JSON so the client can parse the response reliably
header('Content-Type: application/json');

$allowed = [
    'MQTT_BROKER_URL',
    'MQTT_PORT',
    'MQTT_USERNAME',
    'MQTT_PASSWORD',
    'MQTT_DASHBOARD_TOPICS',
    'MQTT_SKYCAM_TOPIC',
    'INFLUX_HOST',
    'INFLUX_ORG',
    'INFLUX_BUCKET',
    'INFLUX_TOKEN',
    'DASHBOARD_SHOW_CHART',
    'DASHBOARD_SHOW_SKYCAM',
    'DASHBOARD_SHOW_CAMERA'
];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$errors = [];

if (isset($input['MQTT_BROKER_URL']) && !preg_match('#^wss?://#i', trim((string)$input['MQTT_BROKER_URL']))) {
    $errors['MQTT_BROKER_URL'] = 'Use a ws:// or wss:// broker URL.';
}
if (isset($input['MQTT_PORT'])) {
    $port = filter_var($input['MQTT_PORT'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false) {
        $errors['MQTT_PORT'] = 'Port must be between 1 and 65535.';
    }
}

if (isset($input['sensors'])) {
    if (!is_array($input['sensors'])) {
        $errors['sensors'] = 'Sensors must be a list.';
    } else {
        $sensorPaths = [];
        foreach ($input['sensors'] as $index => $sensor) {
            $path = trim((string)($sensor['path'] ?? ''));
            $name = trim((string)($sensor['name'] ?? ''));
            $direction = $sensor['greenDirection'] ?? 'below';
            $green = trim((string)($sensor['green'] ?? ''));
            $amber = trim((string)($sensor['amber'] ?? ''));
            if ($path === '') $errors["sensors.$index.path"] = 'MQTT topic is required.';
            if ($name === '') $errors["sensors.$index.name"] = 'Sensor name is required.';
            if ($path !== '' && isset($sensorPaths[$path])) $errors["sensors.$index.path"] = 'Sensor topics must be unique.';
            $sensorPaths[$path] = true;
            if (!in_array($direction, ['above', 'below'], true)) $errors["sensors.$index.greenDirection"] = 'Choose above or below.';
            if ($green !== '' && (!is_numeric($green) || !is_finite((float)$green))) $errors["sensors.$index.green"] = 'Threshold must be a finite number.';
            if ($amber !== '') {
                if (!is_numeric($amber) || !is_finite((float)$amber)) {
                    $errors["sensors.$index.amber"] = 'Amber threshold must be a finite number.';
                } elseif ($green === '' || !is_numeric($green) || !is_finite((float)$green)) {
                    $errors["sensors.$index.green"] = 'Set a green threshold before adding amber.';
                } elseif (($direction === 'below' && (float)$amber <= (float)$green) || ($direction === 'above' && (float)$amber >= (float)$green)) {
                    $errors["sensors.$index.amber"] = $direction === 'below' ? 'Amber must be greater than green.' : 'Amber must be less than green.';
                }
            }
            $measurement = trim((string)($sensor['influxMeasurement'] ?? ''));
            $field = trim((string)($sensor['influxField'] ?? ''));
            if (($measurement === '') !== ($field === '')) $errors["sensors.$index.history"] = 'Measurement and field must be provided together.';
        }
    }
}

if (isset($input['switches'])) {
    if (!is_array($input['switches'])) {
        $errors['switches'] = 'Auxiliary devices must be a list.';
    } else {
        $switchTopics = [];
        foreach ($input['switches'] as $index => $switch) {
            $name = trim((string)($switch['name'] ?? ''));
            $commandPath = trim((string)($switch['commandPath'] ?? ''));
            $statusPath = trim((string)($switch['statusPath'] ?? ''));
            if ($name === '') $errors["switches.$index.name"] = 'Device name is required.';
            if ($commandPath === '') $errors["switches.$index.commandPath"] = 'Command topic is required.';
            if ($statusPath === '') $errors["switches.$index.statusPath"] = 'Status topic is required.';
            if ($statusPath !== '' && isset($switchTopics[$statusPath])) $errors["switches.$index.statusPath"] = 'Status topics must be unique.';
            $switchTopics[$statusPath] = true;
        }
    }
}

if (isset($input['roofController'])) {
    $controller = $input['roofController'];
    if (!is_array($controller)) {
        $errors['roofController'] = 'Roof controller must be an object.';
    } else {
        $baseTopic = trim((string)($controller['baseTopic'] ?? ''), " \t\n\r\0\x0B/");
        if ($baseTopic === '' || strpbrk($baseTopic, '#+') !== false) {
            $errors['roofController.baseTopic'] = 'Enter a base topic without MQTT wildcards.';
        }
        $requiredRelayKeys = ['relay3', 'relay4', 'relay5', 'relay6', 'relay7', 'relay8'];
        foreach (['openSeconds', 'closeSeconds'] as $field) {
            if (!array_key_exists($field, $controller)) continue;
            $value = $controller[$field];
            if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value) || !is_finite((float)$value) || (float)$value < 1 || (float)$value > 900) {
                $errors["roofController.$field"] = 'Enter a travel time between 1 and 900 seconds.';
            }
        }
        $relays = is_array($controller['relays'] ?? null) ? $controller['relays'] : [];
        $relayKeys = [];
        foreach ($relays as $index => $relay) {
            $key = $relay['key'] ?? '';
            $label = trim((string)($relay['label'] ?? ''));
            if (!in_array($key, $requiredRelayKeys, true) || isset($relayKeys[$key])) {
                $errors["roofController.relays.$index.key"] = 'Relay keys must be unique relay3 through relay8.';
            }
            if ($label === '') $errors["roofController.relays.$index.label"] = 'Relay label is required.';
            $relayKeys[$key] = true;
        }
        if (count($relayKeys) !== 6) $errors['roofController.relays'] = 'All six controller relays are required.';
    }
}

if (isset($input['quickLinks'])) {
    $approvedIcons = ['fa-mountain-sun', 'fa-chart-line', 'fa-cloud-sun', 'fa-earth-europe', 'fa-droplet', 'fa-moon', 'fa-camera', 'fa-link', 'fa-house', 'fa-server', 'fa-gauge-high'];
    if (!is_array($input['quickLinks'])) {
        $errors['quickLinks'] = 'Quick links must be a list.';
    } else {
        foreach ($input['quickLinks'] as $index => $link) {
            $label = trim((string)($link['label'] ?? ''));
            $url = trim((string)($link['url'] ?? ''));
            $icon = $link['icon'] ?? '';
            if ($label === '') $errors["quickLinks.$index.label"] = 'Link label is required.';
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) $errors["quickLinks.$index.url"] = 'Use a valid http:// or https:// URL.';
            if (!in_array($icon, $approvedIcons, true)) $errors["quickLinks.$index.icon"] = 'Choose an approved icon.';
        }
    }
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => 'Please correct the highlighted settings.', 'fields' => $errors]);
    exit;
}

try {
    foreach ($allowed as $key) {
        if (array_key_exists($key, $input)) {
            $value = $input[$key];
            if (in_array($key, ['DASHBOARD_SHOW_CHART', 'DASHBOARD_SHOW_SKYCAM', 'DASHBOARD_SHOW_CAMERA'], true)) {
                $value = !empty($value) ? '1' : '0';
            }
            setSetting($key, trim((string)$value));
        }
    }

    if (isset($input['sensors'])) replaceSensors($input['sensors']);
    if (isset($input['switches'])) replaceSwitches($input['switches']);
    if (isset($input['roofController'])) {
        $input['roofController']['baseTopic'] = trim((string)$input['roofController']['baseTopic'], " \t\n\r\0\x0B/");
        setRoofController($input['roofController']);
    }
    if (isset($input['quickLinks'])) replaceQuickLinks($input['quickLinks']);

    // Continue accepting the legacy roof payload for older clients. The redesigned UI does not use it.
    if (isset($input['roof']) && is_array($input['roof'])) {
        setRoof([
            'open_path' => $input['roof']['open']['path'] ?? '',
            'open_limit' => $input['roof']['open']['limit'] ?? '',
            'close_path' => $input['roof']['close']['path'] ?? '',
            'close_limit' => $input['roof']['close']['limit'] ?? ''
        ]);
    }
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to save configuration.']);
    exit;
}

echo json_encode(['status' => 'ok']);
?>
