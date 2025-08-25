<?php
require_once __DIR__ . '/config.php';

// Always return JSON so the client can parse the response reliably
header('Content-Type: application/json');

$allowed = [
    'MQTT_BROKER_URL',
    'MQTT_PORT',
    'MQTT_USERNAME',
    'MQTT_PASSWORD',
    'MQTT_DASHBOARD_TOPICS'
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

echo json_encode(['status' => 'ok']);
?>
