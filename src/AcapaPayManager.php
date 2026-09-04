<?php

namespace AcapaPay\Laravel;

use AcapaPay\Laravel\Http\AcapaPayClient;
use AcapaPay\Laravel\Http\DirectPaymentApi;

class AcapaPayManager
{
    protected AcapaPayClient $client;

    protected ?DirectPaymentApi $directApi = null;

    /**
     * O cliente é opcional para manter a compatibilidade com quem faz
     * `new AcapaPayManager()` directamente.
     */
    public function __construct(?AcapaPayClient $client = null)
    {
        $this->client = $client ?: new AcapaPayClient();
    }

    /**
     * API de pagamento direta — permite criar faturas e cobranças e construir
     * o teu próprio ecrã de checkout, sem redirecionar nem usar iFrame.
     *
     * @since 1.2.0
     */
    public function direct(): DirectPaymentApi
    {
        return $this->directApi ??= new DirectPaymentApi($this->client);
    }

    /**
     * Alias legível de direct(): AcapaPay::invoices()->status($id)
     *
     * @since 1.2.0
     */
    public function invoices(): DirectPaymentApi
    {
        return $this->direct();
    }

    /**
     * Testa a conectividade com a API do AcapaPay.
     *
     * @since 1.2.0
     */
    public function ping(): array
    {
        return $this->client->ping();
    }

    /**
     * Obtém um token OAuth2 (Client Credentials), com cache.
     *
     * Mantido com a mesma assinatura e visibilidade das versões anteriores,
     * para não quebrar subclasses; delega no AcapaPayClient.
     */
    protected function getAccessToken(): string
    {
        return $this->client->token();
    }

    /**
     * Executa um pedido HTTP autenticado à API do AcapaPay.
     *
     * Mantido com a mesma assinatura e visibilidade das versões anteriores;
     * delega no AcapaPayClient.
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $uri    URI relativa (ex: /v1/checkout/sessions)
     * @param array  $data   Dados a enviar no corpo do pedido
     * @return array Resposta decodificada da API
     */
    protected function apiRequest(string $method, string $uri, array $data = []): array
    {
        return $this->client->request($method, $uri, $data);
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
        $originDomain = $this->originDomain();

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

        // O endpoint /v1/billing/invoices espera customer_name + items[] — não
        // {amount, description}. Traduzimos aqui para manter esta assinatura
        // simples, que é a que faz sentido para um pagamento avulso.
        $body = [
            'customer_name' => $metadata['customer_name'] ?? ('user:' . $userId),
            'currency'      => $currency,
            'items'         => [[
                'description' => $description,
                'quantity'    => 1,
                'unit_price'  => round($amount, 2),
            ]],
            'app_reference' => (string) $userId,
            'success_url'   => $successUrl,
            'cancel_url'    => $cancelUrl,
            'origin_domain' => $this->originDomain(),
            'metadata'      => $metadata,
        ];

        if (!empty($metadata['customer_email'])) {
            $body['customer_email'] = $metadata['customer_email'];
        }

        if ($preferredMethod) {
            $body['preferred_payment_method'] = $preferredMethod;
        } elseif (config('acapapay.preferred_method')) {
            $body['preferred_payment_method'] = config('acapapay.preferred_method');
        }

        $result = $this->apiRequest('POST', '/v1/billing/invoices', $body);

        return $result['pay_url'] ?? $result['url'] ?? '';
    }

    /**
     * Domínio de origem, para o SSO autorizar o iFrame.
     * Em contexto de consola/fila não há pedido HTTP — usamos o APP_URL.
     */
    protected function originDomain(): ?string
    {
        try {
            if (app()->runningInConsole()) {
                return config('app.url');
            }

            return request()->getSchemeAndHttpHost();
        } catch (\Throwable $e) {
            return config('app.url');
        }
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

    /**
     * Atalho: cria uma fatura em USD e gera logo a cobrança em criptomoeda
     * (RedotPay), devolvendo o link de pagamento pronto a usar.
     *
     * Equivale a direct()->createInvoice(...) seguido de ->chargeWithCrypto(...).
     *
     * @param array<string, mixed> $invoiceAttributes Ver DirectPaymentApi::createInvoice()
     *
     * @since 1.2.0
     */
    public function createCryptoCharge(array $invoiceAttributes): \AcapaPay\Laravel\Support\ChargeResult
    {
        $invoiceAttributes['currency'] = $invoiceAttributes['currency'] ?? \AcapaPay\Laravel\Enums\Currency::USD;

        $invoice = $this->direct()->createInvoice($invoiceAttributes);

        return $this->direct()->chargeWithCrypto((string) $invoice['invoice_id']);
    }
}
