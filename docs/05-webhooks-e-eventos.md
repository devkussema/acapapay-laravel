# 5. Webhooks e Eventos

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).

Quando uma fatura é paga (ou falha, ou expira), o servidor central envia um *webhook* `POST` para a tua aplicação. O pacote regista automaticamente uma rota (`/webhooks/acapapay`) que valida a assinatura e dispara **eventos nativos do Laravel** — tu só precisas de escutar esses eventos.

## Como funciona internamente

1. O pacote regista `POST /webhooks/acapapay` (nome da rota: `acapapay.webhook`), já **excluída da proteção CSRF** (obrigatório para chamadas server-to-server).
2. Ao receber um pedido, valida a assinatura **HMAC-SHA256** no header `X-AcapaDev-Signature`, calculada sobre o corpo cru do pedido com o teu `ACAPAPAY_WEBHOOK_SECRET`. Sem segredo configurado, ou com assinatura inválida, devolve `401`.
3. Lê o campo `event` do payload e despacha o evento Laravel correspondente.

```php
// Lógica interna do SDK (não precisas de escrever isto)
$expectedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);

if (!hash_equals($expectedSignature, $receivedSignature)) {
    return response()->json(['error' => 'Assinatura Invalida'], 401);
}
```

Garante que o URL `https://teudominio.com/webhooks/acapapay` está configurado no painel de **Developer Apps** do SSO Acapadev, na secção de Webhooks da tua App.

## Tabela de eventos disponíveis

| Evento | Quando é disparado |
|---|---|
| `AcapaPayPaymentReceived` | **Qualquer** fatura paga — com ou sem subscrição associada. *(desde a v1.2.0)* |
| `AcapaPayInvoicePaid` | Apenas pagamentos **de subscrição** (existe desde a v1.0.0). |
| `AcapaPayInvoiceFailed` | Pagamento recusado pela gateway. |
| `AcapaPayInvoiceExpired` | Fatura expirou sem pagamento. |

### Qual devo usar — `AcapaPayInvoicePaid` ou `AcapaPayPaymentReceived`?

- **Só vendes subscrições?** Continua a usar `AcapaPayInvoicePaid`. Nada mudou — nenhuma alteração de comportamento para quem já integrou.
- **Aceitas pagamentos avulsos** (ex: `createInvoice()`, ou qualquer cobrança feita pela [API direta](03-pagamento-direto-api.md), incluindo qualquer pagamento em [USD/cripto via RedotPay](04-pagamentos-cripto-redotpay.md))? Usa `AcapaPayPaymentReceived` — pagamentos avulsos não têm subscrição e por isso **nunca** disparam o `AcapaPayInvoicePaid`.

> [!IMPORTANT]
> Se adotares o `AcapaPayPaymentReceived` **e** mantiveres um listener no `AcapaPayInvoicePaid`, um pagamento de subscrição vai acionar **os dois** eventos (de propósito — para não quebrar quem já escuta o antigo). Usa `$event->isOneOff()` no listener novo para tratar apenas os pagamentos avulsos e evitar processamento duplicado.

## `AcapaPayInvoicePaid` (subscrições)

```php
use AcapaPay\Laravel\Events\AcapaPayInvoicePaid;

protected $listen = [
    AcapaPayInvoicePaid::class => [
        \App\Listeners\MarcarFaturaComoPaga::class,
    ],
];
```

```php
public function handle(AcapaPayInvoicePaid $event)
{
    // Propriedades disponíveis:
    $subscriptionId = $event->subscriptionId;   // ID da subscrição no AcapaPay (string, nunca null)
    $metadata       = $event->metadata;          // Os metadados que enviaste no checkout
    $expiresAt      = $event->expiresAt;         // Validade da subscrição (ISO 8601)
    $payload        = $event->invoicePayload;    // Payload completo do webhook

    $localUserId = $metadata['local_user_id'] ?? null;

    // Protege contra reprocessamento (o SSO pode reenviar o mesmo webhook):
    if (Subscription::where('acapapay_id', $subscriptionId)->exists()) {
        return;
    }

    // Order::where('user_id', $localUserId)->update(['status' => 'paid']);
}
```

## `AcapaPayPaymentReceived` (qualquer pagamento)

```php
use AcapaPay\Laravel\Events\AcapaPayPaymentReceived;

public function handle(AcapaPayPaymentReceived $event)
{
    if ($event->isSubscription()) {
        return; // já tratado pelo listener do AcapaPayInvoicePaid, se tiveres um
    }

    $event->invoiceId;     // ID da fatura
    $event->amount;        // Total pago
    $event->currency;      // 'AOA' ou 'USD'
    $event->paymentMethod; // 'REF' | 'GPO' | 'EKZ' | 'RDP'
    $event->metadata;      // Os teus metadados
    $event->isCrypto();    // true se foi pago em criptomoeda (RDP)

    Order::where('id', $event->metadata['order_id'] ?? null)->update(['status' => 'paid']);
}
```

## `AcapaPayInvoiceFailed`

```php
use AcapaPay\Laravel\Events\AcapaPayInvoiceFailed;

protected $listen = [
    AcapaPayInvoiceFailed::class => [
        \App\Listeners\NotificarFalhaPagamento::class,
    ],
];
```

```php
public function handle(AcapaPayInvoiceFailed $event)
{
    $event->invoiceId; // ID da fatura
    $event->reason;    // Razão da falha
    $event->metadata;  // Metadados originais

    // Notificar o utilizador, reverter ações, etc.
}
```

## `AcapaPayInvoiceExpired`

Mesma forma do `AcapaPayInvoiceFailed`, mas sem `reason` — propriedades `invoiceId`, `metadata`, `webhookPayload`.

## Idempotência

O servidor pode, em situações excecionais (timeouts, retentativas), reenviar o mesmo webhook mais do que uma vez. Os teus listeners **devem ser idempotentes** — produzir o mesmo resultado independentemente de quantas vezes forem chamados. Ver o exemplo com `Subscription::where(...)->exists()` acima.

---

**A seguir:** [06-tratamento-de-erros.md](06-tratamento-de-erros.md) — a hierarquia de exceções do SDK.
