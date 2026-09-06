<?php
function getDb() {
    static $db = null;
    if ($db instanceof SQLite3) {
        return $db;
    }
    $dbPath = getenv('ROOF_CONFIG_DB_PATH') ?: '/var/www/data/config.db';
    $db = new SQLite3($dbPath);
    $db->exec('PRAGMA foreign_keys = ON');
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
    $db->exec('CREATE TABLE IF NOT EXISTS roof_controller (id INTEGER PRIMARY KEY CHECK (id = 1), base_topic TEXT NOT NULL)');
    $db->exec("INSERT OR IGNORE INTO roof_controller (id, base_topic) VALUES (1, 'Observatory/roof-esp')");

    $db->exec('CREATE TABLE IF NOT EXISTS roof_relays (relay_key TEXT PRIMARY KEY, label TEXT NOT NULL, visible INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0)');
    $relayCount = (int)$db->querySingle('SELECT COUNT(*) FROM roof_relays');
    if ($relayCount === 0) {
        $relayDefaults = [
            ['relay3', '12V Power Supply (CH3)'],
            ['relay4', 'White LED (CH4)'],
            ['relay5', 'Red LED (CH5)'],
            ['relay6', 'PC Power (CH6)'],
            ['relay7', 'Dew Heater 12V Power (CH7)'],
            ['relay8', 'Mount/Focus 12V Power (CH8)']
        ];
        $stmt = $db->prepare('INSERT INTO roof_relays (relay_key, label, visible, sort_order) VALUES (:relay_key, :label, 1, :sort_order)');
        foreach ($relayDefaults as $order => $relay) {
            $stmt->bindValue(':relay_key', $relay[0], SQLITE3_TEXT);
            $stmt->bindValue(':label', $relay[1], SQLITE3_TEXT);
            $stmt->bindValue(':sort_order', $order, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }

    $db->exec('CREATE TABLE IF NOT EXISTS quick_links (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL, url TEXT NOT NULL, icon TEXT NOT NULL, visible INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0)');
    $linkCount = (int)$db->querySingle('SELECT COUNT(*) FROM quick_links');
    $linksSeeded = $db->querySingle("SELECT value FROM settings WHERE key = 'QUICK_LINKS_SEEDED'");
    if ($linkCount === 0 && $linksSeeded === null) {
        $linkDefaults = [
            ['AAGSolo', 'http://10.0.179.242', 'fa-mountain-sun'],
            ['Obs Graphs', 'http://data.smeird.com:3000/public-dashboards/9d4866d34e934549a20debd888358718?refresh=1m&from=now-24h&to=now&timezone=browser', 'fa-chart-line'],
            ['Weather', 'http://data.smeird.com:3000/public-dashboards/2ed28400ef714b6899a67ca635137a59', 'fa-cloud-sun'],
            ['Public obs', 'http://ob.smeird.com', 'fa-earth-europe'],
            ['Public Weather', 'http://www.smeird.com', 'fa-droplet'],
            ['Night Forecast', 'https://clearoutside.com/forecast/51.81/-0.29', 'fa-moon'],
            ['SkyCam', 'https://skycam.smeird.com', 'fa-camera']
        ];
        $stmt = $db->prepare('INSERT INTO quick_links (label, url, icon, visible, sort_order) VALUES (:label, :url, :icon, 1, :sort_order)');
        foreach ($linkDefaults as $order => $link) {
            $stmt->bindValue(':label', $link[0], SQLITE3_TEXT);
            $stmt->bindValue(':url', $link[1], SQLITE3_TEXT);
            $stmt->bindValue(':icon', $link[2], SQLITE3_TEXT);
            $stmt->bindValue(':sort_order', $order, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
    $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('QUICK_LINKS_SEEDED', '1')");
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

function getRoofController() {
    $db = getDb();
    $baseTopic = $db->querySingle('SELECT base_topic FROM roof_controller WHERE id = 1');
    $res = $db->query('SELECT relay_key, label, visible FROM roof_relays ORDER BY sort_order, relay_key');
    $relays = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $relays[] = [
            'key' => $row['relay_key'],
            'label' => $row['label'],
            'visible' => (bool)$row['visible']
        ];
    }
    return [
        'baseTopic' => $baseTopic ?: 'Observatory/roof-esp',
        'openSeconds' => (float)getSetting('ROOF_OPEN_SECONDS', '30'),
        'closeSeconds' => (float)getSetting('ROOF_CLOSE_SECONDS', '30'),
        'relays' => $relays
    ];
}

function setRoofController($controller) {
    $db = getDb();
    $db->exec('BEGIN IMMEDIATE');
    try {
        // Optional keys preserve saved durations when an older client saves configuration.
        foreach (['openSeconds' => 'ROOF_OPEN_SECONDS', 'closeSeconds' => 'ROOF_CLOSE_SECONDS'] as $field => $key) {
            if (array_key_exists($field, $controller)) setSetting($key, (string)$controller[$field]);
        }
        $stmt = $db->prepare('REPLACE INTO roof_controller (id, base_topic) VALUES (1, :base_topic)');
        $stmt->bindValue(':base_topic', $controller['baseTopic'], SQLITE3_TEXT);
        $stmt->execute();

        $db->exec('DELETE FROM roof_relays');
        $stmt = $db->prepare('INSERT INTO roof_relays (relay_key, label, visible, sort_order) VALUES (:relay_key, :label, :visible, :sort_order)');
        foreach ($controller['relays'] as $order => $relay) {
            $stmt->bindValue(':relay_key', $relay['key'], SQLITE3_TEXT);
            $stmt->bindValue(':label', $relay['label'], SQLITE3_TEXT);
            $stmt->bindValue(':visible', !empty($relay['visible']) ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':sort_order', $order, SQLITE3_INTEGER);
            $stmt->execute();
        }
        $db->exec('COMMIT');
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

function getQuickLinks() {
    $db = getDb();
    $res = $db->query('SELECT label, url, icon, visible FROM quick_links ORDER BY sort_order, id');
    $links = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $row['visible'] = (bool)$row['visible'];
        $links[] = $row;
    }
    return $links;
}

function replaceQuickLinks($links) {
    $db = getDb();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $db->exec('DELETE FROM quick_links');
        $stmt = $db->prepare('INSERT INTO quick_links (label, url, icon, visible, sort_order) VALUES (:label, :url, :icon, :visible, :sort_order)');
        foreach ($links as $order => $link) {
            $stmt->bindValue(':label', $link['label'], SQLITE3_TEXT);
            $stmt->bindValue(':url', $link['url'], SQLITE3_TEXT);
            $stmt->bindValue(':icon', $link['icon'], SQLITE3_TEXT);
            $stmt->bindValue(':visible', !empty($link['visible']) ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':sort_order', $order, SQLITE3_INTEGER);
            $stmt->execute();
        }
        $db->exec('COMMIT');
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}
?>
