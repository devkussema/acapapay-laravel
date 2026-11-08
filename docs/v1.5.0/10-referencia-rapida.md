# AcapaPay Laravel SDK — Referência Rápida (Cheat Sheet)

* **Versão:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026

---

## 1. Métodos da Facade `AcapaPay`

```php
use AcapaPay\Laravel\Facades\AcapaPay;

// Teste de conectividade
AcapaPay::ping(): array

// Checkout Hospedado (Sessão para Subscrição)
AcapaPay::checkoutSession($userId, $planReference, $metadata = [], $successUrl = null, $cancelUrl = null, $currency = null, $preferredMethod = null): string

// Checkout Hospedado (Fatura com Valor Avulso)
AcapaPay::createInvoice($userId, $amount, $description, $currency = 'AOA', $preferredMethod = null, $metadata = [], $successUrl = null, $cancelUrl = null): string

// Consulta de Fatura
AcapaPay::getInvoiceStatus($invoiceId): array

// Sincronização de Planos
AcapaPay::syncPlans(array $plans): array

// Atalho de Cobrança Cripto
AcapaPay::createCryptoCharge(array $invoiceAttributes): ChargeResult

// Reembolso Total ou Parcial (Novo v1.5.0)
AcapaPay::refund($invoiceId, $amount = null, $reason = null): array

// Acesso à API Direta
AcapaPay::direct(): DirectPaymentApi
AcapaPay::invoices(): DirectPaymentApi
```

---

## 2. Métodos de `DirectPaymentApi` (`AcapaPay::direct()`)

```php
// Criar Fatura
AcapaPay::direct()->createInvoice(array $attributes): array

// Gerar Cobrança (GPO, REF, RDP, WIP)
AcapaPay::direct()->charge($invoiceId, $paymentMethod, $phoneNumber = null): ChargeResult

// Atalhos de Cobrança
AcapaPay::direct()->chargeWithCrypto($invoiceId): ChargeResult
AcapaPay::direct()->chargeWithWipay($invoiceId, $phoneNumber = null): ChargeResult

// Consulta de Estado
AcapaPay::direct()->status($invoiceId): array
AcapaPay::direct()->isPaid($invoiceId): bool
AcapaPay::direct()->find($invoiceId): array

// Simulação em Sandbox
AcapaPay::direct()->simulate($invoiceId): array

// Reembolso (Novo v1.5.0)
AcapaPay::direct()->refund($invoiceId, $amount = null, $reason = null): array
```

---

## 3. Constantes e Enums

### Moedas (`AcapaPay\Laravel\Enums\Currency`)
* `Currency::AOA` — Kwanza Angolano (Padrão para GPO, REF e WIP)
* `Currency::USD` — Dólar Americano (Obrigatório para RDP / RedotPay)

### Métodos de Pagamento (`AcapaPay\Laravel\Enums\PaymentMethod`)
* `PaymentMethod::GPO` — Multicaixa Express (Requer telefone, suporta reembolso)
* `PaymentMethod::REF` — Referência Multicaixa (Entidade, Referência e Montante)
* `PaymentMethod::RDP` — Criptomoeda USD via RedotPay
* `PaymentMethod::WIP` — WiPay Checkout Hospedado

---

## 4. Eventos Laravel e Payloads

```php
use AcapaPay\Laravel\Events\AcapaPayPaymentReceived;
use AcapaPay\Laravel\Events\AcapaPayInvoicePaid;
use AcapaPay\Laravel\Events\AcapaPayInvoiceRefunded;
use AcapaPay\Laravel\Events\AcapaPayInvoiceFailed;
use AcapaPay\Laravel\Events\AcapaPayInvoiceExpired;

// Propriedades do evento AcapaPayInvoiceRefunded (v1.5.0):
$event->invoiceId;      // UUID da fatura
$event->amount;         // Float montante estornado
$event->reason;         // String motivo
$event->metadata;       // Array de metadados originais
$event->webhookPayload; // Payload bruto recebido
```
