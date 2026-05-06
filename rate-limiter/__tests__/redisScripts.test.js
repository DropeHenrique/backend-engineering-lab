'use strict';

jest.mock('fs', () => ({
  readFileSync: jest.fn((p) => {
    if (String(p).includes('token_bucket')) return 'lua token_bucket';
    if (String(p).includes('sliding_window')) return 'lua sliding_window';
    return '';
  }),
}));

const { loadScripts } = require('../src/redisScripts');

describe('loadScripts', () => {
  it('carrega dois scripts Lua via SCRIPT LOAD em paralelo', async () => {
    const redis = {
      script: jest.fn((_cmd, src) =>
        Promise.resolve(`sha:${String(src).split(' ').pop()?.slice(0, 12) || 'x'}`),
      ),
    };

    const r = await loadScripts(redis);

    expect(redis.script).toHaveBeenCalledTimes(2);
    expect(r.tokenSha).toMatch(/^sha:/);
    expect(r.slidingSha).toMatch(/^sha:/);
    expect(r.tokenSrc).toContain('token_bucket');
    expect(r.slidingSrc).toContain('sliding_window');
  });
});
