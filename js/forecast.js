const time = timestamp => new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/London', hour: '2-digit', minute: '2-digit' }).format(new Date(timestamp * 1000));
const make = (tag, className, text) => {
  const node = document.createElement(tag);
  node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
};

export function renderForecast(data) {
  const plan = data.plan;
  const tracks = document.getElementById('forecastTracks');
  tracks.replaceChildren();
  tracks.hidden = !plan?.available;
  if (!plan?.available) throw new Error('Night timeline unavailable.');
  document.getElementById('forecastDate').textContent = plan.date_label;
  const covered = plan.coverage_seconds > 0;
  document.getElementById('forecastWindow').textContent = !covered ? 'Sky forecast unavailable · darkness and moon shown below' : plan.best_window_start != null ? `${plan.best_window} · ${plan.best_window_duration} best window` : 'No ideal imaging window forecast';
  document.getElementById('forecastMoon').textContent = `${plan.moon_phase} · ${plan.moon_illumination}% illuminated`;
  const updated = data.fetched_at ? `Retrieved ${time(data.fetched_at)}` : 'Awaiting forecast';
  document.getElementById('forecastUpdated').textContent = `${data.source} · ${updated}${data.stale ? ' · STALE / unavailable' : ''}`;
  document.getElementById('forecastUpdated').classList.toggle('forecast-stale', data.stale);
  document.getElementById('forecastSegment').textContent = 'Best window = green sky + full darkness + moon below. Select a segment for details.';
  const duration = plan.end - plan.start;
  const position = timestamp => 100 * (timestamp - plan.start) / duration;
  for (const [key, label] of [['sky', 'Sky / seeing'], ['darkness', 'Darkness'], ['moon', 'Moon']]) {
    const row = make('div', 'forecast-row');
    const bar = make('div', 'forecast-bar');
    row.append(make('span', 'forecast-track-label', label), bar);
    for (const segment of plan[key]) {
      const button = make('button', `forecast-segment ${key}-${segment.state}`);
      button.type = 'button';
      button.style.left = `${position(segment.start)}%`;
      button.style.width = `${100 * (segment.end - segment.start) / duration}%`;
      const state = {good:'Green',fair:'Amber',poor:'Red',unknown:'Unknown'}[segment.state] || '';
      const description = `${label}: ${time(segment.start)}–${time(segment.end)} · ${state} ${segment.label}`;
      button.title = description;
      button.setAttribute('aria-label', description);
      const show = () => { document.getElementById('forecastSegment').textContent = description; };
      button.addEventListener('click', show);
      button.addEventListener('focus', show);
      bar.append(button);
    }
    for (let tick = Math.ceil(plan.start / 3600) * 3600; tick < plan.end; tick += 3600) {
      const guide = make('span', 'forecast-hour-guide');
      guide.style.left = `${position(tick)}%`;
      bar.append(guide);
    }
    if (plan.best_window_start != null) {
      const best = make('span', 'forecast-best');
      best.style.left = `${position(plan.best_window_start)}%`;
      best.style.width = `${100 * (plan.best_window_end - plan.best_window_start) / duration}%`;
      best.setAttribute('aria-hidden', 'true');
      bar.append(best);
    }
    tracks.append(row);
  }
  const axis = make('div', 'forecast-row');
  const ticks = make('div', 'forecast-times');
  axis.append(make('span', 'forecast-track-label', 'UK time'), ticks);
  const startLabel = make('span', 'forecast-time-start', time(plan.start));
  const endLabel = make('span', 'forecast-time-end', time(plan.end));
  ticks.append(startLabel, endLabel);
  for (let tick = Math.ceil(plan.start / 3600) * 3600; tick < plan.end; tick += 3600) {
    if (position(tick) < 4 || position(tick) > 96) continue;
    const label = make('span', 'forecast-time-tick', time(tick));
    label.style.left = `${position(tick)}%`;
    ticks.append(label);
  }
  tracks.append(axis);
}

export function setupForecast() {
  let pending = false;
  async function refresh() {
    if (pending) return;
    pending = true;
    try {
      const response = await fetch('/forecast.php', {signal: AbortSignal.timeout(15000)});
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Forecast unavailable.');
      renderForecast(data);
    } catch (error) {
      // Never retain an unlabelled previous night's plan after a failed rollover.
      document.getElementById('forecastTracks').hidden = true;
      document.getElementById('forecastMoon').textContent = '';
      document.getElementById('forecastWindow').textContent = 'Forecast unavailable · retrying shortly';
      document.getElementById('forecastUpdated').textContent = 'Offline';
      document.getElementById('forecastSegment').textContent = 'Live sensor conditions remain separate from this forecast.';
    } finally { pending = false; }
  }
  refresh();
  setInterval(refresh, 5 * 60 * 1000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
}
