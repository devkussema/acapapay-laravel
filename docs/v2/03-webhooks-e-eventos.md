# 3. Webhooks e Eventos (WiPay + RedotPay)

> **Esta é a documentação v2 do pacote** — cobre só **WiPay (WIP)** e **RedotPay (RDP)**. Se a tua integração ainda usa REF/GPO/EKZ, consulta a [documentação v1](../05-webhooks-e-eventos.md).
>
> Faz parte da documentação v2 do [`devkussema/acapapay-laravel`](../../README.md). Ver o [índice v2](../../README.md#-documentação-v2-recomendado--wipay--redotpay).

O mecanismo de webhook é exactamente o mesmo para todos os métodos de pagamento — nada muda no transporte, na assinatura, ou no registo da rota. O que muda é **quão importante** o webhook é para cada método.

## Por que o webhook é obrigatório para WIP

- **REF/GPO/EKZ/RDP** têm um endpoint de consulta de estado (`direct()->status($invoiceId)`) que podes usar para *polling*, com o webhook como confirmação definitiva.
- **WIP não tem endpoint de consulta de estado nenhum.** A WiPay não expõe uma API de "consultar estado desta cobrança" — a única forma de saber que um pagamento WIP foi concluído é o callback assinado que a WiPay envia ao AcapaDev ID, que por sua vez reencaminha para o teu webhook `invoice.paid`.

Isto significa que, se a tua app usa WIP, **tens de ter um listener funcional** em `AcapaPayPaymentReceived` (ou `AcapaPayInvoicePaid`, se for uma subscrição) — não há alternativa de *fallback* por polling.

## Como funciona (sem alterações face à v1)

1. O pacote regista `POST /webhooks/acapapay` (rota `acapapay.webhook`), excluída de CSRF.
2. Valida a assinatura **HMAC-SHA256** no header `X-AcapaDev-Signature`, com o teu `ACAPAPAY_WEBHOOK_SECRET`.
3. Despacha o evento Laravel correspondente ao campo `event` do payload.

Garante que `https://teudominio.com/webhooks/acapapay` está configurado no painel de **Developer Apps** do SSO Acapadev.

## Eventos relevantes

| Evento | Quando é disparado |
|---|---|
| `AcapaPayPaymentReceived` | Qualquer fatura paga — com ou sem subscrição, **incluindo WIP e RDP**. |
| `AcapaPayInvoicePaid` | Apenas pagamentos de subscrição (qualquer método, incluindo WIP/RDP). |
| `AcapaPayInvoiceFailed` | Pagamento recusado — para WIP, é o único outro sinal possível além de "nunca chegou nada". |
| `AcapaPayInvoiceExpired` | Fatura expirou sem pagamento. |

## Exemplo — listener genérico que distingue WIP/RDP

```php
use AcapaPay\Laravel\Events\AcapaPayPaymentReceived;

public function handle(AcapaPayPaymentReceived $event)
{
    if ($event->isSubscription()) {
        return; // já tratado por um listener de AcapaPayInvoicePaid, se existir
    }

    $event->invoiceId;
    $event->amount;
    $event->currency;      // 'AOA' para WIP, 'USD' para RDP
    $event->paymentMethod; // 'WIP' ou 'RDP'
    $event->metadata;

    if ($event->isWipay()) {
        // Confirmação via WiPay — este evento É a única fonte de verdade.
    }

    if ($event->isCrypto()) {
        // Confirmação via RedotPay.
    }

    Order::where('id', $event->metadata['order_id'] ?? null)->update(['status' => 'paid']);
}
```

`AcapaPayPaymentReceived::isWipay(): bool` foi acrescentado na v1.4.0, a par de `isCrypto()` (que já existia para RDP) — devolve `true` quando `$event->paymentMethod === 'WIP'`.

## Idempotência

Tal como em qualquer outro método, o servidor pode reenviar o mesmo webhook mais do que uma vez (timeouts, retentativas). Para WIP isto é ainda mais importante de tratar bem, já que não há um `status()` de apoio para confirmar se já processaste aquele pagamento — confia na chave única da tua própria tabela (`invoice_id`, ou o teu `app_reference`/`metadata`) para rejeitar duplicados:

```php
if (Order::where('acapapay_invoice_id', $event->invoiceId)->where('status', 'paid')->exists()) {
    return;
}
```

---

**A seguir:** [04-referencia-rapida.md](04-referencia-rapida.md) — resumo compacto de tudo o que é específico de WiPay/RedotPay.
