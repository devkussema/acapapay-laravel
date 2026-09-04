<?php

namespace AcapaPay\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use AcapaPay\Laravel\Events\AcapaPayInvoicePaid;
use AcapaPay\Laravel\Events\AcapaPayInvoiceFailed;
use AcapaPay\Laravel\Events\AcapaPayInvoiceExpired;

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
        // Extrair Estrutura Acapadev moderna
        $metadata = $data['data']['metadata'] ?? [];
        $subscriptionData = $data['data']['subscription'] ?? null;
        
        if (!$subscriptionData) {
            throw new \Exception("AcapaPay SDK: A subscrição está omissa no Payload recebido.");
        }

        $subscriptionId = $subscriptionData['id'];
        $expiresAt = $subscriptionData['expires_at'] ?? null;

        // Disparar um Evento Global Nativo no Laravel da app Satélite
        event(new AcapaPayInvoicePaid($subscriptionId, $metadata, $expiresAt, $data));
        
        Log::info("AcapaPay SDK: Webhook invoice.paid validado e evento AcapaPayInvoicePaid disparado com sucesso.");
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
