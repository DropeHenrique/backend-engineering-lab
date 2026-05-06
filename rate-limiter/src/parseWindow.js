'use strict';

/**
 * @param {string} window
 * @returns {number} seconds
 */
function parseWindowToSeconds(window) {
  const m = /^(\d+)(ms|s|m|h)$/i.exec(String(window).trim());
  if (!m) throw new Error(`Invalid window: ${window}`);
  const n = Number(m[1]);
  const u = m[2].toLowerCase();
  if (u === 'ms') return n / 1000;
  if (u === 's') return n;
  if (u === 'm') return n * 60;
  if (u === 'h') return n * 3600;
  return n * 60;
}

/**
 * @param {string} window
 * @returns {number} ms
 */
function parseWindowToMs(window) {
  return Math.ceil(parseWindowToSeconds(window) * 1000);
}

module.exports = { parseWindowToSeconds, parseWindowToMs };
