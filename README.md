# AcapaPay Laravel SDK

O **AcapaPay Laravel SDK** é a biblioteca oficial para integrar de forma rápida e segura a gateway de pagamentos centralizada do ecossistema AcapaDev em qualquer projeto baseado no Laravel.

Este pacote trata automaticamente da comunicação OAuth2 (Server-to-Server) e expõe componentes prontos a usar para apresentar formulários de pagamento sem fricções (através de iFrames otimizados e bidirecionais).

> [!WARNING]
> **Compatibilidade Exclusiva:** Este pacote foi desenhado exclusivamente para o **Laravel Framework**. Ele possui proteções em *runtime* que impedem a sua execução em ambientes PHP puro ou noutras frameworks, assegurando estabilidade na injeção de dependências.

## Funcionalidades Principais

* **Autenticação Automática:** Gestão transparente de Tokens M2M (OAuth2) via `Client Credentials`.
* **Blade Components:** Um componente UI `<x-acapapay::iframe>` inteligente que reage automaticamente quando a fatura é paga pelo utilizador.
* **Validação de Webhooks (HMAC):** Receção segura das notificações de pagamento baseadas numa chave secreta, usando Laravel Events.
* **CLI Diagnostic:** Ferramentas Artisan nativas para testar a saúde da conexão entre o teu servidor e o SSO central.

---

## 1. Instalação

Podes instalar este pacote facilmente através do Composer.

```bash
composer require devkussema/acapapay-laravel
```

### Publicar Configurações

Opcionalmente, podes publicar o ficheiro de configuração se precisares de efetuar modificações profundas (geralmente não é necessário, dado que as variáveis de ambiente `.env` cobrem o essencial):

```bash
php artisan vendor:publish --tag="acapapay-config"
```

---

## 2. Configuração (Variáveis de Ambiente)

A tua aplicação satélite precisa de se identificar perante o AcapaPay (SSO). Tens de criar uma "OAuth App" no teu painel do SSO e adicionar as seguintes credenciais ao teu ficheiro `.env`:

```env
# URL Base do Sistema de Identidade (ex: https://id.acapadev.com)
ACAPAPAY_HOST=https://id.acapadev.com

# URL Base da API do SSO (ex: https://api.acapadev.com)
ACAPAPAY_API_HOST=https://api.acapadev.com

# O teu Client ID (App ID) gerado no Painel de Developer do SSO
ACAPAPAY_CLIENT_ID=9a8b7c6d-1234-5678-abcd...

# O Client Secret gerado para a tua App Satélite
ACAPAPAY_CLIENT_SECRET=super_secret_string...

# (Opcional) A chave HMAC para assinar Webhooks.
# Essencial para garantir que os Webhooks vêm legitimamente do AcapaPay.
ACAPAPAY_WEBHOOK_SECRET=hmac_secret_aqui...

# (Opcional) Desativa verificação SSL (útil para desenvolvimento local)
# ACAPAPAY_VERIFY_SSL=false

# (Opcional) 'sandbox' permite simular pagamentos sem cobrar nada.
# Deixa em 'production' (padrão) para pagamentos reais.
# ACAPAPAY_MODO=sandbox

# (Opcional) Moeda e método padrão, se a tua app cobra sempre da mesma forma.
# Ex: uma app que só cobra em cripto/USD:
# ACAPAPAY_PREFERRED_CURRENCY=USD
# ACAPAPAY_PREFERRED_METHOD=RDP

# (Opcional) Timeouts e retentativas das chamadas à API
# ACAPAPAY_TIMEOUT=30
# ACAPAPAY_CONNECT_TIMEOUT=10
# ACAPAPAY_RETRY_TIMES=2
```

---

## 3. Teste de Diagnóstico e Conexão (Artisan)

Antes de escrever qualquer código, o pacote fornece um comando de diagnóstico que envia um *Ping* seguro à infraestrutura central. Isto valida se as credenciais `.env` estão corretas e se os firewalls não estão a bloquear a ligação.

