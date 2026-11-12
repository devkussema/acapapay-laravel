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

### 3.2. Multicaixa Express via Deeplink (`PaymentMethod::GPO_DEEPLINK`) *(Novo na v1.6.0)*
Permite abrir diretamente a aplicação oficial Multicaixa Express instalada no telemóvel do cliente (Android / iOS):

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\PaymentMethod;

// Opção A: via método charge() padrão
$charge = AcapaPay::direct()->charge(
    invoiceId: $invoiceId,
    paymentMethod: PaymentMethod::GPO_DEEPLINK
);

// Opção B: via atalho direto
$charge = AcapaPay::direct()->chargeWithDeeplink($invoiceId);

// URI de Deeplink:
$deeplink = $charge->deeplink(); // ex: mcxwallet://purchase?...
$expiresAt = $charge->expiresAt(); // Validade de 15 minutos
```

**Uso no Frontend Blade:**
```html
<a href="{{ $charge->deeplink() }}" class="btn btn-primary">
    Pagar com Multicaixa Express (Abrir App)
</a>
```

---

### 3.3. Multicaixa Express via Código QR EMIS (`PaymentMethod::GPO_QR`) *(Novo na v1.6.0)*
Gera um Código QR EMIS oficial para escaneamento com a câmara da aplicação Multicaixa Express:

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\PaymentMethod;

// Opção A: via método charge() padrão
$charge = AcapaPay::direct()->charge(
    invoiceId: $invoiceId,
    paymentMethod: PaymentMethod::GPO_QR
);

// Opção B: via atalho direto
$charge = AcapaPay::direct()->chargeWithQrCode($invoiceId);

// Obter imagem formatada em Data URI:
$qrImage = $charge->qrCodeImage(); // data:image/png;base64,iVBORw0KGgo...
$expiresAt = $charge->expiresAt(); // Validade de 3 dias
```

**Uso no Frontend Blade:**
```html
<div class="qr-container">
    <img src="{{ $charge->qrCodeImage() }}" alt="Código QR Multicaixa Express" class="w-64 h-64 mx-auto rounded-xl shadow-lg">
    <p class="text-sm text-gray-500 mt-2">Abra o Multicaixa Express e aponte a câmara para pagar.</p>
</div>
```

---

### 3.4. Referência Multicaixa (`PaymentMethod::REF`)
Gera Entidade, Referência e Montante para pagamento em Caixa Automático (ATM) ou Internet Banking:

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\PaymentMethod;

$charge = AcapaPay::direct()->charge(
    invoiceId: $invoiceId,
    paymentMethod: PaymentMethod::REF
);

// Extrair dados da referência através dos getters do ChargeResult:
$entity = $charge->entity();       // ex: '00101'
$reference = $charge->reference(); // ex: '912345678' (9 dígitos)
```

---

### 3.5. Criptomoeda USD (`PaymentMethod::RDP` / RedotPay)
Gera uma ordem em USD liquidável em BTC, ETH, USDT, USDC, etc.:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

// A fatura DEVE ter sido criada com currency: 'USD'
$charge = AcapaPay::direct()->chargeWithCrypto($invoiceId);

$payUrl = $charge->payUrl(); // Link hospedado seguro da RedotPay
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

---

## 5. Boas Práticas: Idempotência e Segurança

### 5.1. Idempotência na Criação de Cobranças
- O AcapaPay protege automaticamente contra duplicação de cobranças ativas: se chamar `charge()` repetidamente para o mesmo método numa fatura que já possui uma cobrança ativa e válida, o SDK devolve a cobrança existente sem gerar registos em duplicado no banco ou na EMIS.
- Utilize a propriedade `app_reference` ao criar faturas (`createInvoice`) para mapear estritamente o seu pedido interno (`PED-9842`). Se uma fatura com esse `app_reference` já existir e estiver paga, não crie uma nova fatura.

### 5.2. Segurança e Validação de Pagamentos
1. **Nunca dependa exclusivamente do frontend:** o polling com `isPaid()` na interface serve apenas para melhorar a experiência do utilizador (UX). A entrega do produto ou ativação do plano deve ser confirmada de forma soberana através do **Webhook** (`AcapaPayPaymentReceived` / `AcapaPayInvoicePaid`).
2. **Validação de Assinatura HMAC:** todos os webhooks enviados pelo AcapaDev ID contêm a assinatura `X-AcapaPay-Signature` (HMAC-SHA256). O SDK valida automaticamente a assinatura contra o seu `ACAPAPAY_WEBHOOK_SECRET`.
3. **Bloqueio de Subpagamento:** o servidor verifica se o montante liquidado coincide ao cêntimo com o total da fatura antes de emitir a confirmação.

