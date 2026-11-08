# AcapaPay Laravel SDK — Sandbox, Testes e Simulações

* **Versão:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026

---

## 1. O Modo Sandbox

O modo Sandbox é essencial durante o ciclo de desenvolvimento e integração contínua (CI/CD). Ele permite testar os fluxos completos da sua aplicação (criação de faturas, checkout, liquidação, estorno e receção de webhooks) sem movimentar dinheiro real nem poluir os sistemas bancários da EMIS/AppyPay.

Para ativar no `.env`:

```env
ACAPAPAY_MODO=sandbox
```

---

## 2. Simulação Instantânea de Pagamento (`simulate`)

Em ambiente Sandbox, pode forçar a liquidação imediata de qualquer fatura pendente com o método `simulate()`. O servidor marcará a fatura como paga e disparará o webhook assinado `invoice.paid` para a sua aplicação:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$invoice = AcapaPay::direct()->createInvoice([
    'customer_name' => 'Utilizador de Teste',
    'items' => [
        ['description' => 'Produto Demo', 'quantity' => 1, 'unit_price' => 10000.00]
    ]
]);

$invoiceId = $invoice['invoice_id'];

// Simula a liquidação instantaneamente:
$result = AcapaPay::direct()->simulate($invoiceId);

// Retorna: ['invoice_status' => 'paid', 'paid_at' => '...']
```

> [!CAUTION]
> O método `simulate()` só funciona quando `ACAPAPAY_MODO=sandbox`. Se for chamado em modo `live`, o SDK lança imediatamente uma `ForbiddenException` (HTTP 403) para impedir simulações em faturas de produção.

---

## 3. Simulação de Reembolso em Sandbox

Da mesma forma, o método de reembolso funciona em Sandbox sem necessitar de fundos no TPA Virtual:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$refund = AcapaPay::refund(
    invoiceId: $invoiceId,
    amount: null, // Total
    reason: 'Teste de estorno em Sandbox'
);

// Dispara o webhook 'invoice.refunded' e atualiza o estado para 'refunded'
```

---

## 4. Testar Webhooks Localmente

Como os webhooks são despachados pelo servidor do AcapaDev ID, para os receber no seu ambiente local (`localhost`) utilize uma ferramenta de túnel reverso como o **ngrok** ou **Cloudflare Tunnels**:

```bash
ngrok http 8000
```

Copie o URL gerado (ex: `https://abcd-1234.ngrok-free.app`) e defina no Portal do Desenvolvedor:
```
https://abcd-1234.ngrok-free.app/acapapay/webhook
```
