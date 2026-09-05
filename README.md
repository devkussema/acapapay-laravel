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

### Como apresentar o pagamento ao utilizador: 2 fluxos possíveis

Isto aplica-se especificamente ao método `RDP` (RedotPay/criptomoeda), que é o único
onde a diferença entre os dois fluxos é significativa (para REF/GPO/EKZ a API direta
já te dá a referência/ticket em texto, pronta a mostrar — não há QR nem redirecionamento
externo envolvido).

Quando geras uma cobrança RDP, tens de escolher **uma de duas formas** de levar o
utilizador a pagar. Nenhuma delas é "melhor" objetivamente — a escolha depende de
quanto controlo visual precisas e de quanto trabalho estás disposto a fazer.

#### Fluxo 1 — Redirecionar (ou abrir num separador novo). Recomendado por omissão.

É o mais simples e é o que a nossa própria página de checkout hospedado usa
internamente. Chamas `charge()`, recebes um `payUrl()`, e envias o utilizador
para lá — ponto final.

```php
$charge = AcapaPay::direct()->charge($invoiceId, PaymentMethod::RDP);

return redirect($charge->payUrl());
// ou, se estiveres a chamar isto via AJAX/fetch a partir do frontend:
// return response()->json(['pay_url' => $charge->payUrl()]);
// e no frontend: window.open(data.pay_url, '_blank');
```

O que o utilizador vê ao chegar a esse `payUrl()`: uma página **hospedada pela
RedotPay** (não é nossa, nem tua) com a lista de carteiras suportadas
(MetaMask, Phantom, Trust Wallet, Coinbase, TronLink, etc.), cada uma já com o
seu próprio QR code desenhado e pronto a digitalizar. A RedotPay trata de tudo:
gerar as imagens de QR, detetar se o visitante está em mobile ou desktop, abrir
o deep-link correto se tiver a carteira instalada no telemóvel, etc.

**Vantagens:**
- Zero código extra do lado da UI — só precisas do link.
- A RedotPay mantém aquela página atualizada (novas carteiras, correções de
  segurança, etc.) sem tu teres de mexer em nada.
- Funciona logo em mobile e desktop, sem teres de te preocupar com qual QR
  mostrar em cada caso.

**Desvantagem:**
- O utilizador sai da tua aplicação/domínio durante um instante (vê o URL da
  RedotPay na barra de endereços, ou vês o iframe/separador novo). Se a tua
  marca for muito importante nesse momento, isto pode incomodar.

#### Fluxo 2 — Construir a tua própria interface com o QR code embutido. Mais trabalho, mais controlo.

Se quiseres que o utilizador **nunca saia da tua página** — por exemplo, mostrar
o QR code dentro de um modal da tua app, ao lado do logótipo da tua marca — usa
`paymentMethods()` ou `qrCodeUrls()` no `ChargeResult` devolvido por `charge()`.

**⚠️ Ponto mais importante de todo este fluxo, lê com atenção:** os campos `webQrCode`
e `h5QrCode` que a RedotPay devolve **não são imagens**. São *links* (deep-links de
carteira, tipo `https://phantom.app/ul/browse/...`). A RedotPay não gera nenhuma
imagem de QR code para te dar — quem tem de gerar a imagem, a partir desse link
de texto, **é a tua aplicação**. Isto é o "trabalho extra" de que falávamos: sem
esse passo de geração da imagem, não tens QR code nenhum para mostrar, só um link.

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\PaymentMethod;

$charge = AcapaPay::direct()->charge($invoiceId, PaymentMethod::RDP);

// Todas as carteiras suportadas, com nome, logótipo e os links a converter em QR:
$wallets = $charge->qrCodeUrls();
/*
[
    'phantom'  => ['name' => 'Phantom',  'logo' => 'https://.../phantom.svg',  'web' => 'https://phantom.app/ul/browse/...', 'h5' => '...'],
    'metamask' => ['name' => 'MetaMask', 'logo' => 'https://.../metamask.svg', 'web' => 'https://metamask.app.link/...',     'h5' => '...'],
    'trust'    => [...],
    'tronlink' => [...],
    // ... normalmente 12-13 carteiras
]
*/

