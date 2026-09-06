import { RoofMotion } from './roofMotion.mjs';

export function createRoofDiagram(root, options) {
  const motion = new RoofMotion(options);
  const roof = root.querySelector('[data-roof]');
  const status = root.querySelector('[data-roof-status]');
  const description = root.querySelector('desc');
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  let frame = 0;
  let lastPaint = 0;
  const needsFrame = state => state.position !== null && (state.direction > 0 ? state.position < 1 : state.direction < 0 && state.position > 0);

  function paint(state) {
    roof.setAttribute('transform', `translate(${(state.position ?? .5) * 172} 0)`);
    roof.style.opacity = state.position === null ? '.35' : '1';
    status.textContent = state.label;
    status.className = `ops-status-chip tone-${state.tone}`;
    description.textContent = `Roll-off roof: ${state.label}. Intermediate position is estimated, not measured.`;
    for (const [key, label] of [['closeLimit', 'Closed'], ['openLimit', 'Open']]) {
      const light = root.querySelector(`[data-limit="${key}"]`);
      const value = state.available ? state[key] : null;
      light.dataset.active = value === '1' ? 'true' : 'false';
      light.querySelector('span').textContent = `${label} limit: ${value === '1' ? 'hit' : value === '0' ? 'clear' : 'unknown'}`;
    }
  }

  function animate(now) {
    frame = 0;
    const state = motion.tick(now);
    // Reduced motion retains a useful position readout without smooth movement.
    if (!reducedMotion.matches || now - lastPaint >= 1000 || !needsFrame(state)) { paint(state); lastPaint = now; }
    if (needsFrame(state)) frame = requestAnimationFrame(animate);
  }

  paint(motion.snapshot());
  return {
    update(signals) {
      const state = motion.update(signals, performance.now());
      paint(state);
      if (frame) cancelAnimationFrame(frame);
      frame = needsFrame(state) ? requestAnimationFrame(animate) : 0;
    }
  };
}