Executa o seguinte comando no teu terminal:

```bash
php artisan acapapay:test-connection
```

* Se tudo estiver correto, verás um *banner verde* de sucesso no terminal informando a validação das permissões.
* Se algo falhar (ex: IP bloqueado ou segredo errado), ser-te-á devolvido um relatório de erro a vermelho a explicar o porquê.

---

## 4. Iniciar um Pagamento (Checkout)

O pacote regista automaticamente uma *Facade* global chamada `AcapaPay`. Para criar uma intenção de pagamento no SSO e obter o URL para onde apontar o teu utilizador, basta utilizares o método `checkoutSession`.

### Exemplo no teu Controller:

```php
<?php

namespace App\Http\Controllers;

use AcapaPay\Laravel\Facades\AcapaPay;

class PagamentoController extends Controller
{
    public function comprarPlano()
    {
        $userId = auth()->id(); // ID local do teu utilizador
        $plano = 'PRO_YEARLY';  // Referência do teu plano (que existe no catálogo)

        try {
            // Inicia sessão no SSO
            $urlDePagamento = AcapaPay::checkoutSession(
                $userId, 
                $plano, 
                ['minha_metadata' => '123'], // Opcional
                url('/pagamento/sucesso'),   // URL Sucesso
                url('/pagamento/cancelado')  // URL Cancelamento
            );

            // Redirecionar o utilizador para a View de Pagamento
            return view('pagamento.checkout', compact('urlDePagamento'));

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

---

## 5. Integrar Interface (iFrame Blade Component)

Para manter o utilizador dentro do teu site sem o atirar para fora de forma abrupta, utiliza o nosso componente *Blade* preparado para iFrames. Este componente já escuta eventos de sucesso transmitidos pelo SSO Central!

No teu ficheiro de View (ex: `resources/views/pagamento/checkout.blade.php`), insere:

```blade
<x-layout>
    <h1>Concluir Pagamento</h1>
    
    <!-- Renderiza o Checkout Seguro do AcapaPay -->
    <x-acapapay::iframe :checkout-url="$urlDePagamento" />
    
</x-layout>
```

> [!NOTE]
> O Componente deteta magicamente eventos disparados pela página de sucesso no AcapaDev usando `postMessage` e pode redirecionar automaticamente a aba pai de volta para o teu site sem precisares de escrever JavaScript!

---

## 6. Escutar Webhooks (Atualizar Encomendas)

Quando a transação for paga com sucesso (ou falhar) via Referência Multicaixa (Pay4All) ou Cartão, o servidor central envia um *Webhook POST* para a tua aplicação.

A boa notícia? O nosso SDK regista automaticamente uma Rota (`/webhooks/acapapay`) ignorando a proteção CSRF que geralmente te daria dores de cabeça. 

O pacote processa o Webhook, valida a assinatura **HMAC** (caso tenhas configurado o `ACAPAPAY_WEBHOOK_SECRET`) e, se tudo estiver seguro, dispara um **Evento Laravel Nativo**: `AcapaPayInvoicePaid`.

Basta escutares este evento no teu projeto.

### Exemplo de Listener:
Regista o Listener no teu `EventServiceProvider`:

```php
use AcapaPay\Laravel\Events\AcapaPayInvoicePaid;

