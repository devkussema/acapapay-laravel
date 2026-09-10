<?php

namespace AcapaPay\Laravel\Http;

use AcapaPay\Laravel\Enums\Currency;
use AcapaPay\Laravel\Enums\PaymentMethod;
use AcapaPay\Laravel\Exceptions\ForbiddenException;
use AcapaPay\Laravel\Exceptions\ValidationException;
use AcapaPay\Laravel\Support\ChargeResult;

/**
 * API de pagamento direta ("headless").
 *
 * Permite à tua app construir o seu próprio ecrã de checkout — mostrando a
 * referência Multicaixa, o prompt do Multicaixa Express, o ticket E-Kwanza ou
 * o link de pagamento em cripto da RedotPay dentro da tua própria interface —
 * sem redirecionar o utilizador nem embutir o iFrame do AcapaPay.
 *
 * Fluxo típico:
 *   1. createInvoice()  → cria a fatura
 *   2. charge()         → gera a cobrança no método escolhido
 *   3. status()         → polling até invoice_status === 'paid'
 *      (em paralelo, o webhook invoice.paid confirma de forma fiável)
 *
 * @since 1.2.0
 */
class DirectPaymentApi
{
    public function __construct(protected AcapaPayClient $client)
    {
    }

    /**
     * Cria uma fatura.
     *
     * @param array<string, mixed> $attributes {
     *     @type string $customer_name  Obrigatório.
     *     @type string $customer_email Opcional.
     *     @type string $customer_nif   Opcional.
     *     @type string $currency       'AOA' (padrão no servidor) ou 'USD'.
     *     @type array  $items          Obrigatório: [['description','quantity','unit_price'], ...]
     *     @type string $app_reference  A tua referência interna do pedido.
     *     @type string $due_date       Opcional.
     *     @type string $success_url    Para onde devolver o cliente depois de pagar. Em RDP é
     *                                  este o URL que a RedotPay usa como redirectUrl.
     *     @type string $cancel_url     Para onde devolver o cliente se ele desistir.
     *     @type array  $metadata       Guardado na fatura e devolvido no webhook invoice.paid.
     *     @type string $preferred_payment_method  REF|GPO|EKZ|RDP — sugestão para o checkout.
     * }
     * @return array<string, mixed> {status, invoice_id, pay_url, total, currency, preferred_payment_method}
     *
     * @throws ValidationException
     */
    public function createInvoice(array $attributes): array
    {
        if (empty($attributes['customer_name'])) {
            throw new ValidationException(
                'AcapaPay SDK: "customer_name" é obrigatório para criar uma fatura.',
                ['customer_name' => ['obrigatório']]
            );
        }

        if (empty($attributes['items']) || !is_array($attributes['items'])) {
            throw new ValidationException(
                'AcapaPay SDK: é preciso pelo menos um item em "items".',
                ['items' => ['obrigatório']]
            );
        }

        foreach ($attributes['items'] as $i => $item) {
            foreach (['description', 'quantity', 'unit_price'] as $field) {
                if (!isset($item[$field])) {
                    throw new ValidationException(
                        "AcapaPay SDK: falta \"{$field}\" no item #{$i} de \"items\".",
                        ["items.{$i}.{$field}" => ['obrigatório']]
                    );
                }
            }
        }

        if (isset($attributes['currency'])) {
            if (!Currency::isValid((string) $attributes['currency'])) {
                throw new ValidationException(
                    'AcapaPay SDK: moeda inválida. Use Currency::AOA ou Currency::USD.',
                    ['currency' => ['inválida']]
                );
            }

            // O servidor também normaliza, mas fazê-lo aqui evita que 'usd' viaje até lá.
            $attributes['currency'] = strtoupper((string) $attributes['currency']);
        }

        // Em sandbox, marcar a fatura como tal — é isto que faz o servidor usar o gateway
        // simulado em vez de gerar cobranças a sério. checkoutSession() e o createInvoice()
        // do AcapaPayManager já o faziam; aqui faltava, pelo que quem construía o seu próprio
        // checkout com direct()->createInvoice() gerava cobranças reais mesmo em sandbox.
        if (config('acapapay.modo') === 'sandbox') {
            $metadata = $attributes['metadata'] ?? [];
            $metadata['sandbox_mode'] = true;
            $attributes['metadata'] = $metadata;
        }

        return $this->client->post('/v1/billing/invoices', $attributes);
    }

