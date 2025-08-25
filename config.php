<?php
function getDb() {
    $db = new SQLite3(__DIR__ . '/config.db');
    // create tables for simple key/value settings as well as dynamic lists
    $db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS sensors (id INTEGER PRIMARY KEY AUTOINCREMENT, path TEXT UNIQUE, unit TEXT, name TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS switches (id INTEGER PRIMARY KEY AUTOINCREMENT, path TEXT UNIQUE, name TEXT)');
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
    $res = $db->query('SELECT path, unit, name FROM sensors ORDER BY id');
    $sensors = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $sensors[] = $row;
    }
    return $sensors;
}

function replaceSensors($sensors) {
    $db = getDb();
    $db->exec('DELETE FROM sensors');
    $stmt = $db->prepare('INSERT INTO sensors (path, unit, name) VALUES (:path, :unit, :name)');
    foreach ($sensors as $sensor) {
        if (!isset($sensor['path'])) continue;
        $stmt->bindValue(':path', $sensor['path'], SQLITE3_TEXT);
        $stmt->bindValue(':unit', $sensor['unit'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':name', $sensor['name'] ?? '', SQLITE3_TEXT);
        $stmt->execute();
    }
}

function getSwitches() {
    $db = getDb();
    $res = $db->query('SELECT path, name FROM switches ORDER BY id');
    $switches = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $switches[] = $row;
    }
    return $switches;
}

function replaceSwitches($switches) {
    $db = getDb();
    $db->exec('DELETE FROM switches');
    $stmt = $db->prepare('INSERT INTO switches (path, name) VALUES (:path, :name)');
    foreach ($switches as $sw) {
        if (!isset($sw['path'])) continue;
        $stmt->bindValue(':path', $sw['path'], SQLITE3_TEXT);
        $stmt->bindValue(':name', $sw['name'] ?? '', SQLITE3_TEXT);
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
