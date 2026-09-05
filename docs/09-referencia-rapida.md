# 9. Referência Rápida

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).
>
> Esta página é um resumo compacto de tudo — útil para consulta rápida ou para dar contexto a uma ferramenta/agente de IA sem ela ter de ler toda a documentação. Para explicações e exemplos, seguir os links de cada secção.

## Facade `AcapaPay`

| Método | Retorno | Descrição | Doc |
|---|---|---|---|
| `checkoutSession($userId, $planReference, $metadata=[], $successUrl=null, $cancelUrl=null, $currency=null, $preferredMethod=null)` | `string` | Cria sessão de checkout hospedado, devolve o URL. | [02](02-checkout-hospedado.md) |
| `createInvoice($userId, $amount, $description, $currency='USD', $preferredMethod=null, $metadata=[], $successUrl=null, $cancelUrl=null)` | `string` | Fatura avulsa (sem plano), checkout hospedado. | [02](02-checkout-hospedado.md) |
| `getInvoiceStatus($invoiceId)` | `array` | Consulta o estado de uma fatura. | [02](02-checkout-hospedado.md) |
| `syncPlans($plans)` | `array` | Sincroniza planos em lote. | [08](08-sincronizacao-de-planos.md) |
| `direct()` / `invoices()` | `DirectPaymentApi` | Acesso à API de pagamento direta. | [03](03-pagamento-direto-api.md) |
| `createCryptoCharge($invoiceAttributes)` | `ChargeResult` | Cria fatura USD + cobrança cripto num passo. | [04](04-pagamentos-cripto-redotpay.md) |
| `ping()` | `array` | Testa a conectividade com a API. | [01](01-instalacao-e-configuracao.md) |

## `AcapaPay::direct()` — `DirectPaymentApi`

| Método | Retorno | Descrição |
|---|---|---|
| `createInvoice(array $attributes)` | `array` | `{status, invoice_id, pay_url, total, currency}` |
| `charge($invoiceId, $method, $phone=null)` | `ChargeResult` | Gera a cobrança |
| `chargeWithCrypto($invoiceId)` | `ChargeResult` | Atalho para `charge(..., PaymentMethod::RDP)` |
| `status($invoiceId)` | `array` | `{invoice_status, paid_at, transaction}` |
| `isPaid($invoiceId)` | `bool` | — |
| `find($invoiceId)` | `array` | Detalhe completo |
| `simulate($invoiceId)` | `array` | Só em sandbox |

Ver [03-pagamento-direto-api.md](03-pagamento-direto-api.md).

## `ChargeResult`

| Método | Retorno |
|---|---|
| `payUrl()` | `?string` |
| `reference()` / `entity()` | `?string` (método `REF`) |
| `expiresAt()` | `?string` (ISO 8601) |
| `paymentMethod()` | `?string` |
| `isMock()` | `bool` |
| `paymentMethods()` | `array` (método `RDP`) |
| `qrCodeUrls()` | `array` (método `RDP`) |
| `appUrl($walletId)` | `?string` (método `RDP`) |
| `data()` | `array` (payload bruto) |

Também funciona como array (`$charge['data']['pay_url']`). Ver [03](03-pagamento-direto-api.md) e [04](04-pagamentos-cripto-redotpay.md).

## Comandos Artisan

| Comando | Descrição | Doc |
|---|---|---|
| `php artisan acapapay:test-connection` | Testa a autenticação e a API. | [01](01-instalacao-e-configuracao.md) |
| `php artisan acapapay:sync-plans` | Sincroniza os planos locais com o AcapaPay. | [08](08-sincronizacao-de-planos.md) |

## Eventos

| Evento | Quando | Propriedades principais |
|---|---|---|
| `AcapaPayPaymentReceived` | Qualquer fatura paga | `invoiceId`, `subscriptionId` (nullable), `amount`, `currency`, `paymentMethod`, `metadata`, `isSubscription()`, `isOneOff()`, `isCrypto()` |
| `AcapaPayInvoicePaid` | Só pagamentos de subscrição | `subscriptionId` (string), `metadata`, `expiresAt`, `invoicePayload` |
| `AcapaPayInvoiceFailed` | Pagamento recusado | `invoiceId`, `reason`, `metadata`, `webhookPayload` |
| `AcapaPayInvoiceExpired` | Fatura expirou | `invoiceId`, `metadata`, `webhookPayload` |

Ver [05-webhooks-e-eventos.md](05-webhooks-e-eventos.md).

## Exceções

| Classe | Significado |
|---|---|
| `AcapaPayException` | Base de todas |
| `AuthenticationException` | OAuth2 falhou |
| `ConnectionException` | Rede/DNS/SSL |
| `ApiException` | Erro HTTP genérico (`status()`, `apiError()`, `isRetryable()`) |
| `ValidationException` | 422 (`errors()`) |
| `NotFoundException` | 404 |
| `ForbiddenException` | 403 |

Ver [06-tratamento-de-erros.md](06-tratamento-de-erros.md).

## Enums

| Classe | Constantes |
|---|---|
| `PaymentMethod` | `REF`, `GPO`, `EKZ`, `RDP` — + `isValid()`, `requiresPhoneNumber()`, `requiresUsd()`, `label()` |
| `Currency` | `AOA`, `USD` — + `isValid()` |

## Rota registada automaticamente

| Método | URI | Nome | Middleware |
|---|---|---|---|
| `POST` | `/webhooks/acapapay` | `acapapay.webhook` | Sem CSRF (excluído automaticamente) |

## Componente Blade

`<x-acapapay::iframe :checkout-url="$url" height="700px" width="100%" id="opcional" />` — ver [02-checkout-hospedado.md](02-checkout-hospedado.md).

## Todas as chaves de `config/acapapay.php`

Ver a tabela completa em [01-instalacao-e-configuracao.md](01-instalacao-e-configuracao.md#todas-as-chaves-de-configacapapayphp).