    /**
     * Gera uma cobrança para uma fatura já criada.
     *
     * @param string      $invoiceId     ID devolvido por createInvoice()
     * @param string      $paymentMethod PaymentMethod::REF|GPO|EKZ|RDP
     * @param string|null $phoneNumber   Obrigatório para GPO e EKZ
     *
     * @throws ValidationException
     */
    public function charge(string $invoiceId, string $paymentMethod, ?string $phoneNumber = null): ChargeResult
    {
        $paymentMethod = strtoupper($paymentMethod);

        if (!PaymentMethod::isValid($paymentMethod)) {
            throw new ValidationException(
                'AcapaPay SDK: método de pagamento inválido: ' . $paymentMethod
                . '. Use uma constante de ' . PaymentMethod::class . '.',
                ['payment_method' => ['inválido']]
            );
        }

        if (PaymentMethod::requiresPhoneNumber($paymentMethod) && empty($phoneNumber)) {
            throw new ValidationException(
                'AcapaPay SDK: o método ' . PaymentMethod::label($paymentMethod)
                . ' exige o número de telemóvel do cliente.',
                ['phone_number' => ['obrigatório']]
            );
        }

        $body = ['payment_method' => $paymentMethod];

        if ($phoneNumber) {
            $body['phone_number'] = $phoneNumber;
        }

        $result = $this->client->post("/v1/billing/invoices/{$invoiceId}/charges", $body);

        return ChargeResult::fromArray(array_merge(['invoice_id' => $invoiceId], $result));
    }

    /**
     * Atalho para uma cobrança em criptomoeda via RedotPay.
     * A fatura tem de estar em USD.
     */
    public function chargeWithCrypto(string $invoiceId): ChargeResult
    {
        return $this->charge($invoiceId, PaymentMethod::RDP);
    }

    /**
     * Consulta o estado de pagamento de uma fatura.
     *
     * O servidor limita a consulta ao gateway externo a uma vez a cada 15
     * segundos por fatura — chamadas mais frequentes devolvem o último estado
     * conhecido, sem novo pedido externo.
     *
     * @return array<string, mixed> {invoice_status, paid_at, transaction:{...}}
     */
    public function status(string $invoiceId): array
    {
        $result = $this->client->get("/v1/billing/invoices/{$invoiceId}/status");

        return $result['data'] ?? $result;
    }

    /**
     * A fatura já está paga?
     */
    public function isPaid(string $invoiceId): bool
    {
        return ($this->status($invoiceId)['invoice_status'] ?? null) === 'paid';
    }

    /**
     * Detalhe completo da fatura.
     *
     * @return array<string, mixed>
     */
    public function find(string $invoiceId): array
    {
        $result = $this->client->get("/v1/billing/invoices/{$invoiceId}");

        return $result['data'] ?? $result;
    }

    /**
     * Marca a fatura como paga instantaneamente — apenas em sandbox.
     * Dispara o webhook invoice.paid, tal como um pagamento real.
     *
     * @return array<string, mixed>
     *
     * @throws ForbiddenException quando a app não está em modo sandbox.
     */
    public function simulate(string $invoiceId): array
    {
        if (config('acapapay.modo') !== 'sandbox') {
            throw new ForbiddenException(
                'AcapaPay SDK: simulate() só está disponível em sandbox. Define ACAPAPAY_MODO=sandbox.',
                403
            );
        }

        return $this->client->post("/v1/billing/invoices/{$invoiceId}/simulate");
    }
}
