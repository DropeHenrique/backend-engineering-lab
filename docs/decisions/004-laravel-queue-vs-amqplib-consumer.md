ADR-004: Consumer custom com php-amqplib vs driver Laravel RabbitMQ

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: notification-service

## Contexto

O `notification-service` precisa publicar e consumir um **envelope JSON** canônico (compatível com o `webhook-service`), aplicar backoff explícito, ack/nack e DLQ declarada na topologia RabbitMQ — sem depender do formato serializado padrão de jobs Laravel.

## Decisão

Usar apenas **`php-amqplib`** para:

- declarar exchange/filas/bindings DLQ;
- publicar payloads JSON pela API (`DomainEventPublisher`);
- consumir com o comando Artisan `notifications:consume` (loop `basic_consume`, retries com sleep 1s/5s/25s até `max_attempts`, DLQ por `basic_nack` sem requeue quando excedido).

O pacote **laravel-queue-rabbitmq** não permanece como driver de execução: o contrato de mensagem não é um `ShouldQueue` Laravel.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Driver `vladimir-yuldashev/laravel-queue-rabbitmq` + Jobs | Workers familiares, Horizon opcional | Formato Laravel para payload; backoff/DLQ finos dependentes da integração; acoplamento a serialização PHP |
| Outbox + polling DB | Auditoria forte | Mais latência e tabelas específicas; foge ao foco em AMQP |

## Consequências

### Positivas

- Controle fino de ACK/NACK/DLQ e envelope JSON compatível entre serviços.
- Demonstração direta em entrevistas (código AMQP legível).

### Negativas / Trade-offs

- Boilerplate próprio (`Topology`, comando long-running) versus abstração Laravel Queue.
- Reimplementar retries idempotência no consumer (implementado via Redis).

## Referências

- [php-amqplib](https://github.com/php-amqplib/php-amqplib)
