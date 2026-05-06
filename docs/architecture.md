# Arquitetura — Backend Engineering Lab

Visão de fluxo do lab: cliente na borda, **rate-limiter** (Node.js), **webhook-service** (Laravel), **RabbitMQ** (exchange `direct` + fila durável + **DLQ** após 3 tentativas), **notification worker** e dispatch para canais. O **Redis** aparece como um único cluster lógico com três usos marcados pelas etiquetas das arestas.

```mermaid
graph LR
  C[Cliente HTTP]

  RL["rate-limiter<br/>Node.js :3000"]

  RL429(["429 Too Many Requests<br/>limite Lua"])

  WH["webhook-service<br/>Laravel :8082"]

  E401(["401 Unauthorized<br/>HMAC inválido"])

  E409(["409 Conflict<br/>replay attack"])

  X["exchange direct<br/>lab.events"]

  Q["fila notifications<br/>durable"]

  W["notification worker<br/>consumidor"]

  DLQ[("notifications.dlq<br/>após 3 tentativas")]

  DISP[NotificationDispatcher]

  EC[EmailChannel]

  LC[LogChannel]

  RDS[("Redis :6379")]

  C --> RL

  RL -->|"dentro da quota"| WH
  RL -->|"excedeu limite"| RL429

  WH -->|"HMAC hash_equals falhou"| E401
  WH -->|"X-Timestamp nonce SET NX TTL"| E409
  WH -->|"payload OK + routing key por tipo"| X
  X -->|"bind por tipo"| Q

  Q --> W

  W -->|"falha após 3 tentativas<br/>backoff 1s 5s 25s"| DLQ
  W -->|"processamento OK"| DISP

  DISP --> EC
  DISP --> LC

  RL -.->|"uso 1: Token Bucket + Sliding Window<br/>scripts Lua atômicos"| RDS
  WH -.->|"uso 2: nonce + replay protection SET NX"| RDS
  W -.->|"uso 3: idempotência correlation_id"| RDS
```

## Legenda

| Elemento | Porta / interface | Responsabilidade |
|----------|-------------------|------------------|
| **Cliente** | — | Envia `POST` (ex.: `/webhook/{provider}`) via proxy do rate-limiter. |
| **rate-limiter** | `3000` (público no compose) | Express; limita com **Token Bucket** e **Sliding Window** (Lua + `EVALSHA`); proxy de `/webhook/*` para o upstream configurado. Responde **429** quando bloqueado. |
| **webhook-service** | `8082` (host; `8080` no container) | Laravel; corpo bruto para HMAC; valida assinatura (**401** se inválida); **replay** com janela de timestamp + **nonce** em Redis (**409** se duplicado); persiste auditoria; publica no broker com **routing key** por tipo de evento. |
| **RabbitMQ** | `5672` (AMQP), `15672` (Management UI) | **Exchange** `direct` (`lab.events`); fila durável `notifications`; mensagens que esgotam **3 tentativas** no worker vão para **DLQ** (`notifications.dlq`). |
| **notification worker** | — (processo `notifications:consume`) | Consome a fila `notifications`; retries com backoff; marca idempotência por `correlation_id` no **Redis**; despacha canais. |
| **notification-service API** | `8081` (host) | `POST /api/events` (fluxo alternativo do lab) também publica no mesmo contrato de exchange/filas; não aparece no ramo principal do diagrama acima. |
| **NotificationDispatcher** | — | Encaminha o resultado do handler para **EmailChannel** (mock) ou **LogChannel**. |
| **Redis** | `6379` | Uso **(1)** buckets no rate-limiter; **(2)** nonce anti-replay no webhook; **(3)** idempotência no worker/aplicação de notificação. |

Arquivo PNG legado (placeholder visual): [architecture.png](architecture.png).
