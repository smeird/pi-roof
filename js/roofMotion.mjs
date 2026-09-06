// Position is an estimate between 0 (closed) and 1 (open), never a safety input.
export class RoofMotion {
  constructor({ openSeconds = 30, closeSeconds = 30 } = {}) {
    const duration = value => Number.isFinite(Number(value)) && Number(value) >= 1 && Number(value) <= 900 ? Number(value) : 30;
    this.openSeconds = duration(openSeconds);
    this.closeSeconds = duration(closeSeconds);
    this.position = null;
    this.direction = 0;
    this.time = null;
    this.label = 'Position unknown';
    this.tone = 'warn';
    this.signals = {};
  }

  tick(now) {
    if (this.time !== null && this.direction && this.position !== null) {
      const seconds = this.direction > 0 ? this.openSeconds : this.closeSeconds;
      this.position = Math.max(0, Math.min(1, this.position + this.direction * Math.max(0, now - this.time) / (seconds * 1000)));
    }
    this.time = now;
    return this.snapshot();
  }

  update(signals, now) {
    this.tick(now);
    this.signals = signals;
    const { available, openLimit, closeLimit, openMotor, closeMotor, moving, fault } = signals;
    const open = openLimit === '1', closed = closeLimit === '1';
    const previousDirection = this.direction;
    this.direction = 0;
    this.tone = 'warn';
    if (!available) { this.label = 'Telemetry unavailable · last estimate'; return this.snapshot(); }
    if (open && closed) { this.label = 'Limit conflict · check controller'; this.tone = 'bad'; return this.snapshot(); }
    if (openMotor === '1' && closeMotor === '1') {
      this.label = 'Motor conflict · check controller'; this.tone = 'bad'; return this.snapshot();
    }
    const direction = moving === '1' ? (openMotor === '1' ? 1 : closeMotor === '1' ? -1 : 0) : 0;
    // A departing limit can remain active briefly; do not continually reset travel.
    if (open && (direction !== -1 || previousDirection !== -1)) this.position = 1;
    if (closed && (direction !== 1 || previousDirection !== 1)) this.position = 0;
    if (fault === '1') { this.label = 'Fault · motion estimate paused'; this.tone = 'bad'; }
    else if (open && direction !== -1) { this.label = 'Open · limit confirmed'; this.tone = 'good'; }
    else if (closed && direction !== 1) { this.label = 'Closed · limit confirmed'; this.tone = 'good'; }
    else if (direction) {
      this.direction = direction;
      this.label = direction > 0 ? 'Opening' : 'Closing';
    } else if (moving === '1') this.label = 'Moving · direction unknown';
    else this.label = this.position === null ? 'Position unknown' : 'Stopped · estimated position';
    return this.snapshot();
  }

  snapshot() {
    let label = this.label;
    if (this.direction) {
      if (this.position === null) label += ' · position unknown';
      else if ((this.direction === 1 && this.position === 1) || (this.direction === -1 && this.position === 0)) label += ' · awaiting limit';
      else label += ` · ~${Math.round(this.position * 100)}% open`;
    }
    return { position: this.position, direction: this.direction, label, tone: this.tone, ...this.signals };
  }
}
