<?php

namespace AcapaPay\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando uma fatura do AcapaPay expira sem receber pagamento.
 * Ocorre quando o prazo de validade da cobrança é ultrapassado.
 */
class AcapaPayInvoiceExpired
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * O ID da fatura que expirou.
     */
    public ?string $invoiceId;

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
    public function __construct(?string $invoiceId, array $metadata, array $webhookPayload = [])
    {
        $this->invoiceId = $invoiceId;
        $this->metadata = $metadata;
        $this->webhookPayload = $webhookPayload;
    }
}
