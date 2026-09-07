<?php
// Adapted from smeird/weather frontend/astro/planner.php; display calculations only.
require_once __DIR__ . '/moon.php';
date_default_timezone_set('Europe/London');

function astro_h($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function astro_local_datetime(string $date, string $time, int $dayOffset = 0): ?DateTimeImmutable
{
  $timezone = new DateTimeZone('Europe/London');
  $base = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . substr($time, 0, 5), $timezone);
  if (!$base) {
    return null;
  }
  return $dayOffset === 0 ? $base : $base->modify(($dayOffset > 0 ? '+' : '') . $dayOffset . ' day');
}

function astro_sun_event(string $date, string $event): ?int
{
  $timezone = new DateTimeZone('Europe/London');
  $noon = new DateTimeImmutable($date . ' 12:00', $timezone);
  $events = date_sun_info($noon->getTimestamp(), 51.8, -0.3);
  $timestamp = $events[$event] ?? null;
  return is_int($timestamp) ? $timestamp : null;
}

function astro_darkness_segments(string $date, int $start, int $end): array
{
  $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
  $transitions = [
    [astro_sun_event($date, 'civil_twilight_end'), 'nautical'],
    [astro_sun_event($date, 'nautical_twilight_end'), 'astronomical'],
    [astro_sun_event($date, 'astronomical_twilight_end'), 'dark'],
    [astro_sun_event($nextDate, 'astronomical_twilight_begin'), 'astronomical'],
    [astro_sun_event($nextDate, 'nautical_twilight_begin'), 'nautical'],
    [astro_sun_event($nextDate, 'civil_twilight_begin'), 'civil'],
  ];
  $labels = [
    'civil' => 'Civil twilight',
    'nautical' => 'Nautical twilight',
    'astronomical' => 'Astronomical twilight',
    'dark' => 'Astronomical darkness',
  ];

  $events = [];
  foreach ($transitions as [$timestamp, $state]) {
    if ($timestamp !== null && $timestamp > $start && $timestamp < $end) {
      $events[] = ['timestamp' => $timestamp, 'state' => $state];
    }
  }
  usort($events, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

  $segments = [];
  $cursor = $start;
  $state = 'civil';
  foreach ($events as $event) {
    if ($event['timestamp'] > $cursor) {
      $segments[] = [
        'start' => $cursor,
        'end' => $event['timestamp'],
        'state' => $state,
        'label' => $labels[$state],
        'class' => 'astro-darkness-' . $state,
      ];
    }
    $cursor = $event['timestamp'];
    $state = $event['state'];
  }
  if ($cursor < $end) {
    $segments[] = [
      'start' => $cursor,
      'end' => $end,
      'state' => $state,
      'label' => $labels[$state],
      'class' => 'astro-darkness-' . $state,
    ];
  }
  return $segments;
}

function astro_moon_illumination(int $timestamp): array
{
  $cycleDays = 29.53058867;
  $knownNewMoon = strtotime('2000-01-06 18:14 UTC');
  $age = fmod(($timestamp - $knownNewMoon) / 86400, $cycleDays);
  if ($age < 0) {
    $age += $cycleDays;
  }
  $illumination = (1 - cos(2 * M_PI * $age / $cycleDays)) / 2 * 100;
  $phaseNames = [
    [1.84566, 'New moon'],
    [7.38265, 'Waxing crescent'],
    [9.22831, 'First quarter'],
    [14.76529, 'Waxing gibbous'],
    [16.61096, 'Full moon'],
    [22.14794, 'Waning gibbous'],
    [23.99361, 'Last quarter'],
    [27.68493, 'Waning crescent'],
    [$cycleDays, 'New moon'],
  ];
  $phase = 'New moon';
  foreach ($phaseNames as [$limit, $name]) {
    if ($age < $limit) {
      $phase = $name;
      break;
    }
  }
  return ['illumination' => round($illumination), 'phase' => $phase];
}

function astro_moon_events(string $date): array
{
  static $cache = [];
  if (isset($cache[$date])) {
    return $cache[$date];
  }
  $month = (int) substr($date, 5, 2);
  $day = (int) substr($date, 8, 2);
  $year = (int) substr($date, 0, 4);
  $moon = Moon::calculateMoonTimes($month, $day, $year, 51.8, -0.3);
  $sentinel = mktime(0, 0, 0, $month, $day + 1, $year);
  $utc = new DateTimeZone('UTC');
  $events = [];

  foreach (['rise' => $moon->moonrise, 'set' => $moon->moonset] as $type => $rawTimestamp) {
    if ((int) $rawTimestamp === $sentinel) {
      continue;
    }
    $utcEvent = DateTimeImmutable::createFromFormat(
      '!Y-m-d H:i',
      $date . ' ' . date('H:i', (int) $rawTimestamp),
      $utc
    );
    if ($utcEvent) {
      $events[] = ['timestamp' => $utcEvent->getTimestamp(), 'type' => $type];
    }
  }
  return $cache[$date] = $events;
}

function astro_moon_is_up(int $timestamp): bool
{
  $localDate = date('Y-m-d', $timestamp);
  $dates = [
    date('Y-m-d', strtotime($localDate . ' -1 day')),
    $localDate,
    date('Y-m-d', strtotime($localDate . ' +1 day')),
  ];
  $events = [];
  foreach ($dates as $date) {
    $events = array_merge($events, astro_moon_events($date));
  }
  usort($events, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

  $lastEvent = null;
  foreach ($events as $event) {
    if ($event['timestamp'] > $timestamp) {
      break;
    }
    $lastEvent = $event;
  }
  return $lastEvent !== null && $lastEvent['type'] === 'rise';
}

function astro_sample_moon_segments(int $start, int $end, int $illumination): array
{
  $segments = [];
  $cursor = $start;
  while ($cursor < $end) {
    $next = min($end, $cursor + 900);
    $midpoint = (int) (($cursor + $next) / 2);
    $isUp = astro_moon_is_up($midpoint);
    $key = $isUp ? 'up' : 'down';
    $label = $isUp
      ? 'Moon above horizon · ' . $illumination . '% illuminated'
      : 'Moon below horizon';
    $lastIndex = count($segments) - 1;
    if ($lastIndex >= 0 && $segments[$lastIndex]['state'] === $key) {
      $segments[$lastIndex]['end'] = $next;
    } else {
      $segments[] = [
        'start' => $cursor,
        'end' => $next,
        'state' => $key,
        'label' => $label,
        'class' => 'astro-moon-' . $key,
      ];
    }
    $cursor = $next;
  }
  return $segments;
}

function astro_state_at(array $segments, int $timestamp, string $fallback): string
{
  foreach ($segments as $segment) {
    if ($timestamp >= $segment['start'] && $timestamp < $segment['end']) {
      return $segment['state'];
    }
  }
  return $fallback;
}

function astro_forecast_timestamp(string $value): ?int
{
  if ($value === '') {
    return null;
  }
  try {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
  } catch (Exception $exception) {
    return null;
  }
}

function astro_forecast_segments(array $forecast, int $start, int $end, array $rules): array
{
  $points = [];
  foreach ($forecast as $row) {
    if (empty($row['utcTime'])) {
      continue;
    }
    $timestamp = astro_forecast_timestamp((string) $row['utcTime']);
    if ($timestamp === null) {
      continue;
    }
    if ($timestamp === false || $timestamp + 3600 <= $start || $timestamp >= $end) {
      continue;
    }
    $points[] = ['timestamp' => $timestamp, 'row' => $row];
  }
  usort($points, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

  $segments = [];
  $pointCount = count($points);
  for ($index = 0; $index < $pointCount; $index++) {
    $point = $points[$index];
    $segmentStart = max($start, $point['timestamp']);
    // Never bridge a missing forecast hour with an invented clear interval.
    $naturalEnd = min($point['timestamp'] + 3600, $index < $pointCount - 1 ? $points[$index + 1]['timestamp'] : PHP_INT_MAX);
    $segmentEnd = min($end, $naturalEnd);
    if ($segmentEnd <= $segmentStart) {
      continue;
    }

    $reading = astro_classify_forecast($point['row'], $rules);
    $state = $reading['state'];
    $tooltip = $reading['label'];
    $segments[] = [
      'start' => $segmentStart,
      'end' => $segmentEnd,
      'state' => $state,
      'label' => $tooltip,
      'class' => 'astro-sky-' . $state,
      'cloud' => $reading['cloud'],
    ];
  }
  return $segments;
}

function astro_best_window(array $skySegments, array $darknessSegments, array $moonSegments): array
{
  if (empty($skySegments) || empty($darknessSegments) || empty($moonSegments)) {
    return [
      'label' => 'No ideal imaging window forecast',
      'start' => null,
      'end' => null,
    ];
  }

  $boundaries = [];
  foreach ([$skySegments, $darknessSegments, $moonSegments] as $segments) {
    foreach ($segments as $segment) {
      $boundaries[] = $segment['start'];
      $boundaries[] = $segment['end'];
    }
  }
  $boundaries = array_values(array_unique($boundaries));
  sort($boundaries);

  $windows = [];
  $current = null;
  for ($index = 0, $count = count($boundaries) - 1; $index < $count; $index++) {
    $start = $boundaries[$index];
    $end = $boundaries[$index + 1];
    if ($end <= $start) {
      continue;
    }
    $midpoint = (int) (($start + $end) / 2);
    $isIdeal = astro_state_at($skySegments, $midpoint, 'unavailable') === 'good'
      && astro_state_at($darknessSegments, $midpoint, 'unavailable') === 'dark'
      && astro_state_at($moonSegments, $midpoint, 'unavailable') === 'down';

    if ($isIdeal) {
      if ($current !== null && $start === $current['end']) {
        $current['end'] = $end;
      } else {
        if ($current !== null) {
          $windows[] = $current;
        }
        $current = ['start' => $start, 'end' => $end];
      }
    } elseif ($current !== null) {
      $windows[] = $current;
      $current = null;
    }
  }
  if ($current !== null) {
    $windows[] = $current;
  }

  if (empty($windows)) {
    return [
      'label' => 'No ideal imaging window forecast',
      'start' => null,
      'end' => null,
    ];
  }

  usort($windows, fn($a, $b) => ($b['end'] - $b['start']) <=> ($a['end'] - $a['start']));
  $best = $windows[0];
  return [
    'label' => date('H:i', $best['start']) . '–' . date('H:i', $best['end']) . ' ideal overlap',
    'start' => $best['start'],
    'end' => $best['end'],
  ];
}

function astro_format_duration(?int $start, ?int $end): string
{
  if ($start === null || $end === null || $end <= $start) {
    return '0h';
  }
  $minutes = (int) round(($end - $start) / 60);
  $hours = intdiv($minutes, 60);
  $remainingMinutes = $minutes % 60;
  if ($remainingMinutes === 0) {
    return $hours . 'h';
  }
  if ($hours === 0) {
    return $remainingMinutes . 'm';
  }
  return $hours . 'h ' . $remainingMinutes . 'm';
}

function astro_build_night_plan(string $date, array $forecast, array $rules): array
{
  $start = astro_sun_event($date, 'sunset');
  $end = astro_sun_event(date('Y-m-d', strtotime($date . ' +1 day')), 'sunrise');
  if (!$start || !$end || $end <= $start) {
    return ['available' => false];
  }
  $moon = astro_moon_illumination((int) (($start + $end) / 2));
  $darkness = astro_darkness_segments($date, $start, $end);
  $moonSegments = astro_sample_moon_segments($start, $end, $moon['illumination']);
  $sky = astro_forecast_segments($forecast, $start, $end, $rules);
  $bestWindow = astro_best_window($sky, $darkness, $moonSegments);

  $weightedCloud = 0;
  $coveredSeconds = 0;
  foreach ($sky as $segment) {
    if ($segment['cloud'] === null) continue;
    $duration = $segment['end'] - $segment['start'];
    $weightedCloud += $segment['cloud'] * $duration;
    $coveredSeconds += $duration;
  }
  $averageCloud = $coveredSeconds > 0 ? round($weightedCloud / $coveredSeconds) : null;
  return [
    'available' => true,
    'date' => $date,
    'date_label' => date('D j M', $start) . ' → ' . date('D j M', $end),
    'coverage_seconds' => array_sum(array_map(fn($segment) => $segment['state'] === 'unknown' ? 0 : $segment['end'] - $segment['start'], $sky)),
    'start' => $start,
    'end' => $end,
    'sky' => $sky,
    'darkness' => $darkness,
    'moon' => $moonSegments,
    'average_cloud' => $averageCloud,
    'moon_phase' => $moon['phase'],
    'moon_illumination' => $moon['illumination'],
    'best_window' => $bestWindow['label'],
    'best_window_start' => $bestWindow['start'],
    'best_window_end' => $bestWindow['end'],
    'best_window_duration' => astro_format_duration($bestWindow['start'], $bestWindow['end']),
  ];
}
