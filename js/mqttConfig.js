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
    dashboardTopics: data.dashboardTopics
  };
}
