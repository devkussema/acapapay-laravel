# 11. Saques e Payouts (API M2M)

A partir da versão `1.6.0`, as aplicações satélites podem consultar o saldo disponível, solicitar saques manuais e cancelar saques pendentes directamente através do SDK `AcapaPay`, sem precisar de aceder manualmente ao portal `payments.*`.

O comportamento é **idêntico** ao pedido feito pelo utilizador no portal `payments.*`, incluindo validações de IBAN, limiar mínimo, cálculo de taxas e envio de notificações por email para os administradores da plataforma.

---

## Pré-requisitos

Antes de solicitar um saque via API, o proprietário da aplicação deve ter configurado no portal `payments.*/settings`:
1. **IBAN** e dados bancários válidos;
2. **Preferência de saque** definida como `manual`;
3. **Valor mínimo** (`5 000`, `10 000`, `25 000` ou `50 000` Kz).

> **Taxas aplicadas:**
> - **Taxa da plataforma (3%):** já descontada no cálculo do saldo disponível (`balance.available`).
> - **Taxa de saque (2%):** descontada sobre o valor bruto solicitado no momento do saque (`net_amount = gross_amount - 2%`).

---

## Testar Conexão M2M

Antes de integrar os saques, podes testar a comunicação M2M e verificar se os dados da tua aplicação vêm correctamente:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

// 1. Testar autenticação OAuth2 + conectividade M2M
$ping = AcapaPay::ping();
// ['status' => 'success', 'message' => 'Ligação M2M ao AcapaDev SSO estabelecida com sucesso.']

// 2. Testar leitura de saldo e configurações da aplicação
$dados = AcapaPay::getPayouts();
```

---

## 1. Consultar Saldo e Histórico de Saques

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$response = AcapaPay::getPayouts(page: 1);
```

**Endpoint:** `GET /api/v1/billing/payouts`

**Exemplo de resposta (`200 OK`):**
```json
{
  "status": "success",
  "app": {
    "id": 12,
    "name": "Minha App Satélite"
  },
  "balance": {
    "gross_paid": 100000,
    "platform_fee": 3000,
    "net_paid": 97000,
    "withdrawn": 0,
    "available": 97000
  },
  "settings": {
    "payout_preference": "manual",
    "payout_threshold": 50000,
    "has_iban": true,
    "bank_name": "BAI"
  },
  "payouts": {
    "current_page": 1,
    "data": []
  }
}
```

---

## 2. Solicitar Saque Manual

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$response = AcapaPay::requestPayout(50000.00);
```

**Endpoint:** `POST /api/v1/billing/payouts`  
**Body:** `{ "amount": 50000.00 }`

- Valida se o proprietário tem IBAN configurado e modo `manual`.
- Valida se `amount >= payout_threshold` e `amount <= balance.available`.
- Cria o saque com `status = "pending"`, regista no `AuditLogger` e envia **email formal aos administradores** (`PayoutRequestedAdminMail`).

**Exemplo de resposta (`201 Created`):**
```json
{
  "status": "success",
  "message": "Solicitação de saque enviada com sucesso! Os administradores foram notificados.",
  "payout": {
    "id": 45,
    "connected_app_id": 12,
    "gross_amount": "50000.00",
    "platform_fee": "1000.00",
    "net_amount": "49000.00",
    "currency": "AOA",
    "status": "pending"
  }
}
```

---

## 3. Cancelar Saque Pendente

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$response = AcapaPay::cancelPayout(45);
```

**Endpoint:** `POST /api/v1/billing/payouts/{id}/cancel`

- Apenas saques com `status = "pending"` pertencentes ao proprietário da aplicação podem ser cancelados.
- Atualiza o estado para `cancelled`, liberta o saldo novamente e envia **email aos administradores** (`PayoutCancelledAdminMail`).

**Exemplo de resposta (`200 OK`):**
```json
{
  "status": "success",
  "message": "Solicitação de saque cancelada com sucesso. O saldo voltou a ficar disponível.",
  "payout": {
    "id": 45,
    "status": "cancelled"
  }
}
```

---

## Tratamento de Erros

| Código HTTP | Excepção no SDK | Motivo comum |
|---|---|---|
| `422` | `ValidationException` | IBAN não configurado, modo automático ativo, saldo insuficiente, valor abaixo do mínimo ou saque não está `pending`. |
| `429` | `ApiException` | Solicitação duplicada em andamento (lock de 10s). |
| `404` | `NotFoundException` | Aplicação OAuth ou saque não encontrado. |
