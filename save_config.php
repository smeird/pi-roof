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
    'INFLUX_TOKEN'
];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

foreach ($allowed as $key) {
    if (isset($input[$key])) {
        setSetting($key, $input[$key]);
    }
}

if (isset($input['sensors']) && is_array($input['sensors'])) {
    replaceSensors($input['sensors']);
}

if (isset($input['switches']) && is_array($input['switches'])) {
    replaceSwitches($input['switches']);
}

if (isset($input['roof']) && is_array($input['roof'])) {
    $roof = [
        'open_path' => $input['roof']['open']['path'] ?? '',
        'open_limit' => $input['roof']['open']['limit'] ?? '',
        'close_path' => $input['roof']['close']['path'] ?? '',
        'close_limit' => $input['roof']['close']['limit'] ?? ''
    ];
    setRoof($roof);
}

echo json_encode(['status' => 'ok']);
?>
