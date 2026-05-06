'use strict';

const mockEvaluate = jest.fn();

jest.mock('../src/strategies', () => ({
  TokenBucketStrategy: jest.fn().mockImplementation(() => ({ evaluate: mockEvaluate })),
  SlidingWindowStrategy: jest.fn().mockImplementation(() => ({ evaluate: mockEvaluate })),
}));

const mockPingOrNull = jest.fn();
jest.mock('../src/redisClient', () => ({
  pingOrNull: (...args) => mockPingOrNull(...args),
}));

const { rateLimiterMiddleware } = require('../src/middleware/rateLimiter');

function mockRes() {
  const res = {
    status: jest.fn().mockReturnThis(),
    json: jest.fn().mockReturnThis(),
    setHeader: jest.fn().mockReturnThis(),
  };
  return res;
}

describe('rateLimiterMiddleware', () => {
  const baseDeps = () => ({
    redis: { status: 'ready', evalsha: jest.fn() },
    shas: { tokenSha: 't1', slidingSha: 's1' },
  });

  const waitRedisDeps = () => ({
    redis: { status: 'wait', evalsha: jest.fn() },
    shas: { tokenSha: 't1', slidingSha: 's1' },
  });

  beforeEach(() => {
    jest.clearAllMocks();
    mockEvaluate.mockReset();
    mockPingOrNull.mockReset();
    delete process.env.REDIS_DOWN_MODE;
    mockPingOrNull.mockResolvedValue(true);
    mockEvaluate.mockResolvedValue({
      allowed: true,
      limit: 100,
      remaining: 99,
      reset: Math.floor(Date.now() / 1000) + 120,
    });
  });

  it('chama next quando permitido', async () => {
    const next = jest.fn();
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 10,
      window: '1m',
      routeKey: 't',
    });
    await mw({ method: 'GET', path: '/x', ip: '1.1.1.1', headers: {} }, mockRes(), next);
    expect(next).toHaveBeenCalled();
  });

  it('responde 429 quando bloqueado', async () => {
    const reset = Math.floor(Date.now() / 1000) + 120;
    mockEvaluate.mockResolvedValue({
      allowed: false,
      limit: 2,
      remaining: 0,
      reset,
    });
    const res = mockRes();
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 2,
      window: '1m',
      routeKey: 't',
    });
    await mw({ method: 'GET', path: '/x', ip: '1.1.1.1', headers: {} }, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(429);
    expect(res.json).toHaveBeenCalledWith(
      expect.objectContaining({ error: 'Too Many Requests', limit: 2 }),
    );
  });

  it('falha aberto com Redis indisponível quando REDIS_DOWN_MODE=allow', async () => {
    mockPingOrNull.mockResolvedValue(false);
    const next = jest.fn();
    const mw = rateLimiterMiddleware(waitRedisDeps(), {
      algorithm: 'token_bucket',
      limit: 10,
      window: '1m',
    });
    await mw({ method: 'GET', path: '/x', ip: '9.9.9.9', headers: {} }, mockRes(), next);
    expect(next).toHaveBeenCalled();
  });

  it('responde 503 em deny quando Redis está indisponível', async () => {
    process.env.REDIS_DOWN_MODE = 'deny';
    mockPingOrNull.mockResolvedValue(false);
    const res = mockRes();
    const mw = rateLimiterMiddleware(waitRedisDeps(), {
      algorithm: 'token_bucket',
      limit: 10,
      window: '1m',
    });
    await mw({ method: 'GET', path: '/x', ip: '9.9.9.9', headers: {} }, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(503);
  });

  it('algoritmo desconhecido chama next', async () => {
    const next = jest.fn();
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: '__invalid__',
      limit: 1,
      window: '1m',
    });
    await mw({ method: 'GET', path: '/', ip: '1.1.1.1', headers: {} }, mockRes(), next);
    expect(next).toHaveBeenCalled();
    expect(mockEvaluate).not.toHaveBeenCalled();
  });

  it('erro na avaliação com allow segue para next', async () => {
    process.env.REDIS_DOWN_MODE = 'allow';
    mockEvaluate.mockRejectedValue(new Error('evalsha falhou'));
    const next = jest.fn();
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 10,
      window: '1m',
    });
    await mw({ method: 'GET', path: '/', ip: '1.2.3.4', headers: {} }, mockRes(), next);
    expect(next).toHaveBeenCalled();
  });

  it('janela inválida no 429 preserva texto original na resposta JSON', async () => {
    const reset = Math.floor(Date.now() / 1000) + 999;
    mockEvaluate.mockResolvedValue({
      allowed: false,
      limit: 1,
      remaining: 0,
      reset,
    });
    const res = mockRes();
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 1,
      window: '__nao-valido__',
    });
    await mw({ method: 'GET', path: '/', ip: '1.2.3.4', headers: {} }, res, jest.fn());
    expect(res.json).toHaveBeenCalledWith(expect.objectContaining({ window: '__nao-valido__' }));
  });

  it('erro na avaliação com deny devolve 503', async () => {
    process.env.REDIS_DOWN_MODE = 'deny';
    mockEvaluate.mockRejectedValue(new Error('boom'));
    const res = mockRes();
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 2,
      window: '30s',
    });
    await mw({ method: 'GET', path: '/', ip: '8.8.8.8', headers: {} }, res, jest.fn());
    expect(res.status).toHaveBeenCalledWith(503);
  });

  it('sliding_window envia member para evaluate', async () => {
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'sliding_window',
      limit: 5,
      window: '15m',
      routeKey: 'POST:/api/login',
    });
    await mw(
      { method: 'POST', path: '/api/login', ip: '1.0.0.1', headers: {} },
      mockRes(),
      jest.fn(),
    );
    expect(mockEvaluate).toHaveBeenCalledWith(
      expect.objectContaining({
        bucketKey: expect.stringContaining('sliding_window'),
        limit: 5,
        window: '15m',
        member: expect.any(String),
      }),
    );
  });

  it('identifica por X-Api-Key quando RATE_IDENTIFY_MODE=api_key', async () => {
    process.env.RATE_IDENTIFY_MODE = 'api_key';
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 20,
      window: '1m',
    });
    await mw(
      { method: 'GET', path: '/z', ip: '1.2.3.4', headers: { 'x-api-key': 'chave-alfa' } },
      mockRes(),
      jest.fn(),
    );
    expect(mockEvaluate.mock.calls[0][0].bucketKey).toContain('chave-alfa');
    delete process.env.RATE_IDENTIFY_MODE;
  });

  it('identifica por X-User-Id quando RATE_IDENTIFY_MODE=user_id', async () => {
    process.env.RATE_IDENTIFY_MODE = 'user_id';
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 20,
      window: '1m',
    });
    await mw(
      { method: 'GET', path: '/z', ip: '1.2.3.4', headers: { 'x-user-id': 'usr-9' } },
      mockRes(),
      jest.fn(),
    );
    expect(mockEvaluate.mock.calls[0][0].bucketKey).toContain('usr-9');
    delete process.env.RATE_IDENTIFY_MODE;
  });

  it('fallback Authorization quando identify é api_key e não há X-Api-Key', async () => {
    process.env.RATE_IDENTIFY_MODE = 'api_key';
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 2,
      window: '1m',
    });
    await mw(
      { method: 'GET', path: '/', ip: '2.2.2.2', headers: { authorization: 'Bearer xyz' } },
      mockRes(),
      jest.fn(),
    );
    expect(mockEvaluate.mock.calls[0][0].bucketKey).toContain('Bearer xyz');
    delete process.env.RATE_IDENTIFY_MODE;
  });

  it('cabecalho X-Plan pro aplica planMultiplier externo', async () => {
    const mult = jest.fn(() => 3);
    const deps = {
      redis: baseDeps().redis,
      shas: baseDeps().shas,
      planMultiplier: mult,
    };
    mockEvaluate.mockImplementation(() =>
      Promise.resolve({
        allowed: true,
        limit: 99,
        remaining: 1,
        reset: Math.floor(Date.now() / 1000) + 10,
      }),
    );

    const mw = rateLimiterMiddleware(deps, {
      algorithm: 'token_bucket',
      limit: 10,
      window: '1m',
    });
    await mw(
      { method: 'GET', path: '/', ip: '1.2.3.4', headers: { 'x-plan': 'PRO' } },
      mockRes(),
      jest.fn(),
    );

    expect(mult).toHaveBeenCalledWith('pro');
    expect(mockEvaluate.mock.calls[0][0].capacity).toBe(30);
  });

  it('plano enterprise mapeado corretamente', async () => {
    const mw = rateLimiterMiddleware(baseDeps(), {
      algorithm: 'token_bucket',
      limit: 10,
      window: '1m',
    });
    await mw(
      { method: 'GET', path: '/', ip: '1.2.3.4', headers: { 'x-plan': 'Enterprise' } },
      mockRes(),
      jest.fn(),
    );

    expect(mockEvaluate.mock.calls[0][0].bucketKey).toContain(':enterprise:');
  });
});
