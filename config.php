<?php
function getDb() {
    $db = new SQLite3('/var/www/data/config.db');
    // create tables for simple key/value settings as well as dynamic lists
    $db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS sensors (id INTEGER PRIMARY KEY AUTOINCREMENT, path TEXT UNIQUE, unit TEXT, name TEXT, green_value TEXT, green_direction TEXT, influx_measurement TEXT, influx_field TEXT)');
    $cols = $db->query('PRAGMA table_info(sensors)');
    $hasGreen = false;
    $hasDirection = false;
    $hasMeasurement = false;
    $hasField = false;
    while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'green_value') {
            $hasGreen = true;
        }
        if ($col['name'] === 'green_direction') {
            $hasDirection = true;
        }
        if ($col['name'] === 'influx_measurement') {
            $hasMeasurement = true;
        }
        if ($col['name'] === 'influx_field') {
            $hasField = true;
        }
    }
    if (!$hasGreen) {
        $db->exec('ALTER TABLE sensors ADD COLUMN green_value TEXT');
    }
    if (!$hasDirection) {
        $db->exec('ALTER TABLE sensors ADD COLUMN green_direction TEXT');
    }
    if (!$hasMeasurement) {
        $db->exec('ALTER TABLE sensors ADD COLUMN influx_measurement TEXT');
    }
    if (!$hasField) {
        $db->exec('ALTER TABLE sensors ADD COLUMN influx_field TEXT');
    }
    $db->exec('CREATE TABLE IF NOT EXISTS switches (id INTEGER PRIMARY KEY AUTOINCREMENT, path TEXT UNIQUE, name TEXT, command_path TEXT, status_path TEXT)');
    $switchCols = $db->query('PRAGMA table_info(switches)');
    $hasCommandPath = false;
    $hasStatusPath = false;
    while ($col = $switchCols->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'command_path') {
            $hasCommandPath = true;
        }
        if ($col['name'] === 'status_path') {
            $hasStatusPath = true;
        }
    }
    if (!$hasCommandPath) {
        $db->exec('ALTER TABLE switches ADD COLUMN command_path TEXT');
    }
    if (!$hasStatusPath) {
        $db->exec('ALTER TABLE switches ADD COLUMN status_path TEXT');
    }
    $db->exec('CREATE TABLE IF NOT EXISTS roof (id INTEGER PRIMARY KEY CHECK (id = 1), open_path TEXT, open_limit TEXT, close_path TEXT, close_limit TEXT)');
    return $db;
}

function getAllSettings() {
    $db = getDb();
    $res = $db->query('SELECT key, value FROM settings');
    $settings = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function getSetting($key, $default = '') {
    $db = getDb();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $result ? $result['value'] : $default;
}

function setSetting($key, $value) {
    $db = getDb();
    $stmt = $db->prepare('REPLACE INTO settings (key, value) VALUES (:key, :value)');
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':value', $value, SQLITE3_TEXT);
    $stmt->execute();
}

function getSensors() {
    $db = getDb();
    $res = $db->query('SELECT path, unit, name, green_value, green_direction, influx_measurement, influx_field FROM sensors ORDER BY id');
    $sensors = [];
    if (!$res) {
        return $sensors;
    }
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $row['green'] = $row['green_value'];
        $row['greenDirection'] = $row['green_direction'];
        $row['influxMeasurement'] = $row['influx_measurement'];
        $row['influxField'] = $row['influx_field'];
        unset($row['green_value'], $row['green_direction'], $row['influx_measurement'], $row['influx_field']);
        $sensors[] = $row;
    }
    return $sensors;
}

function replaceSensors($sensors) {
    $db = getDb();
    $db->exec('DELETE FROM sensors');
    $stmt = $db->prepare('INSERT INTO sensors (path, unit, name, green_value, green_direction, influx_measurement, influx_field) VALUES (:path, :unit, :name, :green_value, :green_direction, :influx_measurement, :influx_field)');
    foreach ($sensors as $sensor) {
        if (!isset($sensor['path'])) continue;
        $stmt->bindValue(':path', $sensor['path'], SQLITE3_TEXT);
        $stmt->bindValue(':unit', $sensor['unit'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':name', $sensor['name'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':green_value', $sensor['green'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':green_direction', $sensor['greenDirection'] ?? 'below', SQLITE3_TEXT);
        $stmt->bindValue(':influx_measurement', $sensor['influxMeasurement'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':influx_field', $sensor['influxField'] ?? '', SQLITE3_TEXT);
        $stmt->execute();
    }
}

function getSwitches() {
    $db = getDb();
    $res = $db->query('SELECT path, name, command_path, status_path FROM switches ORDER BY id');
    $switches = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $commandPath = $row['command_path'] ?? '';
        $statusPath = $row['status_path'] ?? '';
        $fallback = $row['path'] ?? '';
        $row['commandPath'] = $commandPath !== '' ? $commandPath : $fallback;
        $row['statusPath'] = $statusPath !== '' ? $statusPath : $fallback;
        unset($row['command_path'], $row['status_path']);
        $switches[] = $row;
    }
    return $switches;
}

function replaceSwitches($switches) {
    $db = getDb();
    $db->exec('DELETE FROM switches');
    $stmt = $db->prepare('INSERT INTO switches (path, name, command_path, status_path) VALUES (:path, :name, :command_path, :status_path)');
    foreach ($switches as $sw) {
        $path = $sw['path'] ?? $sw['commandPath'] ?? $sw['statusPath'] ?? '';
        if ($path === '') continue;
        $commandPath = $sw['commandPath'] ?? $path;
        $statusPath = $sw['statusPath'] ?? $path;
        $stmt->bindValue(':path', $path, SQLITE3_TEXT);
        $stmt->bindValue(':name', $sw['name'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':command_path', $commandPath, SQLITE3_TEXT);
        $stmt->bindValue(':status_path', $statusPath, SQLITE3_TEXT);
        $stmt->execute();
    }
}

function getRoof() {
    $db = getDb();
    $res = $db->query('SELECT open_path, open_limit, close_path, close_limit FROM roof WHERE id = 1');
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if (!$row) {
        $row = ['open_path' => '', 'open_limit' => '', 'close_path' => '', 'close_limit' => ''];
    }
    return $row;
}

function setRoof($data) {
    $db = getDb();
    $stmt = $db->prepare('REPLACE INTO roof (id, open_path, open_limit, close_path, close_limit) VALUES (1, :open_path, :open_limit, :close_path, :close_limit)');
    $stmt->bindValue(':open_path', $data['open_path'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':open_limit', $data['open_limit'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':close_path', $data['close_path'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':close_limit', $data['close_limit'] ?? '', SQLITE3_TEXT);
    $stmt->execute();
}
?>
