'use strict';

const request = require('supertest');

describe('buildApp', () => {
  beforeEach(() => {
    jest.resetModules();
    process.env.PORT = '0';
    process.env.REDIS_URL = 'redis://127.0.0.1:16379';
    process.env.REDIS_DOWN_MODE = 'allow';
    process.env.WEBHOOK_UPSTREAM = 'http://127.0.0.1:19999';
  });

  afterEach(() => {
    delete process.env.PORT;
    delete process.env.REDIS_URL;
    delete process.env.REDIS_DOWN_MODE;
    delete process.env.WEBHOOK_UPSTREAM;
  });

  it('GET /health retorna serviço ativo', async () => {
    const { buildApp } = require('../src/server');
    const { app, redis } = await buildApp();

    try {
      const res = await request(app).get('/health');

      expect(res.status).toBe(200);
      expect(res.body).toMatchObject({ status: 'ok', service: 'rate-limiter' });
    } finally {
      await redis.quit();
    }
  });

  it('roteamento desconhecido retorna 404 JSON', async () => {
    const { buildApp } = require('../src/server');
    const { app, redis } = await buildApp();

    try {
      const res = await request(app).get('/rota/inexistente');

      expect(res.status).toBe(404);
      expect(res.body.error).toBe('Not found');
    } finally {
      await redis.quit();
    }
  });

  it('GET /api/data com Redis degradado segue até o handler', async () => {
    const { buildApp } = require('../src/server');
    const { app, redis } = await buildApp();

    try {
      const res = await request(app).get('/api/data');
      expect(res.status).toBe(200);
      expect(Array.isArray(res.body.samples)).toBe(true);
    } finally {
      await redis.quit();
    }
  });

  it('POST /api/login permite quando Redis não está disponível (modo degradado)', async () => {
    const { buildApp } = require('../src/server');
    const { app, redis } = await buildApp();

    try {
      const res = await request(app).post('/api/login').send({});
      expect(res.status).toBe(200);
      expect(res.body.ok).toBe(true);
    } finally {
      await redis.quit();
    }
  });
});
