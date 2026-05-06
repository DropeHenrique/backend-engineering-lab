'use strict';

describe('planMultiplier', () => {
  beforeEach(() => {
    jest.resetModules();
    delete process.env.PLAN_MULTIPLIER_FREE;
    delete process.env.PLAN_MULTIPLIER_PRO;
    delete process.env.PLAN_MULTIPLIER_ENTERPRISE;
  });

  it('retorna multiplicador configurável para pro e enterprise', () => {
    process.env.PLAN_MULTIPLIER_PRO = '7';
    process.env.PLAN_MULTIPLIER_ENTERPRISE = '11';
    const { planMultiplier } = require('../src/server');

    expect(planMultiplier('pro')).toBe(7);
    expect(planMultiplier('enterprise')).toBe(11);
  });

  it('free e demais planos usam PLAN_MULTIPLIER_FREE', () => {
    process.env.PLAN_MULTIPLIER_FREE = '2';
    const { planMultiplier } = require('../src/server');

    expect(planMultiplier('free')).toBe(2);
    expect(planMultiplier('other')).toBe(2);
  });
});
