// Observing-condition indications only; never publishes commands or bypasses interlocks.
export function sensorNumber(value) {
  if (typeof value !== 'number' && typeof value !== 'string') return NaN;
  if (typeof value === 'string' && !value.trim()) return NaN;
  const number = Number(value);
  return Number.isFinite(number) ? number : NaN;
}

export function classifySensor(value, sensor) {
  const reading = sensorNumber(value);
  const green = sensorNumber(sensor.green);
  const amber = sensorNumber(sensor.amber);
  const hasAmber = sensor.amber != null && String(sensor.amber).trim() !== '';
  const above = sensor.greenDirection === 'above';
  if (!Number.isFinite(reading) || !Number.isFinite(green)) return 'unknown';
  if (hasAmber && (!Number.isFinite(amber) || (above ? amber >= green : amber <= green))) return 'unknown';
  if (above ? reading >= green : reading <= green) return 'green';
  if (hasAmber && (above ? reading >= amber : reading <= amber)) return 'amber';
  return 'red';
}

export function observingStatus(sensors, connected) {
  const counts = { green: 0, amber: 0, red: 0, unknown: 0 };
  sensors.forEach(sensor => { counts[sensor.status]++; });
  const status = counts.red ? 'red' : counts.amber ? 'amber' : !connected || !sensors.length || counts.unknown ? 'unknown' : 'green';
  const titles = { green: 'All green — safe to observe', amber: 'Amber — observe with caution', red: 'Red — unsafe to observe', unknown: 'Conditions unknown — safety unconfirmed' };
  const issues = sensors.filter(sensor => sensor.status !== 'green').map(sensor => `${sensor.name}: ${sensor.status}`).join(' · ');
  const detail = !connected ? 'Sensor connection unavailable. Waiting for fresh readings.' : !sensors.length ? 'No sensors configured.' : status === 'green' ? `${counts.green}/${sensors.length} sensors green · Based on configured sensor limits` : issues;
  return { status, title: titles[status], detail, counts };
}
