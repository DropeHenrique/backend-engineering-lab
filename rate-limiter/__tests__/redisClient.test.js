'use strict';

const { pingOrNull, createRedis } = require('../src/redisClient');

describe('pingOrNull', () => {
  it('retorna true quando ping responde PONG', async () => {
    const client = {
      status: 'ready',
      connect: jest.fn(),
      ping: jest.fn().mockResolvedValue('PONG'),
    };
    await expect(pingOrNull(client)).resolves.toBe(true);
    expect(client.connect).not.toHaveBeenCalled();
  });

  it('conecta quando status é wait e ping funciona', async () => {
    const client = {
      status: 'wait',
      connect: jest.fn().mockResolvedValue(undefined),
      ping: jest.fn().mockResolvedValue('PONG'),
    };
    await expect(pingOrNull(client)).resolves.toBe(true);
    expect(client.connect).toHaveBeenCalled();
  });

  it('retorna false em falha de rede', async () => {
    const client = {
      status: 'wait',
      connect: jest.fn().mockRejectedValue(new Error('refused')),
    };
    await expect(pingOrNull(client)).resolves.toBe(false);
  });

  it('retorna false quando resposta não é PONG', async () => {
    const client = {
      status: 'ready',
      ping: jest.fn().mockResolvedValue('HELLO'),
    };
    await expect(pingOrNull(client)).resolves.toBe(false);
  });
});

describe('createRedis', () => {
  it('retryStrategy desiste após muitas tentativas', () => {
    const client = createRedis({ url: 'redis://127.0.0.1:19999', degradedMode: 'allow' });
    const retry = client.options.retryStrategy;

    expect(retry(1)).toBeGreaterThan(0);
    expect(retry(9)).toBeNull();
  });
});
