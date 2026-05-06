ADR-009: Falha controlada quando Redis está indisponível

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: rate-limiter

## Contexto

Se o Redis cair em produção, o rate limiter não pode derrubar o stack inteiro; precisamos declarar comportamento sob degradação.

## Decisão

Variável **`REDIS_DOWN_MODE`** aceita:

- **`allow`** (default): permite tráfego (fail-open) registrando logs `rate_limit_degraded` / erros tratados nos middleware.
- **`deny`**: responde `503 Rate limit unavailable` quando não é possível executar Lua.

Valor default **`allow`** com **logging de alerta** para destacar segurança reduzida enquanto o Redis volta.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Sempre deny | Mais seguro | Disponibilidade zero se Redis flapar |
| Sempre allow | Alta disponibilidade | Abuso fácil em incidente prolongado |
| Circuit breaker distribuído (ex.: Consul) | Robusto operacionalmente | Alto esforço fora do escopo Docker Compose |

## Consequências

### Positivas

- Operadores escolhem risco conscientemente (avail vs abuse).

### Negativas / Trade-offs

- `allow` aumenta superfície de abuso sob ataque combinado à indisponibilidade Redis.
- Logs precisam ser monitorados externamente ao lab.

## Referências

- [Google SRE — graceful degradation patterns](https://sre.google/sre-book/managing-load/)
