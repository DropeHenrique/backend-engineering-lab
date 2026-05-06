ADR-010: `hash_equals()` para comparação de assinatura HMAC

Status: Accepted  
Data: 2026-05-06  
Contexto do serviço: webhook-service

## Contexto

Strings de assinatura criptográfica (HMAC SHA-256) devem ser comparadas de forma **constant-time**. Uso de operador `===` com comparação curto-circuitada pode vazar diferenças de timing e permitir forja byte a byte (ataque teórico/prático em contextos sensíveis).

## Decisão

Todas as comparações de digest recebido × digest calculado utilizam `hash_equals(string $known, string $user)` (ou equivalente garantido constant-time no provider), nunca `===` direto em material bruto de HMAC.

### Exemplo minimal (PHP)

```php
$expected = hash_hmac('sha256', $rawBody, $secret);
$bad  = $received === $expected;            // evitar
$good = hash_equals($expected, $received);  // ok
```

GitHub adiciona prefixo `sha256=` — comparamos apenas o digest hexadecimal após normalizar.

## Alternativas consideradas

| Alternativa | Prós | Contras |
|-------------|------|---------|
| `===` direto | Mais simples | Falha em constant-time |
| `substr_compare` com length check | Rápido | Fácil introduzir regressão; `hash_equals` é idiomático em PHP |
| Biblioteca crypto dedicada | Mais features | Overkill para webhook HMAC |

## Consequências

### Positivas

- Demonstra consciência OWASP / side channels em entrevistas.

### Negativas / Trade-offs

- Não substitui outros requisitos (TLS, rotação de segredo, política de IPs de origem).

## Referências

- [PHP hash_equals](https://www.php.net/manual/en/function.hash-equals.php)
