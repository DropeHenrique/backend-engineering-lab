'use strict';

const express = require('express');
const morgan = require('morgan');
const { createProxyMiddleware } = require('http-proxy-middleware');
const { createRedis } = require('./redisClient');
const { loadScripts } = require('./redisScripts');
const { rateLimiterMiddleware } = require('./middleware/rateLimiter');

const PORT = Number(process.env.PORT || 3000);
const REDIS_URL = process.env.REDIS_URL || 'redis://127.0.0.1:6379';
const WEBHOOK_UPSTREAM = process.env.WEBHOOK_UPSTREAM || 'http://127.0.0.1:8082';

/**
 * Multiplicadores de limite por plano (cabecalho X-Plan).
 */
function planMultiplier(plan) {
  if (plan === 'pro') return Number(process.env.PLAN_MULTIPLIER_PRO || 5);
  if (plan === 'enterprise') return Number(process.env.PLAN_MULTIPLIER_ENTERPRISE || 25);

  return Number(process.env.PLAN_MULTIPLIER_FREE || 1);
}

async function buildApp() {
  const redis = createRedis({
    url: REDIS_URL,
    degradedMode: (process.env.REDIS_DOWN_MODE || 'allow').toLowerCase(),
  });

  await redis.connect().catch(() => null);
  let shas;
  try {
    shas = await loadScripts(redis);
  } catch {
    shas = { tokenSha: '', slidingSha: '' };
  }

  const deps = {
    redis,
    shas,
    bucketPrefix: 'ratelimit:lab:',
    planMultiplier,
  };

  const app = express();

  app.set('trust proxy', 1);

  app.use(express.json());

  app.use(
    morgan((tokens, req, res) => {
      const line = `${tokens.method(req, res)} ${tokens.url(req, res)} ${tokens.status(req, res)} ${tokens.res(req, res, 'content-length')}B`;
      if (tokens.status(req, res) === 429) {
        console.warn(JSON.stringify({ event: 'http_429_line', message: line }));
      }
      return line;
    }),
  );

  app.get('/health', (_req, res) =>
    res.json({
      status: 'ok',
      service: 'rate-limiter',
    }),
  );

  /** Login sensivel — sliding window apertado */
  app.post(
    '/api/login',
    rateLimiterMiddleware(deps, {
      algorithm: 'sliding_window',
      limit: 5,
      window: '15m',
      routeKey: 'POST:/api/login',
      identify: 'ip',
    }),
    (_req, res) => res.json({ ok: true }),
  );

  /** Dados genericos — token bucket mais permissivo */
  app.get(
    '/api/data',
    rateLimiterMiddleware(deps, {
      algorithm: 'token_bucket',
      limit: 100,
      window: '1m',
      routeKey: 'GET:/api/data',
      identify: process.env.RATE_IDENTIFY_MODE || 'ip',
    }),
    (_req, res) =>
      res.json({
        samples: [],
      }),
  );

  app.use(
    '/webhook',
    rateLimiterMiddleware(deps, {
      algorithm: 'token_bucket',
      limit: Number(process.env.RATE_WEBHOOK_LIMIT || 40),
      window: '1m',
      routeKey: 'ingress:webhook',
      identify: 'ip',
    }),
    createProxyMiddleware({
      target: WEBHOOK_UPSTREAM,
      changeOrigin: true,
    }),
  );

  /** Default 404 */
  app.use((_req, res) => res.status(404).json({ error: 'Not found' }));

  return { app, redis };
}

async function main() {
  const { app, redis } = await buildApp();

  const shutdown = () => {
    redis.quit().catch(() => null);
  };
  process.on('SIGTERM', shutdown);
  process.on('SIGINT', shutdown);

  app.listen(PORT, '0.0.0.0', () =>
    console.warn(JSON.stringify({ event: 'server_listen', port: PORT, redis_url: REDIS_URL })),
  );
}

module.exports = { buildApp, main, planMultiplier };

if (require.main === module) {
  main().catch((e) => {
    console.error(e);
    process.exit(1);
  });
}
