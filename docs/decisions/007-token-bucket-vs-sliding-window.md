ADR-007: Token bucket vs janela deslizante — quando usar cada algoritmo

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: rate-limiter

## Contexto

O serviço precisa provar domínio de dois algoritmos clássicos, escolhíveis por rota, com atomicidade no Redis e sem biblioteca pronta de rate limit.

## Decisão

Implementar **ambos** via scripts **Lua** no Redis:

- **Token bucket** como padrão para tráfego geral (ex.: `/api/data`, `/webhook` ingress) — permite **rajadas** controladas até a capacidade do bucket, com recarga contínua `taxa = limite / janela`.
- **Sliding window** em rotas sensíveis (ex.: `POST /api/login`) — precisão mais uniforme no tempo, sem permitir burst concentrado além do limite da janela.

### Comparativo rápido (ASCII)

```
Token Bucket (cap=10, refill 10/min):
|******----| rajada instantânea ok, depois esvazia e recarrega suavemente

Sliding Window (limit 5 / 15m):
|=====window=====>  conta só timestamps dentro da janela — sem rajada acima do limite
```

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Fixed window apenas | Implementação trivial | Pico 2× no boundary da janela |
| Leaky bucket | Suaviza outbound | Mais estado; não solicitado pelo prompt |

## Consequências

### Positivas

- Demonstração explícita de trade-off burst × suavidade em entrevistas.
- Config por rota no Express (`rateLimiterMiddleware`).

### Negativas / Trade-offs

- Custos de memória Redis: sorted set pode crescer sob tráfego muito alto (mitigamos com `ZREMRANGEBYSCORE`).
- Interpretação dos headers `Retry-After`/`Reset` aproximada sob concorrência extrema (aceitável no lab).

## Referências

- [Cloudflare — Rate limiting algorithms](https://blog.cloudflare.com/counting-things-a-lot-of-different-things/)
