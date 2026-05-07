# Webhook Service

Recepção segura de webhooks (`POST /webhook/{provider}`) com **raw body**, HMAC-SHA256 por provider, **anti-replay** (`X-Timestamp` + `X-Nonce`), auditoria (`webhook_events`) e publicação no **mesmo contrato RabbitMQ** do notification service.

## Stack

- Laravel 11 + PHP 8.2
- Redis (nonce + TTL)
- `php-amqplib` publisher
- PHPUnit + Larastan + Pint

## Swagger (OpenAPI 3)

Swagger UI em **`/api/documentation`** quando as dev-deps estão instaladas.

- Direto ao Laravel (host Compose): <http://localhost:8082/api/documentation>
- Atrás do rate-limiter: ajuste o “Server” na UI ou use Try it com URL base `http://localhost:3000` apenas para `/webhook/*` (demais rotas ficam no :8082).
- Regenerar: `composer run docs`

## Providers

| Slug       | Validação resumida |
|------------|---------------------|
| `stripe`   | Header `Stripe-Signature` (`t` + `v1` HMAC, segredo `whsec_` decodificado quando aplicável) |
| `github`   | `X-Hub-Signature-256` (`sha256=`) |
| `hotmart`  | Simulação lab: `X-Signature` hex HMAC do corpo bruto |
| `generic`  | `X-Signature` hex HMAC do corpo com JSON canônico `{type,payload,metadata}` |

Todos exigem `X-Timestamp` e `X-Nonce` (lab) — ver ADR-011.

## Exemplo de payload genérico

```json
{
  "type": "ORDER_CONFIRMED",
  "payload": { "order_id": "ord_1", "user_email": "a@b.com" },
  "metadata": {
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
    "timestamp": "2026-05-06T12:00:00+00:00"
  }
}
```

Assinatura: `X-Signature = HMAC_SHA256_HEX(raw_body, WEBHOOK_GENERIC_SECRET)`.

## Auditoria

`GET /webhook/logs` lista últimos eventos persistidos.

## Testes

```bash
composer run test
composer run analyse
```

## Integração

Publica no exchange `RABBITMQ_EXCHANGE` com routing key = `type` mapeado. Veja [notification-service README](../notification-service/README.md).
