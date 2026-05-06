ADR-002: Monorepo com três serviços e CI por pasta

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: global

## Contexto

O portfólio precisa mostrar PHP (Laravel) e Node (Express) integrados, com um único clone e demonstração de fluxo ponta a ponta, sem operar três repositórios.

## Decisão

Manter **um monorepo** `backend-engineering-lab` com pastas `notification-service/`, `webhook-service/`, `rate-limiter/` e pipelines **GitHub Actions** disparados por `paths` por serviço.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| Três repositórios | Deploy e versionamento independentes | Pior DX para demo; drift de contrato RabbitMQ; mais overhead de PR cruzado |
| Monorepo + pipeline único | Menos YAML | Falhas em um serviço bloqueiam todos; builds mais lentos |
| Git submodules | Separa histórico | Complexidade desnecessária para portfólio |

## Consequências

### Positivas

- Onboarding com um `git clone` e `docker compose up`.
- ADRs e diagramas versionados junto do código.
- CI isolado por serviço reduz ruído.

### Negativas / Trade-offs

- Deploy “real” costuma exigir separar artefatos por imagem (três builds), acoplados ao mesmo repositório.
- Permissões e secrets do GitHub podem precisar escopo por workflow.

## Referências

- [Monorepo explained (nrwl)](https://nx.dev/concepts/more-concepts/why-monorepos)
