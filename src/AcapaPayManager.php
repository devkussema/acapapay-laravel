<?php

namespace AcapaPay\Laravel;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AcapaPayManager
{
    /**
     * Obtém um token OAuth2 (Client Credentials) com cache para evitar
     * chamadas redundantes ao servidor de autenticação.
     * O token é cacheado por 50 minutos (tokens OAuth2 expiram tipicamente em 60 min).
     */
    protected function getAccessToken(): string
    {
        $cacheKey = 'acapapay_oauth_token_' . md5(config('acapapay.client_id'));

        return Cache::remember($cacheKey, 3000, function () {
            $response = Http::withOptions(['verify' => config('acapapay.verify_ssl')])
                ->asForm()
                ->post(config('acapapay.host') . '/oauth/token', [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => config('acapapay.client_id'),
                    'client_secret' => config('acapapay.client_secret'),
                ]);

            if (!$response->successful()) {
                // Não cachear tokens falhados
                throw new \Exception('AcapaPay SDK: Falha na autenticação OAuth. Verifica as tuas credenciais no .env.');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Executa um pedido HTTP autenticado à API do AcapaPay.
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $uri    URI relativa (ex: /v1/checkout/sessions)
     * @param array  $data   Dados a enviar no corpo do pedido
     * @return array Resposta decodificada da API
     */
    protected function apiRequest(string $method, string $uri, array $data = []): array
    {
        $token = $this->getAccessToken();
        $url = config('acapapay.api_host') . $uri;

        $response = Http::withOptions(['verify' => config('acapapay.verify_ssl')])
            ->withToken($token)
            ->send($method, $url, ['json' => $data]);

        if (!$response->successful()) {
            // Invalidar cache do token se receber 401 (token expirado)
            if ($response->status() === 401) {
                Cache::forget('acapapay_oauth_token_' . md5(config('acapapay.client_id')));
            }
            Log::error('AcapaPay SDK Erro API: ' . $response->body(), [
                'method' => $method,
                'uri' => $uri,
                'status' => $response->status(),
            ]);
            throw new \Exception('AcapaPay SDK: Falha na comunicação com o servidor de pagamento. Status: ' . $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * Cria uma nova sessão de pagamento (Checkout) no AcapaPay
     * e devolve o URL de redirecionamento.
     *
     * @param string|int  $userId          ID do utilizador na app satélite
     * @param string      $planReference   Código de referência do plano
     * @param array       $metadata        Metadados adicionais (opcionais)
     * @param string|null $successUrl      URL de retorno após sucesso
     * @param string|null $cancelUrl       URL de retorno após cancelamento
     * @param string|null $currency        Moeda preferida (ex: 'USD', 'AOA')
     * @param string|null $preferredMethod Método de pagamento preferido (ex: 'RDP' para RedotPay/cripto)
     * @return string URL de checkout para redirecionar o utilizador
     */
    public function checkoutSession(
        $userId,
        string $planReference,
        array $metadata = [],
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        ?string $currency = null,
        ?string $preferredMethod = null
    ): string {
        // Assegurar URLs de retorno com fallbacks inteligentes
        $successUrl = $successUrl ?: url('/acapapay/success');
        $cancelUrl = $cancelUrl ?: url('/acapapay/cancel');

        // Informar o Host de origem para o SSO permitir o Iframe
        $originDomain = request()->getSchemeAndHttpHost();

        // Injetar modo sandbox se estiver ativado
        if (config('acapapay.modo') === 'sandbox') {
            $metadata['sandbox_mode'] = true;
        }

        // Construir payload do pedido
        $body = [
            'user_id'             => $userId,
            'plan_reference_code' => $planReference,
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'origin_domain'       => $originDomain,
            'metadata'            => $metadata,
        ];

        // Adicionar moeda preferida (ex: 'USD' para pagamentos cripto/RedotPay)
        if ($currency) {
            $body['currency'] = $currency;
        } elseif (config('acapapay.preferred_currency')) {
            $body['currency'] = config('acapapay.preferred_currency');
        }

        // Adicionar método de pagamento preferido (ex: 'RDP' para RedotPay)
        if ($preferredMethod) {
            $body['preferred_payment_method'] = $preferredMethod;
        } elseif (config('acapapay.preferred_method')) {
            $body['preferred_payment_method'] = config('acapapay.preferred_method');
        }

        $result = $this->apiRequest('POST', '/v1/checkout/sessions', $body);

        return $result['url'] ?? '';
    }

    /**
     * Cria uma fatura avulsa (sem plano) para cobrança directa.
     * Ideal para pagamentos únicos em USD/cripto via RedotPay.
     *
     * @param string|int  $userId          ID do utilizador
     * @param float       $amount          Montante a cobrar
     * @param string      $description     Descrição do pagamento
     * @param string      $currency        Moeda (padrão: 'USD')
     * @param string|null $preferredMethod Método preferido (ex: 'RDP')
     * @param array       $metadata        Metadados adicionais
     * @param string|null $successUrl      URL de retorno após sucesso
     * @param string|null $cancelUrl       URL de retorno após cancelamento
     * @return string URL de checkout para redirecionar o utilizador
     */
    public function createInvoice(
        $userId,
        float $amount,
        string $description,
        string $currency = 'USD',
        ?string $preferredMethod = null,
        array $metadata = [],
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): string {
        $successUrl = $successUrl ?: url('/acapapay/success');
        $cancelUrl = $cancelUrl ?: url('/acapapay/cancel');

        // Injetar modo sandbox se estiver ativado
        if (config('acapapay.modo') === 'sandbox') {
            $metadata['sandbox_mode'] = true;
        }

        $body = [
            'user_id'     => $userId,
            'amount'      => round($amount, 2),
            'description' => $description,
            'currency'    => $currency,
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'metadata'    => $metadata,
        ];

        if ($preferredMethod) {
            $body['preferred_payment_method'] = $preferredMethod;
        }

        $result = $this->apiRequest('POST', '/v1/billing/invoices', $body);

        return $result['url'] ?? '';
    }

    /**
     * Verifica o estado de uma fatura utilizando a API M2M do AcapaPay.
     * Útil para fallback síncrono após redirecionamento.
     *
     * @param string $invoiceId UUID da fatura
     * @return array Dados da fatura incluindo status
     */
    public function getInvoiceStatus(string $invoiceId): array
    {
        return $this->apiRequest('GET', '/v1/billing/invoices/' . $invoiceId);
    }

    /**
     * Sincroniza os planos de faturação locais com o AcapaPay.
     *
     * @param array $plans Lista de planos no formato AcapaPay
     * @return array Resposta da API com os planos atualizados
     */
    public function syncPlans(array $plans): array
    {
        $result = $this->apiRequest('PUT', '/v1/billing/plans', ['plans' => $plans]);

        return $result['plans'] ?? [];
    }
}
