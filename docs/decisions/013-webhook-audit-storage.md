ADR-013: Auditoria persistente em tabela SQLite `webhook_events`

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: webhook-service

## Contexto

Operadores precisam de visibilidade mínima (aceitos/rejeitos) para demos e troubleshooting sem acessar containers. Redis list poderia funcionar como buffer leve mas carece queries ricas.

## Decisão

Migrar modelo relacional Laravel `WebhookEvent`:

- registra provider, tipo mapeado, `correlation_id`, HTTP status, status lógico e notas opcionais;
- disponibiliza endpoint `GET /webhook/logs` (paginação simples últimos registros neste lab).

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Lista Redis LPUSH | Muito rápido | TTL/volátil; sem JOINs/analytics |
| Log apenas stdout | Infra já coleta ELK/Grafana | Pouco navegável no portfolio isolado |
| Event store CQRS completo | Poder máximo | Fora da entrega atual |

## Consequências

### Positivas

- Portar para PostgreSQL/MySQL em produção real é trivial (`DB_CONNECTION`).
- Ótimo material visual em entrevista (consulta rápida com `sqlite3`/`artisan tinker`).

### Negativas / Trade-offs

- Overhead SQL em cada ingestão bem-sucedida (aceitável no volume demo).
- Necessário planejar purge/rotation em alta escala futura.

## Referências

- [SQLite When To Use](https://www.sqlite.org/whentouse.html)
