'use strict';

const fs = require('fs');
const path = require('path');

/**
 * Carrega SCRIPT LOAD uma vez por instância Redis.
 *
 * @param {import('ioredis').default} redis
 */
async function loadScripts(redis) {
  const tokenSrc = fs.readFileSync(path.join(__dirname, '../scripts/token_bucket.lua'), 'utf8');
  const slidingSrc = fs.readFileSync(path.join(__dirname, '../scripts/sliding_window.lua'), 'utf8');
  const [tokenSha, slidingSha] = await Promise.all([
    redis.script('LOAD', tokenSrc),
    redis.script('LOAD', slidingSrc),
  ]);

  return { tokenSha, slidingSha, tokenSrc, slidingSrc };
}

module.exports = { loadScripts };
