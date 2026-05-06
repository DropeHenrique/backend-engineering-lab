# Architecture Decision Records (ADR)

Índice de decisões técnicas do **Backend Engineering Lab**. Cada arquivo segue um template único com contexto, decisão, alternativas, consequências e referências.

## Global

- [ADR-001 — RabbitMQ vs Redis como broker](001-rabbitmq-vs-redis.md)
- [ADR-002 — Monorepo vs repositórios separados](002-monorepo.md)
- [ADR-003 — Docker Compose para dev e simulação de produção](003-docker-compose-environments.md)

## notification-service

- [ADR-004 — Laravel Queue (RabbitMQ) vs consumer php-amqplib](004-laravel-queue-vs-amqplib-consumer.md)
- [ADR-005 — Idempotência com Redis SET NX vs apenas DB](005-idempotency-redis-vs-db.md)
- [ADR-006 — Strategy Pattern para handlers de notificação](006-notification-handler-strategy.md)

## rate-limiter

- [ADR-007 — Token Bucket vs Sliding Window](007-token-bucket-vs-sliding-window.md)
- [ADR-008 — Lua Scripts no Redis para atomicidade](008-redis-lua-atomicity.md)
- [ADR-009 — Degradação quando Redis está indisponível](009-redis-down-degradation.md)

## webhook-service

- [ADR-010 — hash_equals versus comparação === para HMAC](010-hash-equals-hmac.md)
- [ADR-011 — Proteção contra replay attack](011-replay-protection.md)
- [ADR-012 — Strategy Pattern para providers de webhook](012-webhook-provider-strategy.md)
- [ADR-013 — Persistência de auditoria: DB versus Redis list](013-webhook-audit-storage.md)
