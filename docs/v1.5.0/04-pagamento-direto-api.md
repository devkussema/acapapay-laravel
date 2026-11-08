# AcapaPay Laravel SDK — Pagamento Direto via API (Headless)

* **Versão:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026

---

## 1. Visão Geral

A API de Pagamento Direto (`AcapaPay::direct()` ou `AcapaPay::invoices()`) permite construir um fluxo de checkout inteiramente personalizado dentro da sua aplicação (web, desktop ou mobile). O utilizador nunca abandona a sua interface nem visualiza páginas externas do AcapaPay.

O fluxo direto divide-se em 3 etapas simples:
1. **Criação da Fatura:** `createInvoice()`
2. **Geração da Cobrança:** `charge()`
3. **Acompanhamento de Estado:** `status()` ou escuta do webhook `AcapaPayPaymentReceived`

---

## 2. Passo 1: Criar a Fatura

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\Currency;

$invoice = AcapaPay::direct()->createInvoice([
    'customer_name' => 'Manuel Domingos António',
    'customer_email' => 'manuel.antonio@empresa.co.ao',
    'customer_nif' => '5412893012',
    'currency' => Currency::AOA, // Currency::AOA ou Currency::USD
    'app_reference' => 'PED-9842',
    'items' => [
        [
            'description' => 'Licença Anual Software de Gestão',
            'quantity' => 1,
            'unit_price' => 150000.00,
        ],
        [
            'description' => 'Serviço de Migração e Suporte',
            'quantity' => 2,
            'unit_price' => 25000.00,
        ],
    ],
    'metadata' => [
        'order_id' => 9842,
        'client_id' => 450,
    ],
]);

$invoiceId = $invoice['invoice_id']; // UUID da fatura
$total = $invoice['total'];         // 200000.00
```

---

## 3. Passo 2: Gerar a Cobrança por Método

### 3.1. Multicaixa Express (`PaymentMethod::GPO`)
Despoleta uma notificação no telemóvel do cliente com temporizador de autorização:

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\PaymentMethod;

$charge = AcapaPay::direct()->charge(
    invoiceId: $invoiceId,
    paymentMethod: PaymentMethod::GPO,
    phoneNumber: '923000111' // Número Multicaixa Express do cliente (com ou sem 244)
);

// Objeto ChargeResult devolvido:
$isMock = $charge->isMock; // true se simulado em sandbox sem chaves
$expiresAt = $charge->expiresAt; // ISO8601 do fim do temporizador (ex: 60s)
```

A sua interface exibe ao utilizador: *"Confirme o pagamento de 200.000 Kz na app Multicaixa Express do número 923 000 111"*.

---

### 3.2. Referência Multicaixa (`PaymentMethod::REF`)
Gera Entidade, Referência e Montante para pagamento em Caixa Automático (ATM) ou Internet Banking:

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\PaymentMethod;

$charge = AcapaPay::direct()->charge(
    invoiceId: $invoiceId,
    paymentMethod: PaymentMethod::REF
);

// Extrair dados da referência:
$data = $charge->data;
$entity = $data['entity'] ?? '00101'; // Número de Entidade EMIS
$reference = $data['reference'] ?? $data['referenceNumber']; // 9 dígitos
$amount = $data['amount'];
$dueDate = $data['dueDate']; // Validade padrão de 3 dias
```

---

### 3.3. Criptomoeda USD (`PaymentMethod::RDP` / RedotPay)
Gera uma ordem em USD liquidável em BTC, ETH, USDT, USDC, etc.:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

// A fatura DEVE ter sido criada com currency: 'USD'
$charge = AcapaPay::direct()->chargeWithCrypto($invoiceId);

$payUrl = $charge->data['pay_url']; // Link hospedado seguro da RedotPay
```

---

## 4. Passo 3: Consultar o Estado da Cobrança (`status`)

Para interfaces com polling AJAX ou Livewire:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$statusData = AcapaPay::direct()->status($invoiceId);

// Exemplo de retorno:
// [
//     'invoice_status' => 'paid', // 'pending', 'paid', 'refunded', 'partially_refunded', 'failed'
//     'paid_at' => '2026-11-08T18:30:00Z',
//     'transaction' => [
//         'payment_method' => 'GPO',
//         'status' => 'completed',
//         'gateway_reference' => 'APP-TX-83921'
//     ]
// ]

if (AcapaPay::direct()->isPaid($invoiceId)) {
    // Fatura liquidada!
}
```

> [!TIP]
> **Rate Limiting Inteligente:** O servidor do AcapaDev ID limita as chamadas externas aos gateways a 1 consulta a cada 15 segundos por fatura, protegendo as quotas da sua empresa na AppyPay. Chamadas intermédias devolvem o último estado em cache instantaneamente.
