<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/forecast/service.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    $now = time();
    $cache = forecast_fetch_rows(getenv('ROOF_FORECAST_CACHE_PATH') ?: sys_get_temp_dir() . '/roof-forecast-v1.json', $now);
    $plan = astro_build_night_plan(forecast_night_date($now), $cache['rows'], forecast_settings());
    echo json_encode(['plan' => $plan, 'fetched_at' => $cache['fetched_at'], 'stale' => $cache['stale'], 'source' => 'Metcheck', 'timezone' => 'Europe/London']);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['error' => 'Night forecast unavailable. Retrying shortly.']);
}
