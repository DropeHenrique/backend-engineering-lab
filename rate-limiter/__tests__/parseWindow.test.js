'use strict';

const { parseWindowToSeconds, parseWindowToMs } = require('../src/parseWindow');

describe('parseWindowToSeconds', () => {
  it('parses m, s e h', () => {
    expect(parseWindowToSeconds('1m')).toBe(60);
    expect(parseWindowToSeconds('15m')).toBe(900);
    expect(parseWindowToSeconds('1s')).toBe(1);
    expect(parseWindowToSeconds('2h')).toBe(7200);
  });

  it('parses ms', () => {
    expect(parseWindowToSeconds('500ms')).toBe(0.5);
    expect(parseWindowToMs('1s')).toBe(1000);
  });

  it('rejeita invalido', () => {
    expect(() => parseWindowToSeconds('10x')).toThrow('Invalid window');
  });
});
