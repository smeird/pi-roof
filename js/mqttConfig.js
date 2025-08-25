function getEnv(key, fallback = '') {
  if (typeof window !== 'undefined' && window.__ENV && window.__ENV[key] !== undefined) {
    return window.__ENV[key];
  }
  if (typeof process !== 'undefined' && process.env && process.env[key] !== undefined) {
    return process.env[key];
  }
  return fallback;
}

export const brokerUrl = getEnv('MQTT_BROKER_URL', 'ws://homeassistant.smeird.com');
export const port = parseInt(getEnv('MQTT_PORT', '1884'), 10);
export const username = getEnv('MQTT_USERNAME', '');
export const password = getEnv('MQTT_PASSWORD', '');
export const dashboardTopics = getEnv('MQTT_DASHBOARD_TOPICS', '').split(',').filter(Boolean);

