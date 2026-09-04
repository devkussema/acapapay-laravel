<?php

namespace AcapaPay\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando uma fatura do AcapaPay falha no pagamento.
 * Pode ocorrer quando a transação é rejeitada pela gateway (ex: RedotPay, Pay4All).
 */
class AcapaPayInvoiceFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * O ID da fatura que falhou.
     */
    public ?string $invoiceId;

    /**
     * A razão da falha (ex: 'payment_rejected', 'insufficient_funds').
     */
    public string $reason;

    /**
     * Os metadados que a App enviou durante o Checkout.
     */
    public array $metadata;

    /**
     * Payload completo original do webhook.
     */
    public array $webhookPayload;

    /**
     * Cria uma nova instância do evento.
     */
    public function __construct(?string $invoiceId, string $reason, array $metadata, array $webhookPayload = [])
    {
        $this->invoiceId = $invoiceId;
        $this->reason = $reason;
        $this->metadata = $metadata;
        $this->webhookPayload = $webhookPayload;
    }
}
