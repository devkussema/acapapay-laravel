# 2. API de Pagamento Direta (WiPay + RedotPay)

> **Esta é a documentação v2 do pacote** — cobre só **WiPay (WIP)** e **RedotPay (RDP)**. Se a tua integração ainda usa REF/GPO/EKZ, consulta a [documentação v1](../03-pagamento-direto-api.md) (e também [04-pagamentos-cripto-redotpay.md](../04-pagamentos-cripto-redotpay.md) para o detalhe completo do RDP, incluindo os dois fluxos de QR code).
>
> Faz parte da documentação v2 do [`devkussema/acapapay-laravel`](../../README.md). Ver o [índice v2](../../README.md#-documentação-v2-recomendado--wipay--redotpay).

Tal como no [checkout hospedado](01-checkout-hospedado.md), a API direta (`AcapaPay::direct()`) trata WIP e RDP de forma muito parecida do ponto de vista do payload: ambos devolvem `data.pay_url`, nenhum dos dois tem referência/ticket de texto (ao contrário de REF/GPO/EKZ), e nenhum tem QR code próprio do SDK — no RDP o QR vem de `paymentMethods()`/`qrCodeUrls()` (carteiras cripto), no WIP não existe QR nenhum.

## Regras importantes

| | `PaymentMethod::WIP` | `PaymentMethod::RDP` |
|---|---|---|
| Moeda da fatura | **AOA** apenas | **USD** apenas (422 se a fatura for AOA) |
| `phone_number` em `charge()` | Opcional — a WiPay aceita telefone **ou** outra identificação do cliente | Não aplicável |
| Validade da cobrança | — (definido pela WiPay) | 1 hora |
| Consulta de estado (`status()`) | **Não disponível** — só webhook | Sim |
| QR code / carteiras | Não | Sim (`paymentMethods()`, `qrCodeUrls()`, `appUrl()`) |

> [!WARNING]
> **`status()`/`isPaid()` não são fiáveis para WIP.** O servidor não tem, do lado da WiPay, um endpoint de consulta de estado — a fatura pode continuar a aparecer como `pending` indefinidamente até o webhook `invoice.paid` chegar. Não construas um ecrã de "a aguardar pagamento" que dependa só de `direct()->isPaid()` para o método WIP; usa o webhook como único sinal de confirmação (ver [03-webhooks-e-eventos.md](03-webhooks-e-eventos.md)).

## Fluxo

```
createInvoice()  →  charge()  →  [WIP: só webhook]  /  [RDP: status() + webhook]
```

## Exemplo completo — WiPay

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\Currency;
use AcapaPay\Laravel\Enums\PaymentMethod;

// 1. Criar a fatura em AOA
$invoice = AcapaPay::direct()->createInvoice([
    'customer_name'  => $user->name,
    'customer_email' => $user->email,
    'currency'       => Currency::AOA,
    'app_reference'  => "pedido-{$order->id}",
    'items'          => [
        ['description' => 'Plano PRO (mensal)', 'quantity' => 1, 'unit_price' => 5000],
    ],
]);

// 2. Gerar a cobrança WiPay (phone_number é opcional)
$charge = AcapaPay::direct()->charge($invoice['invoice_id'], PaymentMethod::WIP, '923000000');
// ou, com o atalho:
$charge = AcapaPay::direct()->chargeWithWipay($invoice['invoice_id'], '923000000');

// 3. Enviar o cliente para o checkout hospedado da WiPay (ou embutir num iFrame)
return redirect($charge->payUrl());
```

## Exemplo completo — RedotPay

```php
// 1. Criar a fatura em USD
$invoice = AcapaPay::direct()->createInvoice([
    'customer_name' => $user->name,
    'currency'      => Currency::USD,
    'app_reference' => "pedido-{$order->id}",
    'items'         => [
        ['description' => 'Plano PRO (anual)', 'quantity' => 1, 'unit_price' => 120.00],
    ],
]);

// 2. Gerar a cobrança RedotPay
$charge = AcapaPay::direct()->charge($invoice['invoice_id'], PaymentMethod::RDP);
// ou: $charge = AcapaPay::direct()->chargeWithCrypto($invoice['invoice_id']);

return redirect($charge->payUrl());
```

> Para o fluxo alternativo de QR code embutido (sem redirecionar) específico do RDP, ver [04-pagamentos-cripto-redotpay.md](../04-pagamentos-cripto-redotpay.md) na documentação v1 — esse conteúdo não muda entre v1 e v2.

## Verificar o estado — só para RDP

```php
$status = AcapaPay::direct()->status($invoiceId);
// ['invoice_status' => 'pending'|'paid', 'paid_at' => ..., 'transaction' => [...]]

if (AcapaPay::direct()->isPaid($invoiceId)) {
    // ...
}
```

Para **WIP**, não chames `status()`/`isPaid()` como fonte de verdade — depende do webhook.

## Testar sem dinheiro real (sandbox)

```php
AcapaPay::direct()->simulate($invoiceId);
```

Funciona da mesma forma para WIP e RDP: marca a fatura como paga instantaneamente e dispara o `invoice.paid`, sem tocar no gateway real.

## Referência dos métodos relevantes

### `AcapaPay::direct()`

| Método | Devolve | Descrição |
|---|---|---|
| `createInvoice(array $attributes)` | `array` | Cria a fatura. Devolve `{status, invoice_id, pay_url, total, currency}`. |
| `charge(string $invoiceId, string $method, ?string $phone = null)` | `ChargeResult` | `$method` = `PaymentMethod::WIP` ou `PaymentMethod::RDP`. `$phone` é opcional em ambos (ignorado em RDP, opcional em WIP). |
| `chargeWithWipay(string $invoiceId, ?string $phone = null)` | `ChargeResult` | Atalho para `charge($invoiceId, PaymentMethod::WIP, $phone)`. *(desde 1.4.0)* |
| `chargeWithCrypto(string $invoiceId)` | `ChargeResult` | Atalho para `charge($invoiceId, PaymentMethod::RDP)`. |
| `status(string $invoiceId)` | `array` | Só fiável para RDP (e REF/GPO/EKZ). **Não usar como confirmação para WIP.** |
| `isPaid(string $invoiceId)` | `bool` | Idem. |
| `find(string $invoiceId)` | `array` | Detalhe completo da fatura. |
| `simulate(string $invoiceId)` | `array` | Só em sandbox. |

### `ChargeResult`

| Método | Devolve | Aplica-se a |
|---|---|---|
| `payUrl()` | `?string` | **WIP** e **RDP** — é o campo usado por ambos. |
| `paymentMethod()` | `?string` | Todos — `'WIP'` ou `'RDP'` neste contexto. |
| `expiresAt()` | `?string` | RDP (1h). Em WIP normalmente `null` — a validade é gerida pela WiPay. |
| `isMock()` | `bool` | `true` se simulado em sandbox. |
| `paymentMethods()` / `qrCodeUrls()` / `appUrl()` | `array` / `array` / `?string` | Só RDP — carteiras cripto. Em WIP devolvem vazio/`null`. |
| `data()` | `array` | Payload bruto do gateway. |

---

**A seguir:** [03-webhooks-e-eventos.md](03-webhooks-e-eventos.md) — a única confirmação fiável de um pagamento WIP.
