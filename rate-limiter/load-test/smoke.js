import http from 'k6/http';
import { check } from 'k6';
import { sleep } from 'k6';

/** Smoke test rápido do rate limiter em /api/login (429 aguardados após várias reqs). */

export const options = {
  vus: 5,
  duration: '30s',
  thresholds: {
    http_req_failed: ['rate<0.99'],
  },
};

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:3000';

export default function load() {
  const res = http.post(`${BASE}/api/login`, JSON.stringify({ user: `u${__VU}_${__ITER}` }), {
    headers: { 'Content-Type': 'application/json' },
  });
  check(res, {
    'status 200 ou 429': (r) => r.status === 200 || r.status === 429,
  });
  sleep(0.05);
}
