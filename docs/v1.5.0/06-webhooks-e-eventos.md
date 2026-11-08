# AcapaPay Laravel SDK — Webhooks e Eventos

* **Versão:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026

---

## 1. Como Funcionam os Webhooks

O AcapaPay utiliza Webhooks para notificar a sua aplicação em tempo real sobre alterações de estado de faturas e pagamentos (liquidação, estornos, recusas ou expirações).

O SDK inclui uma rota pronta e protegida em:
```
POST /acapapay/webhook
```
Esta rota valida a assinatura HMAC-SHA256 (`X-AcapaDev-Signature`) utilizando a chave `ACAPAPAY_WEBHOOK_SECRET` configurada no `.env`. Se a assinatura for válida, despacha o evento correspondente para o event bus do Laravel.

---

## 2. Configurar o Webhook no Portal do Desenvolvedor

1. Aceda ao ecrã da sua App no Portal do Desenvolvedor (`https://id.acapadev.me/apps/{id}`).
2. Na secção **Webhooks**, preencha o campo **URL do Webhook de Faturação**:
   ```
   https://sua-app.com/acapapay/webhook
   ```
3. Copie o **Segredo de Webhook** gerado e cole-o no seu ficheiro `.env`:
   ```env
   ACAPAPAY_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxxxxx
   ```

> [!WARNING]
> Certifique-se de que a rota `/acapapay/webhook` está excluída da verificação de CSRF no seu ficheiro `bootstrap/app.php` (Laravel 11+) ou em `app/Http/Middleware/VerifyCsrfToken.php` (Laravel 9/10).

---

## 3. Catálogo de Eventos do SDK

| Evento Laravel | Evento Webhook | Descrição |
| :--- | :--- | :--- |
| `AcapaPayPaymentReceived` | `invoice.paid` | Disparado para **todo e qualquer pagamento liquidado** (com ou sem subscrição de plano, AOA ou USD). |
| `AcapaPayInvoicePaid` | `invoice.paid` | Disparado apenas quando a fatura paga possui uma **subscrição recorrente** associada. |
| `AcapaPayInvoiceRefunded` | `invoice.refunded` | **Novo na v1.5.0:** Disparado quando ocorre um **reembolso/estorno** total ou parcial. |
| `AcapaPayInvoiceFailed` | `invoice.failed` | Disparado quando a cobrança é recusada ou rejeitada pelo banco/gateway. |
| `AcapaPayInvoiceExpired` | `invoice.expired` | Disparado quando a fatura expira sem pagamento. |

---

## 4. Exemplos Práticos de Event Listeners

### 4.1. Tratar Pagamentos Recebidos (`AcapaPayPaymentReceived`)

Crie o listener com o Artisan:
```bash
php artisan make:listener HandlePaymentReceived
```

Implementação:
```php
namespace App\Listeners;

use AcapaPay\Laravel\Events\AcapaPayPaymentReceived;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class HandlePaymentReceived
{
    public function handle(AcapaPayPaymentReceived $event): void
    {
        $invoiceId = $event->invoiceId;
        $orderId = $event->metadata['order_id'] ?? null;
        $amountPaid = $event->total;
        $paymentMethod = $event->paymentMethod; // 'GPO', 'REF', 'RDP', etc.

        Log::info("Pagamento recebido para fatura {$invoiceId}", [
            'order_id' => $orderId,
            'method' => $paymentMethod,
            'total' => $amountPaid,
        ]);

        if ($orderId) {
            $order = Order::find($orderId);
            if ($order && $order->status !== 'paid') {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'acapapay_invoice_id' => $invoiceId,
                ]);

                // Despachar email de confirmação ou entrega de produto digital
            }
        }
    }
}
```

---

### 4.2. Tratar Reembolsos (`AcapaPayInvoiceRefunded` — Novo na v1.5.0)

Crie o listener:
```bash
php artisan make:listener HandleInvoiceRefunded
```

Implementação:
```php
namespace App\Listeners;

use AcapaPay\Laravel\Events\AcapaPayInvoiceRefunded;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class HandleInvoiceRefunded
{
    public function handle(AcapaPayInvoiceRefunded $event): void
    {
        $invoiceId = $event->invoiceId;
        $refundAmount = $event->amount;
        $reason = $event->reason;
        $orderId = $event->metadata['order_id'] ?? null;

        Log::info("Reembolso confirmado pelo AcapaPay: {$refundAmount} Kz", [
            'invoice_id' => $invoiceId,
            'reason' => $reason,
        ]);

        if ($orderId) {
            $order = Order::find($orderId);
            if ($order) {
                $order->update([
                    'status' => 'refunded',
                    'refunded_amount' => $refundAmount,
                    'refund_reason' => $reason,
                ]);

                // Notificar cliente sobre a devolução efetuada
            }
        }
    }
}
```

---

### 4.3. Registar Listeners no `EventServiceProvider`

Registe os listeners no seu `EventServiceProvider` (ou no `AppServiceProvider` no Laravel 11+):

```php
use AcapaPay\Laravel\Events\AcapaPayPaymentReceived;
use AcapaPay\Laravel\Events\AcapaPayInvoiceRefunded;
use App\Listeners\HandlePaymentReceived;
use App\Listeners\HandleInvoiceRefunded;

protected $listen = [
    AcapaPayPaymentReceived::class => [
        HandlePaymentReceived::class,
    ],
    AcapaPayInvoiceRefunded::class => [
        HandleInvoiceRefunded::class,
    ],
];
```
