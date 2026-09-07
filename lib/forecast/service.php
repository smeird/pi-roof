<?php
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/planner.php';

function forecast_night_date(int $now): string {
    $today = date('Y-m-d', $now);
    $sunrise = astro_sun_event($today, 'sunrise');
    return $sunrise !== null && $now < $sunrise ? date('Y-m-d', strtotime($today . ' -1 day')) : $today;
}

function forecast_fetch_rows(string $cachePath, int $now): array {
    $cache = is_file($cachePath) ? json_decode((string)@file_get_contents($cachePath), true) : null;
    $cache = is_array($cache) ? $cache : [];
    $age = $now - (int)($cache['fetched_at'] ?? 0);
    if ($age >= 0 && $age < 3600 && is_array($cache['rows'] ?? null)) return $cache + ['stale' => false];
    $lock = @fopen($cachePath . '.lock', 'c');
    if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
        try {
            $context = stream_context_create(['http' => ['timeout' => 8, 'header' => "Accept: application/json\r\nUser-Agent: RoofNightPlanner/1.0\r\n"]]);
            $json = @file_get_contents('https://ws1.metcheck.com/ENGINE/v9_0/json.asp?lat=51.81&lon=-0.29&lid=58143&Fc=As', false, $context);
            $decoded = $json === false ? null : json_decode($json, true);
            $rows = $decoded['metcheckData']['forecastLocation']['forecast'] ?? null;
            if (!is_array($rows) || !$rows) throw new RuntimeException('Forecast unavailable');
            $merged = [];
            foreach (array_merge($cache['rows'] ?? [], $rows) as $row) {
                if (!is_array($row) || !is_string($row['utcTime'] ?? null)) continue;
                $timestamp = astro_forecast_timestamp($row['utcTime']);
                if ($timestamp !== null && $timestamp > $now - 2 * 86400) $merged[$row['utcTime']] = $row;
            }
            if (!$merged) throw new RuntimeException('No dated forecast rows');
            // Preserve earlier hours of the current night when the provider advances its feed.
            $fresh = ['fetched_at' => $now, 'rows' => array_values($merged)];
            $temporary = tempnam(dirname($cachePath), 'roof-forecast-');
            if ($temporary !== false) {
                if (file_put_contents($temporary, json_encode($fresh)) !== false) @rename($temporary, $cachePath);
                if (is_file($temporary)) unlink($temporary);
            }
            return $fresh + ['stale' => false];
        } catch (Throwable $error) {
            // A labelled, bounded stale cache is preferable to inventing clear sky.
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            $lock = null;
        }
    }
    if (is_resource($lock)) fclose($lock);
    return ['fetched_at' => $cache['fetched_at'] ?? null, 'rows' => $age >= 0 && $age <= 6 * 3600 ? ($cache['rows'] ?? []) : [], 'stale' => true];
}
