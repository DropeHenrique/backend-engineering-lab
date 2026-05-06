'use strict';

const { v4: uuidv4 } = require('uuid');
const { TokenBucketStrategy, SlidingWindowStrategy } = require('../strategies');
const { pingOrNull } = require('../redisClient');

/** @typedef {'free'|'pro'|'enterprise'} Plan */

/**
 * @typedef {object} Options
 * @property {'token_bucket'|'sliding_window'} algorithm
 * @property {number} limit
 * @property {string} window
 * @property {'ip'|'api_key'|'user_id'} [identify]
 * @property {string} [routeKey]
 */

/**
 * Multi-tenant: header X-Plan (default free), multipliers via PLAN_MULTIPLIER_*
 *
 * @param {{ redis:any, shas:any, bucketPrefix?:string, planMultiplier?:(plan:string)=>number }} deps
 * @param {Options} options
 */
function rateLimiterMiddleware(deps, options) {
  const { redis, shas } = deps;
  const bucketPrefix = deps.bucketPrefix ?? 'ratelimit:';

  /** @type {Record<'token_bucket', TokenBucketStrategy> & Record<'sliding_window', SlidingWindowStrategy>} */
  const strategies = {
    token_bucket: new TokenBucketStrategy(redis, shas),
    sliding_window: new SlidingWindowStrategy(redis, shas),
  };

  return async function rateLimiter(req, res, next) {
    const identify = options.identify || process.env.RATE_IDENTIFY_MODE || 'ip';
    /** @type {Plan} */
    const planRaw = String(req.headers['x-plan'] || process.env.RATE_LIMIT_DEFAULT_PLAN || 'free')
      .toLowerCase()
      .trim();

    /** @type {Plan} */
    const plan =
      /** @type {Plan} */ planRaw === 'pro'
        ? 'pro'
        : planRaw === 'enterprise'
          ? 'enterprise'
          : 'free';

    let identity = '';
    if (identify === 'api_key')
      identity = String(req.headers['x-api-key'] || req.headers['authorization'] || 'anon');
    else if (identify === 'user_id')
      identity = String(req.headers['x-user-id'] || req.headers['x-user'] || '');
    identity = identity || req.ip || 'unknown';

    const mult = deps.planMultiplier?.(plan) ?? 1;
    const effectiveLimit = Math.max(1, Math.ceil(options.limit * mult));

    const routeKey = options.routeKey || `${req.method}:${req.path}`;
    const algoKey = `${bucketPrefix}${plan}:${options.algorithm}:${routeKey}:${identity}`;

    const alive = redis.status === 'ready' ? true : await pingOrNull(redis);
    if (!alive) {
      const mode = (process.env.REDIS_DOWN_MODE || 'allow').toLowerCase();
      console.warn(JSON.stringify({ event: 'rate_limit_degraded', mode, route: routeKey, plan }));
      if (mode === 'deny') {
        return res.status(503).json({ error: 'Rate limit unavailable', redis: 'down' });
      }
      return next();
    }

    /** @type {import('../strategies').RateResult | null} */
    let result = null;
    try {
      const strat = strategies[options.algorithm];
      if (!strat) {
        return next();
      }
      if (options.algorithm === 'token_bucket') {
        result = await strat.evaluate({
          bucketKey: algoKey,
          capacity: effectiveLimit,
          window: options.window,
        });
      } else {
        result = await strat.evaluate({
          bucketKey: algoKey,
          limit: effectiveLimit,
          window: options.window,
          member: `${Date.now()}${uuidv4()}`,
        });
      }
    } catch {
      const mode = (process.env.REDIS_DOWN_MODE || 'allow').toLowerCase();
      console.warn(JSON.stringify({ event: 'rate_limit_error', route: routeKey }));
      if (mode === 'deny') return res.status(503).json({ error: 'Rate limit unavailable' });
      return next();
    }

    if (!result) return next();

    res.setHeader('X-RateLimit-Limit', String(result.limit));
    res.setHeader('X-RateLimit-Remaining', String(result.remaining));
    res.setHeader('X-RateLimit-Reset', String(result.reset));

    if (!result.allowed) {
      const retrySec = Math.max(1, result.reset - Math.floor(Date.now() / 1000));
      res.setHeader('Retry-After', String(retrySec));
      console.warn(
        JSON.stringify({
          event: 'rate_limit_block',
          route: routeKey,
          algorithm: options.algorithm,
          plan,
          identity,
          limit: result.limit,
        }),
      );

      const windowCanonical = canonicalWindow(options.window);
      return res.status(429).json({
        error: 'Too Many Requests',
        retry_after: retrySec,
        limit: result.limit,
        window: windowCanonical,
      });
    }

    return next();
  };
}

/** @param {string} window */
function canonicalWindow(window) {
  try {
    const { parseWindowToSeconds } = require('../parseWindow');
    const s = Math.round(parseWindowToSeconds(window));

    return `${s}s`;
  } catch {
    return String(window);
  }
}

module.exports = { rateLimiterMiddleware };
