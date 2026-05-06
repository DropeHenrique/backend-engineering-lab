'use strict';

const Redis = require('ioredis');
const { loadScripts } = require('../src/redisScripts');

const REDIS_URL = process.env.REDIS_URL || '';

(REDIS_URL ? describe : describe.skip)('Lua scripts contra Redis real', () => {
  /** @type {import('ioredis').default} */
  let redis;

  beforeAll(async () => {
    redis = new Redis(REDIS_URL);
    await redis.ping();
  });

  afterAll(async () => {
    await redis.quit();
  });

  beforeEach(async () => {
    const keys = await redis.keys('__test_*');
    if (keys.length) await redis.del(...keys);
  });

  test('token bucket permite primeira requisicao e reduz tokens', async () => {
    const { tokenSha } = await loadScripts(redis);
    const key = '__test_tb_1';

    /** @type {number[]} */
    const r1 = await redis.evalsha(
      tokenSha,
      1,
      key,
      '5',
      String(5 / 10),
      String(Date.now() / 1000),
      '1',
    );

    /** @type {number[]} */
    const r2 = await redis.evalsha(
      tokenSha,
      1,
      key,
      '5',
      String(5 / 10),
      String(Date.now() / 1000),
      '1',
    );

    expect(r1[0]).toBe(1);
    expect(r2[0]).toBe(1);
    expect(Number(r2[2])).toBeLessThan(Number(r1[2]));
  });

  test('sliding window bloqueia apos limite', async () => {
    const { slidingSha } = await loadScripts(redis);
    const key = '__test_sw_1';
    const lim = 3;
    const winMs = 10000;

    const results = [];
    for (let i = 0; i <= lim + 1; i++) {
      /** @type {number[]} */
      const r = await redis.evalsha(
        slidingSha,
        1,
        key,
        String(Date.now() + i),
        String(winMs),
        String(lim),
        `m${Date.now()}${i}${Math.random()}`,
      );

      results.push(r[0]);
    }

    const allowed = results.filter((x) => x === 1).length;

    expect(allowed).toBeLessThanOrEqual(lim);
  });
});