protected $listen = [
    AcapaPayInvoicePaid::class => [
        \App\Listeners\MarcarFaturaComoPaga::class,
    ],
];
```

Dentro do teu *Listener* (`MarcarFaturaComoPaga.php`):

```php
public function handle(AcapaPayInvoicePaid $event)
{
    // Propriedades disponíveis no evento:
    $subscriptionId = $event->subscriptionId;   // ID da subscrição no AcapaPay
    $metadata       = $event->metadata;          // Os metadados que enviaste no checkout
    $expiresAt      = $event->expiresAt;         // Validade da subscrição (ISO 8601)
    $payload        = $event->invoicePayload;    // Payload completo do webhook

    $localUserId = $metadata['local_user_id'] ?? null;

    // Protege contra reprocessamento (o SSO pode reenviar o mesmo webhook):
    if (Subscription::where('acapapay_id', $subscriptionId)->exists()) {
        return;
    }

    // Atualiza a tua Base de Dados local:
    // Order::where('user_id', $localUserId)->update(['status' => 'paid']);
}
```

> ⚠️ **Atenção:** o `AcapaPayInvoicePaid` só é disparado em pagamentos **de subscrição**.
> Se a tua app também aceita pagamentos avulsos (por exemplo em USD/cripto), usa o
> evento `AcapaPayPaymentReceived` descrito na [secção 8](#8-eventos-disponíveis).

---

## 7. Pagamentos em USD e Criptomoedas (RedotPay)

O AcapaPay suporta pagamentos em **Dólar Americano (USD)** e **criptomoedas (stablecoins)** através da integração com a **RedotPay**. Isto permite que os teus utilizadores paguem com USDT, USDC e outros activos digitais, enquanto tu recebes o settlement na tua moeda local.

### Checkout com USD/Cripto

Para iniciar um pagamento em dólar via RedotPay, basta especificares a moeda e o método preferido:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$url = AcapaPay::checkoutSession(
    userId: auth()->id(),
    planReference: 'PRO_YEARLY',
    metadata: ['projeto' => 'minha-app'],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado'),
    currency: 'USD',                // Forçar checkout em dólares
    preferredMethod: 'RDP'           // Pré-selecionar RedotPay/cripto
);

return redirect($url);
```

### Fatura Avulsa em USD

Para cobrar um montante específico sem estar associado a um plano:

```php
$url = AcapaPay::createInvoice(
    userId: auth()->id(),
    amount: 49.99,
    description: 'Consultoria Premium - 1 hora',
    currency: 'USD',
    preferredMethod: 'RDP',
    metadata: ['sessao_id' => $sessaoId],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado')
);

return redirect($url);
```

### Configuração Global de Moeda

Se a tua aplicação cobra exclusivamente em USD, podes definir a moeda e método padrão no `.env`:

```env
ACAPAPAY_PREFERRED_CURRENCY=USD
ACAPAPAY_PREFERRED_METHOD=RDP
```

Assim, não precisas de especificar `currency` e `preferredMethod` em cada chamada.

### Constantes em vez de strings

Para evitares erros de escrita, usa as constantes em vez de `'RDP'`/`'USD'`:

```php
use AcapaPay\Laravel\Enums\PaymentMethod;
use AcapaPay\Laravel\Enums\Currency;

AcapaPay::checkoutSession(
    userId: auth()->id(),
    planReference: 'PRO_YEARLY',
    currency: Currency::USD,
    preferredMethod: PaymentMethod::RDP,
);
```

| Constante | Valor | Método | Moeda | Validade da cobrança |
|---|---|---|---|---|
| `PaymentMethod::REF` | `REF` | Referência Multicaixa | AOA | ~3 dias |
| `PaymentMethod::GPO` | `GPO` | Multicaixa Express | AOA | 60 segundos |
| `PaymentMethod::EKZ` | `EKZ` | E-Kwanza | AOA | 5 minutos |
| `PaymentMethod::RDP` | `RDP` | **RedotPay (cripto)** | **USD** | 1 hora |

> A RedotPay **exige** faturas em USD. Se criares uma fatura em AOA e pedires `RDP`,
> a API devolve `422` com uma mensagem explícita.

---

## 8. API de Pagamento Direta (checkout próprio, sem iFrame)

As secções anteriores usam o **checkout hospedado**: o utilizador é redirecionado (ou vê um iFrame) com a página de pagamento do AcapaPay.

