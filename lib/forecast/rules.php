<?php
function forecast_defaults(): array {
    return ['greenCloud' => 0, 'amberCloud' => 40, 'useSeeing' => false, 'greenSeeing' => 5.5, 'amberSeeing' => 4];
}

function forecast_settings(): array {
    $stored = json_decode(getSetting('FORECAST_RULES', '{}'), true);
    return array_replace(forecast_defaults(), is_array($stored) ? $stored : []);
}

function astro_forecast_number($value, float $max): ?float {
    return is_numeric($value) && is_finite((float)$value) && (float)$value >= 0 && (float)$value <= $max ? (float)$value : null;
}

function astro_classify_forecast(array $row, array $rules): array {
    $layers = [];
    $labels = [];
    foreach (['lowcloud' => 'Low', 'medcloud' => 'Middle', 'highcloud' => 'High'] as $key => $name) {
        $layers[] = $number = astro_forecast_number($row[$key] ?? null, 100);
        $labels[] = $name . ': ' . ($number === null ? 'unavailable' : $number . '%');
    }
    $seeing = astro_forecast_number($row['seeingIndex'] ?? null, 10);
    $labels[] = 'Seeing: ' . ($seeing === null ? 'unavailable' : $seeing . '/10');
    $label = implode(' · ', $labels);
    if (in_array(null, $layers, true) || ($rules['useSeeing'] && $seeing === null)) {
        return ['state' => 'unknown', 'cloud' => null, 'label' => 'Forecast incomplete · ' . $label];
    }
    $cloud = max(...$layers);
    $green = $cloud <= $rules['greenCloud'] && (!$rules['useSeeing'] || $seeing >= $rules['greenSeeing']);
    $amber = $cloud <= $rules['amberCloud'] && (!$rules['useSeeing'] || $seeing >= $rules['amberSeeing']);
    return ['state' => $green ? 'good' : ($amber ? 'fair' : 'poor'), 'cloud' => $cloud, 'label' => $label];
}