return view('checkout.cripto', [
    'wallets'   => $wallets,
    'invoiceId' => $invoiceId,
]);
```

Agora, na tua view, tens de gerar a imagem do QR code **a partir do link** (o
campo `'web'` de cada carteira). Há duas formas de o fazer — escolhe consoante
onde preferes que o trabalho aconteça:

**Opção A — Gerar a imagem no servidor (PHP), com [`simplesoftwareio/simple-qrcode`](https://github.com/SimpleSoftwareIO/simple-qrcode):**

```bash
composer require simplesoftwareio/simple-qrcode
```

```blade
{{-- resources/views/checkout/cripto.blade.php --}}
@foreach ($wallets as $id => $wallet)
    @if ($wallet['web'])
        <div class="carteira">
            <img src="{{ $wallet['logo'] }}" alt="{{ $wallet['name'] }}">
            <p>{{ $wallet['name'] }}</p>
            {{-- SimpleQrCode desenha o SVG do QR diretamente a partir do link --}}
            {!! QrCode::size(220)->generate($wallet['web']) !!}
        </div>
    @endif
@endforeach
```

**Opção B — Gerar a imagem no browser (JavaScript), com uma lib tipo
[`qrcode`](https://www.npmjs.com/package/qrcode) (evita uma dependência PHP extra,
útil se o teu checkout for uma SPA/Vue/React):**

```html
<canvas id="qr-phantom"></canvas>

<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<script>
    // 'linkDaCarteira' é o valor de $wallet['web'] que já devolveste do backend
    // (por exemplo dentro de um @json($wallets) embutido na página).
    QRCode.toCanvas(document.getElementById('qr-phantom'), linkDaCarteira);
</script>
```

Em qualquer das opções, se detetares que o visitante está num **telemóvel**
(user agent, ou uma media query no frontend), o mais correto é usares
`h5` em vez de `web`, ou até nem mostrares QR nenhum e usares antes um botão
"Abrir carteira" apontado a `appUrl()` — porque num telemóvel não faz sentido
pedir ao utilizador para digitalizar um QR code com... o próprio telemóvel:

```php
$phantomAppLink = $charge->appUrl('phantom'); // deep-link direto, sem QR
```

```blade
<a href="{{ $phantomAppLink }}" class="btn">Abrir no Phantom</a>
```

**Vantagens:**
- Zero saída da tua aplicação — o pagamento acontece dentro da tua própria página.
- Controlo total do design (podes escolher quais carteiras mostrar, a ordem, o estilo).

**Desvantagens (o "trabalho extra"):**
- Tens de escolher e integrar uma biblioteca de geração de QR code (uma das
  duas acima, ou outra à tua escolha) — a RedotPay e o SDK não geram a imagem
  por ti, só o link.
- Tens de decidir tu, caso a caso, se mostras `web` (QR) ou `h5`/`appUrl()`
  (deep-link direto) consoante o dispositivo do visitante.
- Sempre que a RedotPay acrescentar/retirar carteiras suportadas, o array de
  `paymentMethods()` muda — a tua UI tem de lidar bem com isso (não assumas
  sempre as mesmas 13 carteiras).

#### Qual escolher?

| | Fluxo 1 (redirecionar) | Fluxo 2 (QR embutido) |
|---|---|---|
| Trabalho de implementação | Nenhum além de `charge()->payUrl()` | Escolher/integrar uma lib de QR, decidir web vs h5 por dispositivo |
| Onde o utilizador vê o QR | Página da RedotPay (fora da tua app) | Dentro da tua própria página |
| Manutenção ao longo do tempo | Nenhuma (a RedotPay atualiza a página dela) | Tens de acompanhar mudanças no array `paymentMethods()` |
| Recomendado para | A maioria das apps — arranca em minutos | Apps onde a experiência de marca dentro do checkout é crítica |

### Exemplo completo: checkout em cripto próprio (Fluxo 1 — redirecionar)

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

O `ChargeResult` devolvido por `charge()` expõe:

| Método | Devolve | Usa em |
|---|---|---|
| `payUrl()` | Link da página de checkout da RedotPay | Fluxo 1 (redirecionar) |
| `paymentMethods()` | Array completo das carteiras suportadas (nome, logótipo, links) | Fluxo 2 (QR próprio) — dados em bruto |
| `qrCodeUrls()` | Igual, mas simplificado: `[id => ['name','logo','web','h5']]` | Fluxo 2 (QR próprio) — atalho |
| `appUrl(string $walletId)` | Deep-link direto de uma carteira específica | Fluxo 2, quando o visitante já está no telemóvel |
| `reference()` / `entity()` | Referência/entidade Multicaixa | Método `REF` |
| `expiresAt()` | Validade da cobrança (ISO 8601) | Todos os métodos |
| `paymentMethod()` | O método usado (`'RDP'`, `'REF'`...) | Todos os métodos |
| `isMock()` | Se foi uma simulação de sandbox | Todos os métodos |
| `data()` | Payload bruto devolvido pelo gateway | Depuração / casos avançados |

Também funciona como array simples (`$charge['data']['pay_url']`), se preferires não usar os métodos.

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
