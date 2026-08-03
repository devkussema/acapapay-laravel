<?php

namespace AcapaPay\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AcapaPayInvoicePaid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * O ID da subscrição gerado pelo AcapaPay.
     */
    public string $subscriptionId;

    /**
     * Os metadados que a tua App enviou durante o Checkout.
     */
    public array $metadata;

    /**
     * A data de validade/expiração do serviço adquirido.
     */
    public ?string $expiresAt;

    /**
     * Payload completo original da fatura.
     */
    public array $invoicePayload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $subscriptionId, array $metadata, ?string $expiresAt, array $invoicePayload = [])
    {
        $this->subscriptionId = $subscriptionId;
        $this->metadata = $metadata;
        $this->expiresAt = $expiresAt;
        $this->invoicePayload = $invoicePayload;
    }
}
