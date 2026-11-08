<?php

namespace AcapaPay\Laravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando uma fatura do AcapaPay é estornada/reembolsada.
 * Suportado para pagamentos via Multicaixa Express (GPO) e simulações de sandbox.
 *
 * @since 1.5.0
 */
class AcapaPayInvoiceRefunded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * O ID da fatura reembolsada.
     */
    public ?string $invoiceId;

    /**
     * O montante reembolsado.
     */
    public ?float $amount;

    /**
     * O motivo do reembolso.
     */
    public ?string $reason;

    /**
     * Os metadados originais da fatura.
     */
    public array $metadata;

    /**
     * Payload completo do webhook recebido.
     */
    public array $webhookPayload;

    /**
     * Cria uma nova instância do evento.
     */
    public function __construct(
        ?string $invoiceId,
        ?float $amount,
        ?string $reason,
        array $metadata = [],
        array $webhookPayload = []
    ) {
        $this->invoiceId = $invoiceId;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->metadata = $metadata;
        $this->webhookPayload = $webhookPayload;
    }
}
