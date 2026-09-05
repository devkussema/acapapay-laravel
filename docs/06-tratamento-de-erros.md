# 6. Tratamento de Erros

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).

Desde a v1.2.0, o SDK lança exceções tipadas — mas todas descendem de `\Exception` (via `\RuntimeException`), pelo que o teu `catch (\Exception $e)` já existente continua a funcionar sem alterações.

## Hierarquia

```
Exception
└─ RuntimeException
   └─ AcapaPayException                 (marcador base)
      ├─ AuthenticationException        (falha no OAuth2)
      ├─ ConnectionException            (rede/DNS/SSL)
      └─ ApiException                   (qualquer erro HTTP da API)
         ├─ ValidationException         (422 — tem errors())
         ├─ NotFoundException           (404)
         └─ ForbiddenException          (403)
```

## Exemplo de uso

```php
use AcapaPay\Laravel\Exceptions\ValidationException;
use AcapaPay\Laravel\Exceptions\AuthenticationException;
use AcapaPay\Laravel\Exceptions\ConnectionException;
use AcapaPay\Laravel\Exceptions\ApiException;

try {
    $charge = AcapaPay::direct()->charge($invoiceId, PaymentMethod::RDP);
} catch (ValidationException $e) {
    // 422 — dados recusados. $e->errors() traz os campos em falta.
    return back()->withErrors($e->errors());
} catch (AuthenticationException $e) {
    // Credenciais erradas no .env
    report($e);
} catch (ConnectionException $e) {
    // Rede/DNS/SSL — é seguro voltar a tentar
    return back()->with('error', 'Serviço temporariamente indisponível.');
} catch (ApiException $e) {
    // Qualquer outro erro HTTP: $e->status(), $e->apiError(), $e->isRetryable()
    report($e);
}
```

## Tabela de exceções

| Exceção | Significado | Métodos úteis |
|---|---|---|
| `ValidationException` | `422` — dados inválidos | `errors(): array` — os erros por campo, tal como devolvidos pelo Laravel do lado do servidor |
| `AuthenticationException` | Falha no OAuth2 — credenciais erradas ou App desativada | — |
| `NotFoundException` | `404` — fatura inexistente ou que não pertence à tua App | — |
| `ForbiddenException` | `403` — operação não permitida (ex: `simulate()` fora de sandbox) | — |
| `ConnectionException` | Falha de rede/DNS/SSL — nada chegou ao servidor | Seguro tentar novamente |
| `ApiException` | Base das três anteriores; qualquer outro erro HTTP não coberto | `status(): int`, `body(): string`, `apiError(): ?string`, `uri(): string`, `httpMethod(): string`, `isRetryable(): bool` (true para status ≥ 500) |

## Retentativas automáticas

O SDK já trata de dois casos automaticamente, sem precisares de código extra:

- **Falhas de rede/timeout:** repetidas automaticamente (`retry_times`/`retry_sleep`, ver [01-instalacao-e-configuracao.md](01-instalacao-e-configuracao.md)). Um erro `422`/`404`/`403` **nunca** é repetido — só faz sentido repetir problemas transitórios.
- **Token expirado (401):** o SDK deteta, limpa a cache do token, obtém um novo, e repete o pedido original **uma vez** automaticamente. Só lança `AuthenticationException` se o segundo pedido também falhar.

---

**A seguir:** [07-sandbox-e-testes.md](07-sandbox-e-testes.md) — testar sem gerar cobranças reais.
