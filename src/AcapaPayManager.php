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
        $authReq = Http::asForm()
            // ATENÇÃO: para testes com self-signed certificates, isto seria útil ser customizável,
            // mas o ideal é deixar o administrador da framework gerir o Http Facade em caso de erro.
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
        $checkoutReq = Http::withToken($token)->post(config('acapapay.api_host') . '/v1/checkout/sessions', [
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
}
