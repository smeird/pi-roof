export async function loadConfig() {
  const res = await fetch('/get_config.php');
  if (!res.ok) {
    throw new Error('Failed to load config');
  }
  const data = await res.json();
  return {
    brokerUrl: data.brokerUrl,
    port: parseInt(data.port, 10),
    username: data.username,
    password: data.password,
    dashboardTopics: data.dashboardTopics,
    sensors: data.sensors || [],
    switches: data.switches || [],
    roof: data.roof || { open: { path: '', limit: '' }, close: { path: '', limit: '' } },
    skyCamTopic: data.skyCamTopic || '',
    influxHost: data.influxHost,
    influxOrg: data.influxOrg,
    influxBucket: data.influxBucket
  };
}
