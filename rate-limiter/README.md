# Rate Limiter (Node 20 + Express)

Serviço de borda com **token bucket** e **sliding window** implementados em **Lua + Redis** (`EVALSHA`), multi-tenant via header `X-Plan` (`free|pro|enterprise`) e multiplicadores `PLAN_MULTIPLIER_*`. Faz proxy de `/webhook` para `WEBHOOK_UPSTREAM` (fluxo integrado do lab).

## Algoritmos

- **Token bucket** — permite rajadas até a capacidade; taxa `limite / janela`.
- **Sliding window** — sorted set com expurgo por score; sem burst além do limite.

### Quando usar

| Cenário | Sugestão |
|---------|----------|
| APIs de leitura com tráfego bursty | Token bucket |
| Login / mutações sensíveis | Sliding window |

## Swagger UI

Documentação OpenAPI servida pelo próprio Express:

- **UI**: <http://localhost:3000/api-docs>
- **JSON**: <http://localhost:3000/openapi.json>

## Operação

```bash
npm install
npm run lint
npm test
REDIS_URL=redis://127.0.0.1:6379 npm run test:integration  # opcional
```

### k6 (smoke)

```bash
BASE_URL=http://127.0.0.1:3000 k6 run load-test/smoke.js
```

## Variáveis

| Variável | Descrição |
|----------|-----------|
| `REDIS_URL` | Conexão Redis |
| `REDIS_DOWN_MODE` | `allow` (default) ou `deny` |
| `WEBHOOK_UPSTREAM` | URL base do webhook-service |
| `PLAN_MULTIPLIER_FREE\|PRO\|ENTERPRISE` | Multiplicador do limite |
| `RATE_IDENTIFY_MODE` | `ip`, `api_key`, `user_id` |

## Limitações conhecidas

Rate limiting perfeito em cluster multi-master Redis exige políticas adicionais (hash tags, proximidade). Este lab assume **um nó Redis** como no `docker-compose`.

## Benchmark

Colete `http_req_duration` e taxa de `429` no output do k6; documente ambiente (CPU, `vus`, duração) ao divulgar números.
