'use strict';

/** @typedef {{ allowed:boolean, limit:number, remaining:number, reset:number }} RateResult */

class TokenBucketStrategy {
  constructor(redis, shas) {
    this.redis = redis;
    this.sha = shas.tokenSha;
  }

  /**
   * @param {object} opts
   * @param {string} opts.bucketKey redis key prefix complete
   * @param {number} opts.capacity
   * @param {string} opts.window interval for computing refill rate (= capacity / duration)
   * @returns {Promise<RateResult>}
   */
  async evaluate({ bucketKey, capacity, window }) {
    const { parseWindowToSeconds } = require('../parseWindow');
    const winSec = parseWindowToSeconds(window);
    const rate = capacity / Math.max(winSec, 1e-6);
    const nowSec = Date.now() / 1000;
    const argv = [String(Math.ceil(capacity)), String(rate), String(nowSec), '1'];

    /** @type {number[]} */
    const res = /** @type {any} */ (await this.redis.evalsha(this.sha, 1, bucketKey, ...argv));
    const allowed = res[0] === 1;
    const limit = res[1];
    const remaining = res[2];
    const reset = res[3];
    return { allowed, limit, remaining, reset };
  }
}

class SlidingWindowStrategy {
  constructor(redis, shas) {
    this.redis = redis;
    this.sha = shas.slidingSha;
  }

  /**
   * @param {object} opts
   * @param {string} opts.bucketKey
   * @param {number} opts.limit max requests per window
   * @param {string} opts.window
   * @param {string} opts.member uuid
   */
  async evaluate({ bucketKey, limit, window, member }) {
    const { parseWindowToMs } = require('../parseWindow');
    const windowMs = parseWindowToMs(window);
    const nowMs = Date.now();
    const argv = [String(nowMs), String(windowMs), String(Math.ceil(limit)), member];

    /** @type {number[]} */
    const res = /** @type {any} */ (await this.redis.evalsha(this.sha, 1, bucketKey, ...argv));
    const allowed = res[0] === 1;
    return {
      allowed,
      limit: res[1],
      remaining: Math.max(res[2], 0),
      reset: res[3],
    };
  }
}

module.exports = { TokenBucketStrategy, SlidingWindowStrategy };
