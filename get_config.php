<?php
require_once __DIR__ . '/config.php';

$defaults = [
    'MQTT_BROKER_URL' => 'ws://homeassistant.smeird.com',
    'MQTT_PORT' => '1884',
    'MQTT_USERNAME' => '',
    'MQTT_PASSWORD' => '',
    'MQTT_DASHBOARD_TOPICS' => '',
    'MQTT_SKYCAM_TOPIC' => ''
];

$stored = getAllSettings();
$config = [];
foreach ($defaults as $key => $value) {
    $config[$key] = $stored[$key] ?? $value;
}

$roof = getRoof();

header('Content-Type: application/json');
echo json_encode([
    'brokerUrl' => $config['MQTT_BROKER_URL'],
    'port' => (int)$config['MQTT_PORT'],
    'username' => $config['MQTT_USERNAME'],
    'password' => $config['MQTT_PASSWORD'],
    'skyCamTopic' => $config['MQTT_SKYCAM_TOPIC'],
    'dashboardTopics' => array_values(array_filter(explode(',', $config['MQTT_DASHBOARD_TOPICS']))),
    'sensors' => getSensors(),
    'switches' => getSwitches(),
    'roof' => [
        'open' => ['path' => $roof['open_path'], 'limit' => $roof['open_limit']],
        'close' => ['path' => $roof['close_path'], 'limit' => $roof['close_limit']]
    ]
]);
?>
