ADR-008: Scripts Lua no Redis para atomicidade (EVALSHA)

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: rate-limiter

## Contexto

Em alta concorrência, `GET` + `SET` separados falham sob race condition (dois pods ou workers concedem dois tokens onde só deveria caber um).

## Decisão

Todos os increments/decréscimos dos algoritmos rodam dentro de scripts **Lua** carregados com `SCRIPT LOAD` e executados por **`EVALSHA`**, mantendo cópias em memória dos SHA em `redisScripts.loadScripts()`.

MULTI/EXEC com `WATCH` foi descartado: exige retrys de transação quando colide; Lua executa atomicamente em uma única interpretação Redis.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| MULTI / EXEC (`WATCH`) | Padrão Redis | Aborta em conflitos; cliente precisa backoff |
| Redlock distribuído | Coordena vários masters | Pesado para contador por chave |
| Lua | Atomicidade garantida | Debug mais árido; erro de runtime rejeita script |

## Consequências

### Positivas

- Corretude sob concorrência no single shard Redis deste laboratório.

### Negativas / Trade-offs

- Migrar clusters Redis com failover exige garantir hashing da mesma chave no mesmo nó ou migrar pra outro padrão.
- Scripts precisam ser versionados quando alterados (mudança altera comportamento atomicamente ao carregar novo SHA).

## Referências

- [Redis Programmability — Lua scripting](https://redis.io/docs/latest/develop/programmability/lua/)
