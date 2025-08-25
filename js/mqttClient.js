(function(global){
  function createClient({ brokerUrl, options = {} }) {
    const subscriptions = [];
    const handlers = { message: [], connect: [], error: [] };
    const client = mqtt.connect(brokerUrl, options);

    const resubscribe = () => {
      subscriptions.forEach(topic => client.subscribe(topic));
    };

    client.on('connect', () => {
      resubscribe();
      handlers.connect.forEach(h => h());
    });

    client.on('message', (topic, message) => {
      handlers.message.forEach(h => h(topic, message));
    });

    client.on('error', (err) => {
      handlers.error.forEach(h => h(err));
    });

    return {
      publish: (...args) => client.publish(...args),
      subscribe: (topic) => {
        if (!subscriptions.includes(topic)) subscriptions.push(topic);
        client.subscribe(topic);
      },
      on: (event, handler) => {
        if (handlers[event]) handlers[event].push(handler);
      },
      end: () => client.end()
    };
  }

  global.createClient = createClient;
})(this);
