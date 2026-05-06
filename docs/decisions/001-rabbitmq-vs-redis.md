ADR-001: RabbitMQ como broker principal (vs Redis Pub/Sub / Streams)

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: global

## Contexto

Os serviços `notification-service` e `webhook-service` precisam de um canal assíncrono confiável para eventos de domínio. O laboratório precisa demonstrar persistência, DLQ, roteamento por tipo e operação em ambiente sem Kubernetes.

## Decisão

Adotar **RabbitMQ** com exchange **direct** durável, filas duráveis, bindings por routing key igual ao tipo de evento (`ORDER_CONFIRMED`, `USER_REGISTERED`, `PASSWORD_RESET`) e fila **DLQ** (`notifications.dlq`) acoplada via `x-dead-letter-exchange` / `x-dead-letter-routing-key`. Mensagens publicadas com `delivery_mode` persistente.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Redis Pub/Sub | Baixa latência, stack única com cache | Fire-and-forget: sem persistência nativa de fila, sem DLQ, perda em crash de consumidor |
| Redis Streams | Persistência, grupos de consumidores, bom para logs | Menos maduro que Rabbit para DLQ/roteamento declarativo; UI de operação inferior ao Management do Rabbit |
| Amazon SQS / cloud | Totalmente gerenciado | Fora do escopo “rodar local com Docker” do lab |

## Consequências

### Positivas

- Garantias de fila e DLQ alinhadas a padrões de produção.
- UI de management (porta 15672) para entrevistas e debug.
- Contrato claro com routing key = tipo de evento.

### Negativas / Trade-offs

- Mais um componente operacional (memory footprint, tuning de conexões).
- Definição explícita de topologia necessária em publisher e consumer.

## Referências

- [RabbitMQ Durability & Persistence](https://www.rabbitmq.com/docs/queues#durability)
- [Dead Letter Exchanges](https://www.rabbitmq.com/docs/dlx)
- [Redis Pub/Sub vs Streams (Redis docs)](https://redis.io/docs/latest/develop/interact/pubsub/)
