ADR-003: Docker Compose para desenvolvimento e simulação de produção

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: global

## Contexto

O lab deve subir Redis, RabbitMQ e os três serviços com um comando, tanto para desenvolvimento (hot reload bind-mount) quanto para simular produção sem Kubernetes.

## Decisão

- `docker-compose.yml`: ambiente **dev** com volumes montados no código Laravel/Node-alvo (`notification-service`, `worker`, `webhook-service`, `rate-limiter`) e imagens estágio `dev`.
- `docker-compose.prod.yml`: override **production-like** (`target: production`, menos bind-mount onde aplicável, `NODE_ENV`/`APP_ENV` de produção).

Healthchecks obrigatórios em Redis, RabbitMQ, APIs e rate-limiter para ordenar dependências (`depends_on.condition: service_healthy`).

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Kubernetes local (kind/k3s) | Paridade máxima com cloud | Curva alta para portfolio; tempo de manutenção |
| Somente Dockerfile sem compose | Simples | Pior ergonomia ao orquestrar 5 containers |
| Dev Containers apenas | BOM para IDE | Não cobre Rabbit + worker + proxy em um comando genérico |

## Consequências

### Positivas

- Paridade razoável de rede e variáveis entre dev e prod simulado.
- Entrevista técnica: `./docker compose config` como prova de entendimento de serviços.

### Negativas / Trade-offs

- Compose **não** substitúi secrets manager, rollout canário ou HA do broker Redis/RabbitMQ.
- Diferenças sutis persistem (`APP_DEBUG`, logs, timeouts de healthcheck).

## Referências

- [Docker Compose — Merge / multiple files](https://docs.docker.com/compose/multiple-compose-files/)
