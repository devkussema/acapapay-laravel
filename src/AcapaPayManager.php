<?php

namespace AcapaPay\Laravel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AcapaPayManager
{
    /**
     * Cria uma nova sessão de pagamento (Checkout) no AcapaPay
     * e devolve o URL de redirecionamento.
     */
    public function checkoutSession($userId, string $planReference, array $metadata = [], string $successUrl = null, string $cancelUrl = null)
    {
        // 1. Obter o Access Token (via Client Credentials)
        $authReq = Http::withOptions(['verify' => config('acapapay.verify_ssl')])->asForm()
            ->post(config('acapapay.host') . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('acapapay.client_id'),
                'client_secret' => config('acapapay.client_secret'),
            ]);

        if (!$authReq->successful()) {
            throw new \Exception('AcapaPay SDK: Falha na autenticação OAuth. Verifica as tuas credenciais no .env.');
        }

        $token = $authReq->json('access_token');
        
        // Assegurar que temos URLs de retorno, senão adivinha
        if (!$successUrl) {
            $successUrl = url('/acapapay/success');
        }
        if (!$cancelUrl) {
            $cancelUrl = url('/acapapay/cancel');
        }

        // Informar o Host de origem para o SSO permitir o Iframe
        $originDomain = request()->getSchemeAndHttpHost();

        // 2. Pedir uma Sessão de Checkout
        $checkoutReq = Http::withOptions(['verify' => config('acapapay.verify_ssl')])->withToken($token)->post(config('acapapay.api_host') . '/v1/checkout/sessions', [
            'user_id' => $userId,
            'plan_reference_code' => $planReference,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'origin_domain' => $originDomain, // Adicionamos esta metadata para que o SSO liberte o iframe security
            'metadata' => $metadata
        ]);

        if (!$checkoutReq->successful()) {
            Log::error('AcapaPay Erro Checkout: ' . $checkoutReq->body());
            throw new \Exception('AcapaPay SDK: Falha ao comunicar com o servidor de pagamento para criar sessão.');
        }

        return $checkoutReq->json('url');
    }

    /**
     * Verifica o estado de uma fatura utilizando a API M2M do AcapaPay.
     * Útil para fallback síncrono após redirecionamento.
     */
    public function getInvoiceStatus(string $invoiceId)
    {
        // 1. Obter o Access Token (via Client Credentials)
        $authReq = Http::withOptions(['verify' => config('acapapay.verify_ssl')])->asForm()
            ->post(config('acapapay.host') . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('acapapay.client_id'),
                'client_secret' => config('acapapay.client_secret'),
            ]);

        if (!$authReq->successful()) {
            throw new \Exception('AcapaPay SDK: Falha na autenticação OAuth para verificar fatura.');
        }

        $token = $authReq->json('access_token');

        // 2. Consultar o estado da fatura
        $invoiceReq = Http::withOptions(['verify' => config('acapapay.verify_ssl')])
            ->withToken($token)
            ->get(config('acapapay.api_host') . '/v1/billing/invoices/' . $invoiceId);

        if (!$invoiceReq->successful()) {
            Log::error('AcapaPay Erro GetInvoice: ' . $invoiceReq->body());
            throw new \Exception('AcapaPay SDK: Falha ao comunicar com o servidor de pagamento para verificar a fatura.');
        }

        return $invoiceReq->json();
    }

    /**
     * Sincroniza os planos de faturação locais com o AcapaPay.
     * 
     * @param array $plans Lista de planos
     * @return array Resposta da API com os planos atualizados
     */
    public function syncPlans(array $plans)
    {
        // 1. Obter o Access Token (via Client Credentials)
        $authReq = Http::withOptions(['verify' => config('acapapay.verify_ssl')])->asForm()
            ->post(config('acapapay.host') . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('acapapay.client_id'),
                'client_secret' => config('acapapay.client_secret'),
            ]);

        if (!$authReq->successful()) {
            throw new \Exception('AcapaPay SDK: Falha na autenticação OAuth para sincronizar planos.');
        }

        $token = $authReq->json('access_token');

        // 2. Enviar a lista de planos para sincronização
        $syncReq = Http::withOptions(['verify' => config('acapapay.verify_ssl')])
            ->withToken($token)
            ->put(config('acapapay.api_host') . '/v1/billing/plans', [
                'plans' => $plans
            ]);

        if (!$syncReq->successful()) {
            Log::error('AcapaPay Erro SyncPlans: ' . $syncReq->body());
            throw new \Exception('AcapaPay SDK: Falha ao sincronizar planos com o servidor AcapaPay.');
        }

        return $syncReq->json('plans') ?? [];
    }
}
