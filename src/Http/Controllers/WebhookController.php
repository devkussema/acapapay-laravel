<?php

namespace AcapaPay\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use AcapaPay\Laravel\Events\AcapaPayInvoicePaid;
use AcapaPay\Laravel\Events\AcapaPayInvoiceFailed;
use AcapaPay\Laravel\Events\AcapaPayInvoiceExpired;
use AcapaPay\Laravel\Events\AcapaPayPaymentReceived;

class WebhookController extends Controller
{
    /**
     * Processa webhooks recebidos do SSO AcapaPay.
     * Valida a assinatura HMAC-SHA256 e despacha o evento Laravel correspondente.
     */
    public function handle(Request $request)
    {
        // 1. Extração de Segurança
        $signature = $request->header('X-AcapaDev-Signature');
        $secret    = config('acapapay.webhook_secret');
        $payload   = $request->getContent(); 
        
        // 2. Validação HMAC (Evitar ataques forjados)
        $expectedSignature = hash_hmac('sha256', $payload, $secret ?? '');
        
        if (!$secret || !hash_equals((string)$expectedSignature, (string)$signature)) {
            Log::warning('AcapaPay SDK: Assinatura HMAC de Webhook Acapadev rejeitada.');
            return response()->json(['error' => 'Assinatura Invalida'], 401);
        }

        // 3. Processar Evento
        $data = $request->json()->all();
        $eventName = $data['event'] ?? 'unknown';

        try {
            match ($eventName) {
                'invoice.paid'    => $this->handleInvoicePaid($data),
                'invoice.failed'  => $this->handleInvoiceFailed($data),
                'invoice.expired' => $this->handleInvoiceExpired($data),
                default           => Log::info("AcapaPay SDK: Evento desconhecido ignorado: {$eventName}"),
            };

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("AcapaPay SDK Webhook {$eventName} erro: " . $e->getMessage());
            return response()->json(['error' => 'Erro a processar evento interno SDK'], 500);
        }
    }

    /**
     * Processa o evento de fatura paga com sucesso.
     */
    private function handleInvoicePaid(array $data): void
    {
        $payload = $data['data'] ?? [];
        $metadata = $payload['metadata'] ?? [];
        $subscriptionData = $payload['subscription'] ?? null;

        $subscriptionId = $subscriptionData['id'] ?? null;
        $expiresAt = $subscriptionData['expires_at'] ?? null;

        // AcapaPayPaymentReceived cobre TODOS os pagamentos — com ou sem subscrição.
        // É o evento a usar em apps que aceitam pagamentos avulsos (ex: USD/cripto
        // via RedotPay), que nunca trazem subscrição no payload.
        event(new AcapaPayPaymentReceived(
            $payload['invoice_id'] ?? null,
            $subscriptionId,
            $metadata,
            $expiresAt,
            $payload['payment_method'] ?? null,
            $payload['currency'] ?? null,
            $payload['total'] ?? null,
            $data
        ));

        // AcapaPayInvoicePaid mantém o significado que sempre teve — "uma subscrição
        // foi paga" — por isso só dispara quando existe mesmo uma subscrição. Assim os
        // listeners já existentes continuam a receber um subscriptionId válido e nunca
        // são invocados com null (o que faria um Subscription::where(...) falhar em
        // silêncio). Um pagamento avulso deixa de rebentar o webhook: antes lançava
        // uma excepção aqui, devolvia HTTP 500, e o SSO reenviava indefinidamente.
        if ($subscriptionId !== null) {
            event(new AcapaPayInvoicePaid($subscriptionId, $metadata, $expiresAt, $data));

            Log::info('AcapaPay SDK: Webhook invoice.paid (subscrição) processado — eventos AcapaPayPaymentReceived e AcapaPayInvoicePaid disparados.');

            return;
        }

        Log::info('AcapaPay SDK: Webhook invoice.paid (pagamento avulso) processado — evento AcapaPayPaymentReceived disparado.');
    }

    /**
     * Processa o evento de fatura com pagamento falhado.
     */
    private function handleInvoiceFailed(array $data): void
    {
        $metadata = $data['data']['metadata'] ?? [];
        $invoiceId = $data['data']['invoice_id'] ?? null;
        $reason = $data['data']['reason'] ?? 'unknown';

        event(new AcapaPayInvoiceFailed($invoiceId, $reason, $metadata, $data));

        Log::info("AcapaPay SDK: Webhook invoice.failed validado e evento AcapaPayInvoiceFailed disparado.");
    }

    /**
     * Processa o evento de fatura expirada sem pagamento.
     */
    private function handleInvoiceExpired(array $data): void
    {
        $metadata = $data['data']['metadata'] ?? [];
        $invoiceId = $data['data']['invoice_id'] ?? null;

        event(new AcapaPayInvoiceExpired($invoiceId, $metadata, $data));

        Log::info("AcapaPay SDK: Webhook invoice.expired validado e evento AcapaPayInvoiceExpired disparado.");
    }
}