Se preferires desenhar **a tua própria interface de pagamento** — mostrando a referência Multicaixa, o ticket E-Kwanza ou o link de cripto dentro da tua app — usa a **API direta**.

### Fluxo

```
createInvoice()  →  charge()  →  status()  (polling)
                                     ↕
                          webhook invoice.paid (confirmação fiável)
```

### Exemplo completo: checkout em cripto próprio

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\Currency;
use AcapaPay\Laravel\Enums\PaymentMethod;

// 1. Criar a fatura
$invoice = AcapaPay::direct()->createInvoice([
    'customer_name'  => $user->name,
    'customer_email' => $user->email,
    'currency'       => Currency::USD,
    'app_reference'  => "pedido-{$order->id}",
    'items'          => [
        ['description' => 'Plano PRO (anual)', 'quantity' => 1, 'unit_price' => 120.00],
    ],
]);

// 2. Gerar a cobrança em criptomoeda
$charge = AcapaPay::direct()->charge($invoice['invoice_id'], PaymentMethod::RDP);

// 3. Mostrar ao utilizador na tua própria UI
return view('checkout', [
    'payUrl'    => $charge->payUrl(),      // link de pagamento da RedotPay
    'expiresAt' => $charge->expiresAt(),   // até quando é válido
    'invoiceId' => $invoice['invoice_id'],
]);
```

Ou, num único passo:

```php
$charge = AcapaPay::createCryptoCharge([
    'customer_name' => $user->name,
    'items' => [['description' => 'Plano PRO', 'quantity' => 1, 'unit_price' => 120.00]],
]);

return redirect($charge->payUrl());
```

### Verificar o estado (polling)

```php
$status = AcapaPay::direct()->status($invoiceId);
// ['invoice_status' => 'pending'|'paid', 'paid_at' => ..., 'transaction' => [...]]

if (AcapaPay::direct()->isPaid($invoiceId)) {
    // ...
}
```

> O servidor só consulta a gateway externa **uma vez a cada 15 segundos** por fatura.
> Não vale a pena perguntar mais depressa — e usa sempre o **webhook** como confirmação
> definitiva; o polling serve apenas para dar feedback imediato na interface.

### Testar sem dinheiro real (sandbox)

Com `ACAPAPAY_MODO=sandbox`, podes marcar uma fatura como paga instantaneamente. Isto dispara o webhook `invoice.paid` tal como um pagamento real:

```php
AcapaPay::direct()->simulate($invoiceId);
```

### Referência dos métodos

| Método | Descrição |
|---|---|
| `AcapaPay::direct()->createInvoice(array $attributes)` | Cria a fatura. Devolve `invoice_id`, `pay_url`, `total`, `currency`. |
| `AcapaPay::direct()->charge($invoiceId, $method, $phone = null)` | Gera a cobrança. Devolve um `ChargeResult`. |
| `AcapaPay::direct()->chargeWithCrypto($invoiceId)` | Atalho para `charge(..., PaymentMethod::RDP)`. |
| `AcapaPay::direct()->status($invoiceId)` | Estado atual da fatura. |
| `AcapaPay::direct()->isPaid($invoiceId)` | `true` se já estiver paga. |
| `AcapaPay::direct()->find($invoiceId)` | Detalhe completo da fatura. |
| `AcapaPay::direct()->simulate($invoiceId)` | Marca como paga (só em sandbox). |
| `AcapaPay::createCryptoCharge(array $attributes)` | Cria fatura em USD + cobrança cripto, num só passo. |

O `ChargeResult` devolvido por `charge()` expõe `payUrl()`, `reference()`, `entity()`, `expiresAt()`, `paymentMethod()`, `isMock()` e `data()` — e também funciona como array (`$charge['data']['pay_url']`).

---

## 9. Eventos Disponíveis

| Evento | Quando é disparado |
|--------|--------------------|
| `AcapaPayPaymentReceived` | **Qualquer** fatura paga — com ou sem subscrição |
| `AcapaPayInvoicePaid` | Apenas pagamentos **de subscrição** |
| `AcapaPayInvoiceFailed` | Pagamento recusado pela gateway |
| `AcapaPayInvoiceExpired` | Fatura expirou sem pagamento |

### Qual devo usar?

- **Só vendes subscrições?** Continua com o `AcapaPayInvoicePaid`. Nada mudou.
- **Aceitas pagamentos avulsos** (ex: USD/cripto via `createInvoice()` ou pela API direta)?
  Usa o **`AcapaPayPaymentReceived`** — os pagamentos avulsos não têm subscrição e por isso
  **não** disparam o `AcapaPayInvoicePaid`.

> Se adotares o `AcapaPayPaymentReceived` **e** mantiveres um listener no `AcapaPayInvoicePaid`,
> um pagamento de subscrição vai acionar os dois. Usa `$event->isOneOff()` para tratar
> apenas os avulsos no listener novo e evitar processamento duplicado.

```php
use AcapaPay\Laravel\Events\AcapaPayPaymentReceived;

