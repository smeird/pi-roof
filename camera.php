<?php
// Serve only the local bridge output. Never accept a URL or expose camera credentials.
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$name = $_GET['file'] ?? 'stream.m3u8';
if (!is_string($name) || !preg_match('/^(stream\.m3u8|segment[0-9]+\.ts)$/D', $name)) {
    http_response_code(400); exit;
}
$directory = '/var/tmp/roof-camera';
$manifest = $directory . '/stream.m3u8';
// TC70 keyframes can arrive in bursts; allow a short network/keyframe pause
// while still rejecting genuinely abandoned output.
if (!is_file($manifest) || time() - filemtime($manifest) > 90) {
    http_response_code(503); header('Retry-After: 10'); exit;
}
$path = $directory . '/' . $name;
if (!is_file($path) || is_link($path)) { http_response_code(404); exit; }
if ($name === 'stream.m3u8') {
    header('Content-Type: application/vnd.apple.mpegurl');
    $contents = file_get_contents($path);
    echo preg_replace('/^(segment[0-9]+\.ts)$/m', 'camera.php?file=$1', $contents);
} else {
    header('Content-Type: video/mp2t');
    readfile($path);
}
