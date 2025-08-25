(function(global){
  function createClient({ brokerUrl, options = {} }) {
    const subscriptions = [];
    const handlers = { message: [], connect: [], error: [], status: [] };
    let client;
    let reconnectDelay = 1000;
    const maxDelay = options.maxReconnectDelay || 30000;
    let ended = false;

    const connect = () => {
      handlers.status.forEach(h => h('connecting'));
      client = mqtt.connect(brokerUrl, { ...options, reconnectPeriod: 0 });

      client.on('connect', () => {
        reconnectDelay = 1000;
        subscriptions.forEach(topic => client.subscribe(topic));
        handlers.connect.forEach(h => h());
        handlers.status.forEach(h => h('connected'));
      });

      client.on('message', (topic, message) => {
        handlers.message.forEach(h => h(topic, message));
      });

      client.on('error', (err) => {
        console.error('MQTT Error:', err);
        handlers.error.forEach(h => h(err));
        handlers.status.forEach(h => h('error', err));
      });

      client.on('close', handleDisconnect);
    };

    const handleDisconnect = () => {
      handlers.status.forEach(h => h('disconnected'));
      if (ended) return;
      const delay = reconnectDelay;
      reconnectDelay = Math.min(maxDelay, reconnectDelay * 2);
      handlers.status.forEach(h => h('reconnecting', delay));
      setTimeout(connect, delay);
    };

    connect();

    return {
      publish: (...args) => client && client.publish(...args),
      subscribe: (topic) => {
        if (!subscriptions.includes(topic)) subscriptions.push(topic);
        if (client) client.subscribe(topic);
      },
      on: (event, handler) => {
        if (handlers[event]) handlers[event].push(handler);
      },
      end: () => {
        ended = true;
        if (client) client.end();
      }
    };
  }

  global.createClient = createClient;
})(this);
