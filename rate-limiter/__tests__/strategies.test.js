'use strict';

const { TokenBucketStrategy, SlidingWindowStrategy } = require('../src/strategies');

describe('TokenBucketStrategy', () => {
  it('interpreta resultado permitido da Lua', async () => {
    const redis = {
      evalsha: jest.fn().mockResolvedValue([1, 100, 99, Math.floor(Date.now() / 1000) + 60]),
    };
    const s = new TokenBucketStrategy(redis, { tokenSha: 'sha_tb' });

    const r = await s.evaluate({ bucketKey: 'k:test', capacity: 100, window: '1m' });

    expect(r.allowed).toBe(true);
    expect(r.limit).toBe(100);
    expect(r.remaining).toBe(99);
    expect(typeof r.reset).toBe('number');
    expect(redis.evalsha.mock.calls[0][0]).toBe('sha_tb');
    expect(redis.evalsha.mock.calls[0][2]).toBe('k:test');
  });
});

describe('SlidingWindowStrategy', () => {
  it('interpreta resultado da janela deslizante', async () => {
    const now = Math.floor(Date.now());
    const redis = {
      evalsha: jest.fn().mockResolvedValue([1, 5, 4, Math.floor(now / 1000) + 900]),
    };
    const s = new SlidingWindowStrategy(redis, { slidingSha: 'sha_sw' });

    const r = await s.evaluate({
      bucketKey: 'kw',
      limit: 5,
      window: '15m',
      member: 'm1',
    });

    expect(r.allowed).toBe(true);
    expect(r.limit).toBe(5);
    expect(r.remaining).toBe(4);
    expect(redis.evalsha.mock.calls[0][0]).toBe('sha_sw');
  });
});
