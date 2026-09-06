export function setupCamera() {
  const video = document.getElementById('tapoVideo');
  const status = document.getElementById('tapoStatus');
  const message = document.getElementById('tapoMessage');
  let player, retry, stopped = false, lastTime = -1, progressAt = Date.now();
  function state(label, text, good = false) {
    status.textContent = label;
    status.className = `ops-status-chip tone-${good ? 'good' : 'warn'}`;
    message.textContent = text;
    message.hidden = good;
  }
  function unavailable() {
    if (stopped) return;
    state('Offline', 'Camera unavailable · retrying shortly');
    player?.destroy(); player = null;
    video.removeAttribute('src'); video.load();
    clearTimeout(retry);
    retry = setTimeout(connect, 10000);
  }
  function play() {
    video.play().catch(() => {
      state('Ready', 'Press play to view the camera');
      message.hidden = true;
    });
  }
  function connect() {
    if (stopped || document.hidden) return;
    clearTimeout(retry);
    state('Connecting', 'Connecting to camera…');
    progressAt = Date.now();
    const source = '/camera.php?file=stream.m3u8';
    if (window.Hls?.isSupported()) {
      player = new window.Hls({ maxBufferLength: 8, liveSyncDurationCount: 2 });
      player.loadSource(source);
      player.attachMedia(video);
      player.on(window.Hls.Events.MANIFEST_PARSED, play);
      player.on(window.Hls.Events.ERROR, (_, data) => { if (data.fatal) unavailable(); });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
      video.src = source; play();
    } else state('Unsupported', 'This browser cannot play the camera stream');
  }
  video.addEventListener('error', () => { if (video.getAttribute('src')) unavailable(); });
  video.addEventListener('timeupdate', () => {
    if (video.currentTime !== lastTime) {
      lastTime = video.currentTime; progressAt = Date.now();
      state('Live', '', true);
    }
  });
  const watchdog = setInterval(() => {
    if (!document.hidden && !video.paused && Date.now() - progressAt > 15000) unavailable();
  }, 5000);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearTimeout(retry); player?.destroy(); player = null;
      video.removeAttribute('src'); video.load();
    } else connect();
  });
  window.addEventListener('pagehide', () => {
    stopped = true; clearTimeout(retry); clearInterval(watchdog); player?.destroy();
  });
  connect();
}
