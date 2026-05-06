ADR-012: Strategy pattern para validação multi-provider

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: webhook-service

## Contexto

Stripe, GitHub, Hotmart e um provider genérico possuem formatos de headers, corpos e regras HMAC distintos. Um `switch` no controller acoplava lógica Open/Closed negativamente.

## Decisão

- Interface `WebhookProviderContract` (`validate`, `buildEnvelope`).
- `WebhookProviderRegistry` mapeia `{slug => instância}` registrada no `AppServiceProvider`.
- Rota `POST /webhook/{provider}` resolve slug e executa validação + construção do envelope canônico consumido pelo RabbitMQ.

### Adicionar um novo provider (checklist)

1. Criar classe `App\Webhooks\NovaIntegracaoWebhookProvider` implementando o contrato.
2. Registrar a instância no array passado ao construtor de `WebhookProviderRegistry` dentro de `App\Providers\AppServiceProvider` (chave = `slug()`).
3. Cobrir com testes PHPUnit (HMAC, headers, payload mínimo) e documentar segredo em `config/webhooks.php` + `.env`.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Template method em classe base | Menos duplicação | Herança rígida |
| Motor de regras YAML | Configurável por negócio | Sem tipagem; difícil debugar HMAC |
| Lambda por slug | Poucas linhas | Testabilidade pior |

## Consequências

### Positivas

- Providers isolados facilitam mocking e regressão específica.
- Demonstra SOLID/OCP ao vivo.

### Negativas / Trade-offs

- Pequeno passo manual de registrar provider (aceitável no lab).

## Referências

- [Stripe — Signing secrets](https://docs.stripe.com/webhooks#signatures)
- [GitHub Webhooks — Securing payloads](https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries)
