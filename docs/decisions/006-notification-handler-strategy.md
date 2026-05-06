ADR-006: Strategy pattern para handlers de notificação

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: notification-service

## Contexto

Cada tipo de evento tem regras de payload e canais de notificação distintos. Um `switch` central cresce com o tempo, quebra SRP e dificulta testes unitários isolados.

## Decisão

- Interface `EventTypeHandler` com `supports(string $type): bool` e `handle(...)`.
- Implementações: `OrderConfirmedHandler`, `UserRegisteredHandler`, `PasswordResetHandler`.
- `EventHandlerRegistry` resolve o handler por tipo e injeta `NotificationDispatcher` (canais `Email` mock + `Log`).
- Novos tipos = nova classe + bind no `AppServiceProvider`.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Match/switch monolítico | Rápido de escrever | Viola OCP; difícil testar por tipo |
| Chain of responsibility | Extensível | Complexidade desnecessária para 3 tipos fixos |
| Event sourcing completo | Histórico rico | Fora do escopo do lab |

## Consequências

### Positivas

- Testabilidade por handler.
- Alinhamento com SOLID (OCP/DIP via container).

### Negativas / Trade-offs

- Pequena taxa de boilerplate (registry + N classes).

## Referências

- [Refactoring Guru — Strategy](https://refactoring.guru/design-patterns/strategy)
