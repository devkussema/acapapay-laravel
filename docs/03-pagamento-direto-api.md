# 3. API de Pagamento Direta (checkout próprio, sem iFrame)

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).

O [checkout hospedado](02-checkout-hospedado.md) redireciona (ou embute num iFrame) o utilizador para uma página de pagamento pronta. Se preferires desenhar **a tua própria interface** — mostrando a referência Multicaixa, o ticket E-Kwanza ou o link/QR de criptomoeda dentro da tua própria app — usa a **API direta**, através de `AcapaPay::direct()`.

> Para o caso específico de criptomoeda (RedotPay), há uma decisão extra a tomar — redirecionar ou construir o teu próprio QR code — coberta em detalhe em [04-pagamentos-cripto-redotpay.md](04-pagamentos-cripto-redotpay.md). Este ficheiro cobre o fluxo geral, válido para todos os métodos (`REF`, `GPO`, `EKZ`, `RDP`).

## Fluxo

```
createInvoice()  →  charge()  →  status()  (polling)
                                     ↕
                          webhook invoice.paid (confirmação fiável)
```

1. Crias a fatura.
2. Geras a cobrança no método escolhido.
3. Mostras os dados devolvidos (referência, ticket, ou link/QR) na tua própria UI.
4. Fazes *polling* do estado até a fatura ficar paga — **mas a confirmação fiável e definitiva é sempre o [webhook](05-webhooks-e-eventos.md)**, nunca o polling isolado.

## Exemplo completo

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\Currency;
use AcapaPay\Laravel\Enums\PaymentMethod;

// 1. Criar a fatura
$invoice = AcapaPay::direct()->createInvoice([
    'customer_name'  => $user->name,
    'customer_email' => $user->email,
    'currency'       => Currency::AOA,          // ou Currency::USD
    'app_reference'  => "pedido-{$order->id}",
    'items'          => [
        ['description' => 'Plano PRO (anual)', 'quantity' => 1, 'unit_price' => 15000],
    ],
]);
// $invoice = ['status' => 'success', 'invoice_id' => '...', 'pay_url' => '...', 'total' => 15000, 'currency' => 'AOA']

// 2. Gerar a cobrança (ex: Referência Multicaixa)
$charge = AcapaPay::direct()->charge($invoice['invoice_id'], PaymentMethod::REF);

// 3. Mostrar ao utilizador na tua própria UI
return view('checkout', [
    'referencia' => $charge->reference(),
    'entidade'   => $charge->entity(),
    'expiresAt'  => $charge->expiresAt(),
    'invoiceId'  => $invoice['invoice_id'],
]);
```

## Verificar o estado (polling)

```php
$status = AcapaPay::direct()->status($invoiceId);
// ['invoice_status' => 'pending'|'paid', 'paid_at' => ..., 'transaction' => [...]]

if (AcapaPay::direct()->isPaid($invoiceId)) {
    // ...
}
```

> [!NOTE]
> O servidor só consulta a gateway externa **uma vez a cada 15 segundos** por fatura (configurável via `status_poll_interval`, ver [01-instalacao-e-configuracao.md](01-instalacao-e-configuracao.md)). Não vale a pena perguntar mais depressa — chamadas mais frequentes devolvem o último estado conhecido sem novo pedido externo. Usa sempre o **webhook** como confirmação definitiva; o polling serve apenas para dar feedback imediato na interface.

## Testar sem dinheiro real (sandbox)

Com `ACAPAPAY_MODO=sandbox` (ver [07-sandbox-e-testes.md](07-sandbox-e-testes.md)), podes marcar uma fatura como paga instantaneamente. Isto dispara o webhook `invoice.paid` tal como um pagamento real:

```php
AcapaPay::direct()->simulate($invoiceId);
```

## Referência completa dos métodos

### `AcapaPay::direct()` (ou o alias `AcapaPay::invoices()`)

| Método | Devolve | Descrição |
|---|---|---|
| `createInvoice(array $attributes)` | `array` | Cria a fatura. `attributes`: `customer_name` (obrigatório), `customer_email`, `customer_nif`, `currency`, `app_reference`, `due_date`, `items` (obrigatório, array de `{description, quantity, unit_price}`). Devolve `{status, invoice_id, pay_url, total, currency}`. |
| `charge(string $invoiceId, string $method, ?string $phone = null)` | `ChargeResult` | Gera a cobrança. `$method` é uma constante de `PaymentMethod`. `$phone` é obrigatório para `GPO`/`EKZ`. |
| `chargeWithCrypto(string $invoiceId)` | `ChargeResult` | Atalho para `charge($invoiceId, PaymentMethod::RDP)`. |
| `status(string $invoiceId)` | `array` | `{invoice_status, paid_at, transaction: {payment_method, status, gateway_reference, expires_at}}`. |
| `isPaid(string $invoiceId)` | `bool` | Atalho: `status($id)['invoice_status'] === 'paid'`. |
| `find(string $invoiceId)` | `array` | Detalhe completo da fatura. |
| `simulate(string $invoiceId)` | `array` | Marca como paga instantaneamente. Só funciona em sandbox — lança `ForbiddenException` fora dele. |

### `AcapaPay::createCryptoCharge(array $invoiceAttributes)`

Atalho de alto nível: cria uma fatura em USD e gera logo a cobrança em criptomoeda, num só passo. Equivale a `direct()->createInvoice(...)` seguido de `direct()->chargeWithCrypto(...)`.

```php
$charge = AcapaPay::createCryptoCharge([
    'customer_name' => $user->name,
    'items' => [['description' => 'Plano PRO', 'quantity' => 1, 'unit_price' => 120.00]],
]);

return redirect($charge->payUrl());
```

### `ChargeResult`

Objeto devolvido por `charge()`, `chargeWithCrypto()` e `createCryptoCharge()`:

| Método | Devolve | Notas |
|---|---|---|
| `payUrl()` | `?string` | Link da página de checkout (Fluxo 1 do RedotPay — ver [04-pagamentos-cripto-redotpay.md](04-pagamentos-cripto-redotpay.md)). |
| `reference()` | `?string` | Referência Multicaixa (método `REF`). |
| `entity()` | `?string` | Entidade Multicaixa (método `REF`). |
| `expiresAt()` | `?string` | Validade da cobrança, ISO 8601. Varia por método: `REF` ~3 dias, `GPO` 60s, `EKZ` 5 min, `RDP` 1h. |
| `paymentMethod()` | `?string` | O método usado (`'REF'`, `'GPO'`, `'EKZ'`, `'RDP'`). |
| `isMock()` | `bool` | `true` se a cobrança foi simulada em sandbox (não chamou o gateway real). |
| `paymentMethods()` | `array` | Só para `RDP` — carteiras de cripto suportadas. Ver [04-pagamentos-cripto-redotpay.md](04-pagamentos-cripto-redotpay.md). |
| `qrCodeUrls()` | `array` | Idem, simplificado. |
| `appUrl(string $walletId)` | `?string` | Idem, deep-link de uma carteira específica. |
| `data()` | `array` | Payload bruto devolvido pelo gateway — útil para depuração ou casos não cobertos pelos métodos acima. |

Também funciona como array simples, se preferires: `$charge['data']['pay_url']`.

## Erros

Ver [06-tratamento-de-erros.md](06-tratamento-de-erros.md) para a lista completa de exceções e como as tratar (`ValidationException`, `NotFoundException`, `ForbiddenException`, etc.).

---

**A seguir:** [04-pagamentos-cripto-redotpay.md](04-pagamentos-cripto-redotpay.md) — pagamentos em USD/criptomoeda via RedotPay.
