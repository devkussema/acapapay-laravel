<?php

namespace AcapaPay\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado sempre que uma fatura é paga — com ou sem subscrição associada.
 *
 * Ao contrário do AcapaPayInvoicePaid (que existe desde a v1.0 e representa
 * especificamente "uma subscrição foi paga"), este evento cobre também os
 * pagamentos avulsos: faturas criadas com AcapaPay::createInvoice() ou pagas
 * em USD/criptomoeda via RedotPay, que não têm subscrição nenhuma.
 *
 * Usa este evento em vez do AcapaPayInvoicePaid se a tua app aceita pagamentos
 * avulsos. Se a tua app só vende subscrições, o AcapaPayInvoicePaid continua a
 * funcionar exatamente como sempre funcionou.
 *
 * @since 1.2.0
 */
class AcapaPayPaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * O ID da fatura no AcapaPay.
     */
    public ?string $invoiceId;

    /**
     * O ID da subscrição, quando o pagamento diz respeito a uma.
     * É null em pagamentos avulsos.
     */
    public ?string $subscriptionId;

    /**
     * Os metadados que a tua App enviou durante o checkout.
     */
    public array $metadata;

    /**
     * Data de expiração da subscrição (null em pagamentos avulsos).
     */
    public ?string $expiresAt;

    /**
     * Método de pagamento usado: 'REF', 'GPO', 'EKZ' ou 'RDP' (RedotPay/cripto).
     * Pode ser null se o servidor não o enviar.
     *
     * @see \AcapaPay\Laravel\Enums\PaymentMethod
     */
    public ?string $paymentMethod;

    /**
     * Moeda da fatura ('AOA' ou 'USD').
     */
    public ?string $currency;

    /**
     * Total pago.
     */
    public float|int|string|null $amount;

    /**
     * Payload completo do webhook, para auditoria.
     */
    public array $webhookPayload;

    public function __construct(
        ?string $invoiceId,
        ?string $subscriptionId,
        array $metadata,
        ?string $expiresAt = null,
        ?string $paymentMethod = null,
        ?string $currency = null,
        float|int|string|null $amount = null,
        array $webhookPayload = []
    ) {
        $this->invoiceId = $invoiceId;
        $this->subscriptionId = $subscriptionId;
        $this->metadata = $metadata;
        $this->expiresAt = $expiresAt;
        $this->paymentMethod = $paymentMethod;
        $this->currency = $currency;
        $this->amount = $amount;
        $this->webhookPayload = $webhookPayload;
    }

    /**
     * O pagamento diz respeito a uma subscrição?
     */
    public function isSubscription(): bool
    {
        return $this->subscriptionId !== null;
    }

    /**
     * O pagamento é avulso (sem subscrição)?
     */
    public function isOneOff(): bool
    {
        return $this->subscriptionId === null;
    }

    /**
     * O pagamento foi feito em criptomoeda através da RedotPay?
     */
    public function isCrypto(): bool
    {
        return $this->paymentMethod === 'RDP';
    }
}
