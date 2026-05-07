# Notification Service

API que recebe eventos de domínio, persiste auditoria mínima e publica um **envelope JSON** no RabbitMQ. Um worker (`php artisan notifications:consume`) processa com **idempotência Redis**, backoff 1s/5s/25s até 3 tentativas e DLQ configurada na topologia.

## Stack

- PHP 8.2 + Laravel 11
- RabbitMQ (`php-amqplib`)
- Redis (`predis`)
- PHPUnit + Larastan (nível 8) + Pint

## Fluxo ASCII

```
POST /api/events → validação por tipo → domain_events(status=received)
    → RabbitMQ(exchange=direct rk=tipo) → consumer → handlers → canais Email mock + Log
```

## Contrato RabbitMQ

- Exchange: `lab.events` (env `RABBITMQ_EXCHANGE`).
- Routing key: tipo (`ORDER_CONFIRMED`, `USER_REGISTERED`, `PASSWORD_RESET`).
- Fila: `notifications` + DLQ `notifications.dlq`.

## RabbitMQ × Redis Pub/Sub

Redis Pub/Sub é fan-out efêmero sem persistência; **não há DLQ nem retentativa nativa**. Para garantir fila e dead-letter usamos Rabbit; Redis fica com idempotência/dedup de processamento.

## Swagger (OpenAPI 3)

Com dependências de desenvolvimento (`composer install`): interface **Swagger UI** em **`/api/documentation`**.

- Docker (host): <http://localhost:8081/api/documentation>
- Regenerar JSON: `composer run docs`

> Em `APP_ENV=production` ou sem `composer install --dev`, o pacote Swagger não existe — use apenas ambientes dev/lab ou gere o arquivo com `composer run docs` onde o código estiver completo.

## At-least-once e duplicidade

O broker pode reentregar; o consumer usa chave `notification:processed:{correlation_id}` com TTL 24h após sucesso. A API deduplica (`correlation_id`,`type`) com HTTP 409.

## Escalar workers

Suba múltiplos processos `notifications:consume` (outro container Compose ou réplicas k8s). Prefetch `basic_qos(0,1,false)` limita inflight por conexão.

## Comandos locais (sem Docker)

```bash
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
composer run test
composer run analyse
```

## Docker

Ver [README raiz](../README.md) — serviço `notification-service` + `notification-worker`.
