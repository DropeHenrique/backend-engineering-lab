ADR-005: Idempotência de processamento com Redis + auditoria SQLite

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: notification-service

## Contexto

Mensagens AMQP são **pelo menos uma vez**. Reprocessamentos (redelivery, novo worker ou bug) não podem enviar dois e-mails reais ou duplicar efeitos colaterais perceptíveis. É necessário um guard rápido e um registro audível opcional por evento de API.

## Decisão

- **Linha rápida:** `IdempotencyGuard` usa `Cache` (Redis em produção) com chave `notification:processed:{correlation_id}` e TTL configurável (**86400** segundos padrão) após sucesso da pipeline de handler.
- **Auditoria de ingestão pela API:** tabela `domain_events` registra ciclo `received → queued → processed/failed` para eventos criados pela rota REST (duplicação retorna HTTP 409 via unique (`correlation_id`,`type`)).

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Somente tabela “processed” | Durável | Latência maior; pressão no SQLite em alto throughput |
| Somente Redis | Muito rápido | Perde guarda se Redis for limpo/recriado durante janela |
| Exactly-once global | Ideal teórico | Impossível de forma prática entre DB + fila sem transações distribuídas caras |

## Consequências

### Positivas

- Check O(1) antes de processar o handler.
- Duplicatas de API detectadas cedo (constraint SQL).

### Negativas / Trade-offs

- Se Redis reiniciar **e** a mesma mensagem for reentregue **antes** de reprocessar DB, ainda pode haver janela curta — mitigada por TTL alinhado ao SLA de retenção do broker.
- Eventos **apenas** publicados pelo webhook não criam linha `domain_events` até evolução futura (idempotência continua via Redis).

## Referências

- [RabbitMQ — At Least Once Delivery](https://www.rabbitmq.com/docs/confirms)
