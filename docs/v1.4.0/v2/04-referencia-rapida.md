# 4. Referência Rápida (WiPay + RedotPay)

> **Esta é a documentação v2 do pacote** — cobre só **WiPay (WIP)** e **RedotPay (RDP)**. Se a tua integração ainda usa REF/GPO/EKZ, consulta a [documentação v1](../09-referencia-rapida.md), que cobre todos os métodos.
>
> Faz parte da documentação v2 do [`devkussema/acapapay-laravel`](../../README.md). Ver o [índice v2](../../README.md#-documentação-v2-recomendado--wipay--redotpay).
>
> Esta página é um resumo compacto — útil para consulta rápida ou para dar contexto a uma ferramenta/agente de IA. Para explicações e exemplos, ver [01](01-checkout-hospedado.md), [02](02-pagamento-direto-api.md) e [03](03-webhooks-e-eventos.md).

## Tabela comparativa

| | `PaymentMethod::WIP` | `PaymentMethod::RDP` |
|---|---|---|
| Nome | WiPay | RedotPay |
| Moeda | AOA apenas | USD apenas |
| `requiresPhoneNumber()` | `false` (opcional) | `false` |
| `requiresUsd()` | `false` | `true` |
| `pay_url` na resposta | Sim | Sim |
| QR code | Não | Sim (`paymentMethods()`) |
| `status()`/`isPaid()` fiável | **Não** — só webhook | Sim |
| Atalho em `direct()` | `chargeWithWipay($invoiceId, $phone = null)` *(1.4.0+)* | `chargeWithCrypto($invoiceId)` |
| `AcapaPayPaymentReceived` helper | `isWipay()` *(1.4.0+)* | `isCrypto()` |

## Contrato HTTP (referência, já implementado no servidor)

```
POST /v1/billing/invoices/{id}/charges
{"payment_method": "WIP", "phone_number": "923000000"}   // phone_number opcional
```

```
201
{
  "status": "success",
  "payment_method": "WIP",
  "is_mock": false,
  "expires_at": null,
  "data": { "pay_url": "https://hosted.wipay.ao/..." }
}
```

## `AcapaPay::direct()` — métodos relevantes a WIP/RDP

| Método | Retorno | Descrição |
|---|---|---|
| `createInvoice(array $attributes)` | `array` | `{status, invoice_id, pay_url, total, currency}` |
| `charge($invoiceId, PaymentMethod::WIP, $phone = null)` | `ChargeResult` | Gera cobrança WiPay |
| `charge($invoiceId, PaymentMethod::RDP)` | `ChargeResult` | Gera cobrança RedotPay |
| `chargeWithWipay($invoiceId, $phone = null)` | `ChargeResult` | Atalho WiPay *(1.4.0+)* |
| `chargeWithCrypto($invoiceId)` | `ChargeResult` | Atalho RedotPay |
| `status($invoiceId)` | `array` | Fiável só para RDP |
| `isPaid($invoiceId)` | `bool` | Fiável só para RDP |
| `simulate($invoiceId)` | `array` | Sandbox — funciona para ambos |

Ver [02-pagamento-direto-api.md](02-pagamento-direto-api.md).

## `ChargeResult`

| Método | Retorno | Notas |
|---|---|---|
| `payUrl()` | `?string` | WIP e RDP |
| `paymentMethod()` | `?string` | `'WIP'` ou `'RDP'` |
| `expiresAt()` | `?string` | `null` em WIP; ~1h em RDP |
| `isMock()` | `bool` | Sandbox |
| `paymentMethods()` / `qrCodeUrls()` / `appUrl()` | — | Só RDP; vazio em WIP |

## Eventos

| Evento | Propriedades relevantes |
|---|---|
| `AcapaPayPaymentReceived` | `paymentMethod` (`'WIP'`\|`'RDP'`), `isWipay()` *(1.4.0+)*, `isCrypto()` |
| `AcapaPayInvoicePaid` | Igual à v1 — não muda com o método |

Ver [03-webhooks-e-eventos.md](03-webhooks-e-eventos.md).

## Enum `PaymentMethod` — constantes relevantes

```php
PaymentMethod::WIP; // 'WIP'
PaymentMethod::RDP; // 'RDP'

PaymentMethod::label(PaymentMethod::WIP); // 'WiPay'
PaymentMethod::requiresPhoneNumber(PaymentMethod::WIP); // false
PaymentMethod::requiresUsd(PaymentMethod::WIP); // false
```

## Config relevante (`config/acapapay.php`)

```env
ACAPAPAY_PREFERRED_CURRENCY=AOA   # ou USD, para RDP
ACAPAPAY_PREFERRED_METHOD=WIP     # ou RDP
```

---

Ver também a [referência rápida v1](../09-referencia-rapida.md) para todos os métodos (REF, GPO, EKZ, RDP, WIP), exceções, comandos Artisan e todas as chaves de configuração.