public function handle(AcapaPayPaymentReceived $event)
{
    if ($event->isSubscription()) {
        return; // já tratado pelo listener do AcapaPayInvoicePaid
    }

    // $event->invoiceId     - ID da fatura
    // $event->amount        - Total pago
    // $event->currency      - 'AOA' ou 'USD'
    // $event->paymentMethod - 'REF' | 'GPO' | 'EKZ' | 'RDP'
    // $event->metadata      - Os teus metadados
    // $event->isCrypto()    - true se foi pago em criptomoeda

    Order::where('id', $event->metadata['order_id'])->update(['status' => 'paid']);
}
```

### Exemplo de Listener para Falhas:

```php
use AcapaPay\Laravel\Events\AcapaPayInvoiceFailed;

protected $listen = [
    AcapaPayInvoiceFailed::class => [
        \App\Listeners\NotificarFalhaPagamento::class,
    ],
];
```

```php
public function handle(AcapaPayInvoiceFailed $event)
{
    // $event->invoiceId - ID da fatura
    // $event->reason - Razão da falha
    // $event->metadata - Metadados originais

    // Notificar o utilizador, reverter acções, etc.
}
```

---

## 10. Tratamento de Erros

O SDK lança excepções tipadas, todas descendentes de `\Exception` — o teu `catch (\Exception $e)` atual continua a funcionar.

```php
use AcapaPay\Laravel\Exceptions\ValidationException;
use AcapaPay\Laravel\Exceptions\AuthenticationException;
use AcapaPay\Laravel\Exceptions\ConnectionException;
use AcapaPay\Laravel\Exceptions\ApiException;

try {
    $charge = AcapaPay::direct()->charge($invoiceId, PaymentMethod::RDP);
} catch (ValidationException $e) {
    // 422 — dados recusados. $e->errors() traz os campos em falta.
    return back()->withErrors($e->errors());
} catch (AuthenticationException $e) {
    // Credenciais erradas no .env
    report($e);
} catch (ConnectionException $e) {
    // Rede/DNS/SSL — é seguro voltar a tentar
    return back()->with('error', 'Serviço temporariamente indisponível.');
} catch (ApiException $e) {
    // Qualquer outro erro HTTP: $e->status(), $e->apiError(), $e->isRetryable()
    report($e);
}
```

| Excepção | Significado |
|---|---|
| `ValidationException` | 422 — dados inválidos (tem `errors()`) |
| `AuthenticationException` | Falha no OAuth2 — credenciais erradas |
| `NotFoundException` | 404 — fatura inexistente ou de outra app |
| `ForbiddenException` | 403 — operação não permitida (ex: `simulate()` fora de sandbox) |
| `ConnectionException` | Falha de rede — seguro repetir |
| `ApiException` | Base das anteriores; qualquer outro erro HTTP |

---

## Licença

Distribuído sob a licença **MIT**.
