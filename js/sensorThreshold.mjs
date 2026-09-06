// Presentation only: never authorizes controls or changes configured threshold logic.
export function describeThreshold(value, threshold, direction = 'below', unit = '') {
  const limit = Number.parseFloat(threshold);
  const numeric = Number.parseFloat(value);
  const suffix = unit ? ` ${unit}` : '';
  const format = number => String(Math.round(number * 10) / 10);
  if (!Number.isFinite(limit)) return { available: false, rule: 'No green threshold', distance: 'Threshold not configured' };
  const above = direction === 'above';
  const rule = `Green ${above ? '≥' : '≤'} ${format(limit)}${suffix}`;
  if (!Number.isFinite(numeric)) return { available: false, rule, distance: 'Waiting for a numeric reading' };
  const margin = above ? numeric - limit : limit - numeric;
  const deltaUnit = unit === '%' ? ' pp' : suffix;
  const gap = Math.abs(margin);
  const amount = gap > 0 && gap < .1 ? '<0.1' : format(gap);
  const distance = margin === 0 ? 'At green boundary' : margin > 0 ? `${amount}${deltaUnit} inside green` : `${above ? '↑' : '↓'} ${amount}${deltaUnit} to green`;
  // A fixed scale per sensor keeps the marker comparable across incoming readings.
  // The threshold is centered; ends represent ±max(|threshold|, 1) in native units.
  const span = Math.max(Math.abs(limit), 1);
  return { available: true, rule, distance, margin, span,
    position: 50 + Math.max(-1, Math.min(1, margin / span)) * 50,
    green: margin >= 0,
    description: `${rule}. ${distance}. Margin scale: minus ${format(span)} to plus ${format(span)}${suffix}; green starts at the center. Values beyond the scale are pinned to its ends.`
  };
}
