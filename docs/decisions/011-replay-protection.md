ADR-011: Anti-replay com nonce no Redis + timestamp

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: webhook-service

## Contexto

HMAC válido prova integridade e autenticidade do corpo, mas **não impede reenvio**: um atacante poderia capturar uma requisição válida e repeti-la (replay) para causar efeitos duplicados no downstream se o processamento não for idempotente o suficiente.

## Decisão

Exigir headers de laboratório:

- `X-Timestamp` (epoch segundos decimal string) — rejeitar se `abs(now - ts) > replay_window` (default **300** s alinhado ao TTL de nonce).
- `X-Nonce` — armazenado em Redis/Cache com **`SET` idempotente equivalente (`Cache::add`)** e TTL **300** s. Colisão ⇒ HTTP **409 Conflict**.

Providers que já carregam tempo assinado (ex.: Stripe dentro de `Stripe-Signature`) ainda respeitam a checagem própria no provider, mas os headers acima permanecem obrigatórios no lab para uniformidade.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Só timestamp | Simples | Replay dentro da janela ainda possível |
| Só nonce | Simples | Replay após expiração TTL se clock não validado |
| Assinatura TLS mTLS | Forte | Operação pesada; não substitui replay lógico |

## Consequências

### Positivas

- Demonstra defesa em camadas (integridade + freshness + unicidade).

### Negativas / Trade-offs

- Clientes precisam gerar nonce e relógio estável.
- Clock skew exige monitoramento (janela configurável).

## Referências

- [OWASP — Replay Attack](https://owasp.org/www-community/attacks/Replay_attack)
