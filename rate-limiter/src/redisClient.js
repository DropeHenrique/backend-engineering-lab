'use strict';

const Redis = require('ioredis');

function createRedis({ url, degradedMode }) {
  const client = new Redis(url, {
    lazyConnect: true,
    maxRetriesPerRequest: 2,
    retryStrategy(times) {
      if (times > 8) return null;
      return Math.min(times * 100, 2000);
    },
  });
  /** @type {'allow'|'deny'} */
  client._degraded = degradedMode;
  client.on('error', () => {
    /** Suprimido para evitar "Unhandled error"; middleware trata degraded. */
  });
  return client;
}

async function pingOrNull(client) {
  try {
    if (client.status === 'wait') await client.connect();
    const pong = await client.ping();
    return pong === 'PONG';
  } catch {
    return false;
  }
}

module.exports = { createRedis, pingOrNull };
