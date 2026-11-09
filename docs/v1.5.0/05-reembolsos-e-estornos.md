# AcapaPay Laravel SDK — Reembolsos e Estornos Automatizados

* **Versão:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026
* **Recurso:** Estorno Programático Total e Parcial de Cobranças

---

## 1. Visão Geral

A versão `v1.5.0` introduz o suporte oficial a **reembolsos (refunds) e estornos automatizados**. Este mecanismo permite que aplicações satélite emitam devoluções financeiras diretamente pelo SDK sem intervenção manual no portal bancário ou da AppyPay.

O dinheiro estornado é debitado do **TPA Virtual da Empresa** e devolvido imediatamente à conta bancária e cartão do cliente que originou o pagamento via **Multicaixa Express**.

---

## 2. Matriz de Suporte por Método de Pagamento

| Método de Pagamento | Código | Suporta Reembolso Automático? | Justificação Técnica |
| :--- | :---: | :---: | :--- |
| **Multicaixa Express** | `GPO` | **SIM** | O protocolo EMIS / AppyPay v2.0 associa a transação ao token do cartão do cliente, permitindo estorno direto via API. |
| **Referência Multicaixa** | `REF` | **NÃO** | Pagamentos por referência são transferências interbancárias unidirecionais sem vínculo de cartão na rede EMIS. Exigem devolução manual via transferência bancária tradicional (TED/IBAN). |
| **Criptomoeda USD (RedotPay)** | `RDP` | **NÃO** | Transações em blockchain são irreversíveis pelo protocolo nativo. |
| **Ambiente Sandbox** | `sandbox_test` | **SIM** | Totalmente suportado para testes automatizados e integração de fluxos. |

Você pode verificar programaticamente se um método suporta reembolso:

```php
use AcapaPay\Laravel\Enums\PaymentMethod;

if (PaymentMethod::supportsRefund($method)) {
    // Pode chamar AcapaPay::refund()
}
```

---

## 3. Como Emitir um Reembolso

### 3.1. Reembolso Total (Full Refund)
Se não especificar montante, o SDK estorna a totalidade do valor pago na fatura:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

try {
    $result = AcapaPay::refund(
        invoiceId: 'f94c3d8a-1122-4a55-8899-abcdef123456',
        amount: null, // null = estorno de 100% do valor da fatura
        reason: 'Cancelamento do pedido pelo cliente dentro do prazo legal'
    );

    // Estrutura de resposta:
    // [
    //     'invoice_id' => 'f94c3d8a-1122-4a55-8899-abcdef123456',
    //     'invoice_status' => 'refunded',
    //     'refund_amount' => 50000.00,
    //     'total_refunded' => 50000.00,
    //     'currency' => 'AOA',
    //     'transaction_id' => 9812,
    //     'gateway_response' => [...]
    // ]

    Log::info("Fatura reembolsada com sucesso: " . $result['invoice_status']);

} catch (\AcapaPay\Laravel\Exceptions\ApiException $e) {
    // Erros devolvidos pelo gateway (ex: saldo insuficiente no TPA)
    Log::error("Erro no gateway: " . $e->getMessage());
} catch (\Exception $e) {
    Log::error("Falha ao processar reembolso: " . $e->getMessage());
}
```

---

### 3.2. Reembolso Parcial (Partial Refund)
É possível devolver apenas parte do valor (por exemplo, devolução de 1 artigo de um pedido composto):

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$result = AcapaPay::refund(
    invoiceId: $invoiceId,
    amount: 12500.00, // Reembolso parcial
    reason: 'Devolução do Item #2 por avaria no transporte'
);

// O status da fatura torna-se 'partially_refunded'
if ($result['invoice_status'] === 'partially_refunded') {
    // Restam ainda fundos na fatura
}
```

Caso sejam efetuados múltiplos reembolsos parciais e a soma atinja o montante total da fatura, o estado é atualizado automaticamente para `refunded`.

---

### 3.3. Atalho via DirectPaymentApi
Também pode aceder através de `AcapaPay::direct()->refund(...)`:

```php
AcapaPay::direct()->refund($invoiceId, $amount, $reason);
```

---

## 4. Requisitos e Regras de Negócio

1. **Estado da Fatura:** Apenas faturas com status `paid` ou `partially_refunded` podem ser reembolsadas. Tentar reembolsar faturas `pending` ou `cancelled` lançará um erro HTTP 422 (`ValidationException`).
2. **Janela Temporal de 30 Dias (GPO Real):** Em pagamentos reais por Multicaixa Express (GPO), a rede interbancária EMIS e a AppyPay impõem um limite operacional estrito de **até 30 dias** após a data de pagamento para realização de estornos automáticos via API. Transações fora deste prazo exigem reconciliação manual junto do suporte bancário.
3. **Saldo em TPA Virtual (Live):** Em ambiente de produção, a sua conta de comerciante na AppyPay deve possuir **saldo disponível suficiente** no TPA Virtual do banco liquidante para cobrir o valor da devolução.
4. **Identificação 100% Automática:** A sua aplicação satélite apenas necessita de indicar o `invoiceId` da fatura. O AcapaDev ID associa, persiste e resolve o identificador de cobrança original da AppyPay de forma totalmente automática e transparente.
5. **Conformidade Bancária do Motivo:** A rede EMIS impõe limites estritos de carateres na descrição da transação. A API e o SDK tratam disso automaticamente: se fornecer um motivo customizado, ele é sanitizado e limitado a 20 carateres; caso seja omitido, o sistema envia o termo padrão seguro `"Reembolso"`. O motivo completo continua preservado na íntegra nos registos de auditoria e webhooks internos.
6. **Limite de Montante:** O montante solicitado não pode exceder o total da fatura menos o somatório de valores já estornados anteriormente.

---

## 5. Webhook `invoice.refunded`

Quando um reembolso é processado (seja disparado pela sua app, pelo painel administrativo ou pelo Portal do Desenvolvedor), o AcapaDev ID despacha automaticamente um webhook assinado para a sua aplicação com o evento `invoice.refunded`.

O SDK escuta este webhook e dispara o evento Laravel `AcapaPayInvoiceRefunded`. Veja o capítulo [06. Webhooks e Eventos](06-webhooks-e-eventos.md) para detalhes de implementação do Listener.
