# 4. Pagamentos em USD e Criptomoeda (RedotPay)

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).

O AcapaPay suporta pagamentos em **Dólar Americano (USD)** e **criptomoeda (stablecoins)** através da integração com a **RedotPay**. Isto permite que os teus utilizadores paguem com USDT, USDC e outros ativos digitais, através de qualquer carteira suportada (MetaMask, Phantom, Trust Wallet, TronLink, etc.).

## Regras importantes

- O método correspondente é `PaymentMethod::RDP` (constante de `AcapaPay\Laravel\Enums\PaymentMethod`).
- **A RedotPay exige faturas em USD.** Se criares uma fatura em `AOA` e pedires `RDP`, a API devolve `422` com uma mensagem explícita — cria sempre a fatura com `currency: Currency::USD`.
- A validade de uma cobrança RDP é de **1 hora** a partir da criação.
- **Limite de valor por fatura.** O contrato da RedotPay limita cada linha de produto a
  menos de 10 000 (`0 < goodsAmount < 10000`) e a ordem a 10 linhas. O AcapaPay reparte o
  total da tua fatura pelas linhas necessárias automaticamente, mas isso impõe um tecto de
  **99 999,90 USD por fatura**. Acima disso a cobrança falha com uma mensagem explícita —
  divide o valor por várias faturas.
- **Para onde volta o cliente depois de pagar:** o `success_url` que envias ao criar a
  fatura é o `redirectUrl` que a RedotPay usa. Se não o enviares, o cliente fica na página
  de pagamento do AcapaPay em vez de voltar à tua app.

> [!IMPORTANT]
> **O webhook da RedotPay configura-se no painel deles, não por código.** O pedido de
> criação de ordem da RedotPay não tem campo nenhum para URL de callback — só
> `redirectUrl`. O endereço para onde a RedotPay envia a confirmação do pagamento é
> definido em `business.redotpay.com`, na conta do comerciante. Isto é do lado do AcapaPay
> (não teu): o `invoice.paid` que a **tua** app recebe continua a ser configurado
> normalmente no painel Developer, e chega na mesma por reconciliação mesmo que o callback
> da RedotPay falhe.

### Em sandbox (`ACAPAPAY_MODO=sandbox`)

Ao contrário de REF/GPO/EKZ, o RDP **não** é simulado quando a RedotPay está configurada
do lado do AcapaPay: recebes um link de pagamento genuíno do ambiente de sandbox da
RedotPay, onde podes pagar com carteiras de teste. Só se a RedotPay não estiver configurada
é que recebes um mock (`is_mock: true`). Em qualquer dos casos, `direct()->simulate()`
continua a liquidar a fatura e a disparar o `invoice.paid`.

## Checkout com USD/Cripto (via checkout hospedado)

Para iniciar um pagamento em dólar via RedotPay usando o [checkout hospedado](02-checkout-hospedado.md), especifica a moeda e o método preferido:

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\Currency;
use AcapaPay\Laravel\Enums\PaymentMethod;

$url = AcapaPay::checkoutSession(
    userId: auth()->id(),
    planReference: 'PRO_YEARLY',
    metadata: ['projeto' => 'minha-app'],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado'),
    currency: Currency::USD,
    preferredMethod: PaymentMethod::RDP,
);

return redirect($url);
```

Ou, para uma fatura avulsa:

```php
$url = AcapaPay::createInvoice(
    userId: auth()->id(),
    amount: 49.99,
    description: 'Consultoria Premium - 1 hora',
    currency: Currency::USD,
    preferredMethod: PaymentMethod::RDP,
    metadata: ['sessao_id' => $sessaoId],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado')
);

return redirect($url);
```

### Configuração global (se cobras sempre em USD/cripto)

```env
ACAPAPAY_PREFERRED_CURRENCY=USD
ACAPAPAY_PREFERRED_METHOD=RDP
```

Assim não precisas de especificar `currency`/`preferredMethod` em cada chamada.

### Tabela de referência dos métodos

| Constante | Valor | Método | Moeda | Validade da cobrança |
|---|---|---|---|---|
| `PaymentMethod::REF` | `REF` | Referência Multicaixa | AOA | ~3 dias |
| `PaymentMethod::GPO` | `GPO` | Multicaixa Express | AOA | 60 segundos |
| `PaymentMethod::EKZ` | `EKZ` | E-Kwanza | AOA | 5 minutos |
| `PaymentMethod::RDP` | `RDP` | **RedotPay (cripto)** | **USD** | 1 hora |

---

## Como apresentar o pagamento ao utilizador: 2 fluxos possíveis

Isto aplica-se especificamente ao método `RDP`, que é o único onde a diferença entre os dois fluxos é significativa — para `REF`/`GPO`/`EKZ` a [API direta](03-pagamento-direto-api.md) já te dá a referência/ticket em texto, pronta a mostrar, sem QR nem redirecionamento externo.

Quando geras uma cobrança RDP através da [API direta](03-pagamento-direto-api.md) (`AcapaPay::direct()->charge($invoiceId, PaymentMethod::RDP)`), tens de escolher **uma de duas formas** de levar o utilizador a pagar. Nenhuma é objetivamente "melhor" — a escolha depende de quanto controlo visual precisas e de quanto trabalho de implementação estás disposto a fazer.

### Fluxo 1 — Redirecionar (recomendado por omissão)

É o mais simples, e é o que a nossa própria página de checkout hospedado usa internamente. Chamas `charge()`, recebes um `payUrl()`, e envias o utilizador para lá — ponto final.

```php
$charge = AcapaPay::direct()->charge($invoiceId, PaymentMethod::RDP);

return redirect($charge->payUrl());
// ou, se estiveres a responder a um pedido AJAX/fetch do teu frontend:
// return response()->json(['pay_url' => $charge->payUrl()]);
// e no frontend: window.open(data.pay_url, '_blank');
```

O que o utilizador vê ao chegar a esse `payUrl()`: uma página **hospedada pela RedotPay** (não é nossa, nem tua) com a lista de carteiras suportadas (MetaMask, Phantom, Trust Wallet, Coinbase, TronLink, etc.), cada uma já com o seu próprio QR code desenhado e pronto a digitalizar. A RedotPay trata de tudo: gerar as imagens de QR, detetar se o visitante está em mobile ou desktop, abrir o deep-link correto se tiver a carteira instalada no telemóvel, etc.

**Vantagens:**
- Zero código extra do lado da UI — só precisas do link.
- A RedotPay mantém aquela página atualizada (novas carteiras, correções de segurança, etc.) sem tu teres de mexer em nada.
- Funciona logo em mobile e desktop, sem teres de te preocupar com qual QR mostrar em cada caso.

**Desvantagem:**
- O utilizador sai da tua aplicação/domínio durante um instante (vê o URL da RedotPay na barra de endereços, ou vês o iframe/separador novo). Se a tua marca for muito importante nesse momento, isto pode incomodar.

### Fluxo 2 — Construir a tua própria interface com o QR code embutido

Se quiseres que o utilizador **nunca saia da tua página** — por exemplo, mostrar o QR code dentro de um modal da tua app, ao lado do teu logótipo — usa `paymentMethods()` ou `qrCodeUrls()` no `ChargeResult` devolvido por `charge()`.

> [!WARNING]
> **Ponto mais importante deste fluxo, lê com atenção:** os campos `webQrCode` e `h5QrCode` que a RedotPay devolve **não são imagens**. São *links* (deep-links de carteira, do tipo `https://phantom.app/ul/browse/...`). A RedotPay não gera nenhuma imagem de QR code para ti — quem tem de gerar a imagem, a partir desse link de texto, **és tu**. Isto é o "trabalho extra" de que se fala mais abaixo: sem esse passo de geração da imagem, não tens QR code nenhum para mostrar, só um link em bruto.

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
    // ... normalmente 12-13 carteiras (confirmado com uma chamada real ao ambiente sandbox:
    // metamask, binance, imtoken, okx, trust, bitget, tokenpocket, coinbase, phantom,
    // rainbow, tronlink, redotpay, coinbasepay)
]
*/

return view('checkout.cripto', [
    'wallets'   => $wallets,
    'invoiceId' => $invoiceId,
]);
```

Estrutura de cada item em `paymentMethods()` (a versão não simplificada; `qrCodeUrls()` extrai só os campos abaixo):

```json
{
  "id": "phantom",
  "name": "Phantom",
  "manual": false,
  "appName": "Phantom",
  "logo": "https://staticsource1.redotpay.com/aquirer/wallets/phantom.svg",
  "webUrl": "https://phantom.app/ul/browse/...",
  "webQrCode": "https://phantom.app/ul/browse/...",
  "h5Url": "https://phantom.app/ul/browse/...",
  "h5QrCode": "https://phantom.app/ul/browse/...",
  "appUrl": "https://phantom.app/ul/browse/...",
  "appQrCode": null,
  "paymentVariants": null
}
```

### Gerar a imagem do QR code

Há duas formas de o fazer — escolhe consoante onde preferes que o trabalho aconteça:

**Opção A — Gerar no servidor (PHP), com [`simplesoftwareio/simple-qrcode`](https://github.com/SimpleSoftwareIO/simple-qrcode):**

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

**Opção B — Gerar no browser (JavaScript), com uma lib tipo [`qrcode`](https://www.npmjs.com/package/qrcode):** útil se o teu checkout for uma SPA (Vue/React) e quiseres evitar uma dependência PHP extra.

```html
<canvas id="qr-phantom"></canvas>

<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<script>
    // 'linkDaCarteira' é o valor de $wallet['web'] que já devolveste do backend
    // (por exemplo dentro de um @json($wallets) embutido na página).
    QRCode.toCanvas(document.getElementById('qr-phantom'), linkDaCarteira);
</script>
```

### QR vs. deep-link direto (desktop vs. mobile)

Se detetares que o visitante está num **telemóvel** (user agent, ou uma media query no frontend), o mais correto é usares `h5` em vez de `web`, ou até nem mostrares QR nenhum e usares antes um botão "Abrir carteira" apontado a `appUrl()` — porque num telemóvel não faz sentido pedir ao utilizador para digitalizar um QR code com o próprio telemóvel:

```php
$phantomAppLink = $charge->appUrl('phantom'); // deep-link direto, sem QR
```

```blade
<a href="{{ $phantomAppLink }}" class="btn">Abrir no Phantom</a>
```

### Vantagens e desvantagens do Fluxo 2

**Vantagens:**
- Zero saída da tua aplicação — o pagamento acontece dentro da tua própria página.
- Controlo total do design (podes escolher quais carteiras mostrar, a ordem, o estilo).

**Desvantagens ("trabalho extra"):**
- Tens de escolher e integrar uma biblioteca de geração de QR code — a RedotPay e o SDK não geram a imagem por ti, só o link.
- Tens de decidir tu, caso a caso, se mostras `web` (QR) ou `h5`/`appUrl()` (deep-link direto) consoante o dispositivo do visitante.
- Sempre que a RedotPay acrescentar/retirar carteiras suportadas, o array de `paymentMethods()` muda — a tua UI tem de lidar bem com isso (não assumas sempre as mesmas 12-13 carteiras).

### Qual escolher?

| | Fluxo 1 (redirecionar) | Fluxo 2 (QR embutido) |
|---|---|---|
| Trabalho de implementação | Nenhum além de `charge()->payUrl()` | Escolher/integrar uma lib de QR, decidir web vs h5 por dispositivo |
| Onde o utilizador vê o QR | Página da RedotPay (fora da tua app) | Dentro da tua própria página |
| Manutenção ao longo do tempo | Nenhuma (a RedotPay atualiza a página dela) | Tens de acompanhar mudanças no array `paymentMethods()` |
| Recomendado para | A maioria das apps — arranca em minutos | Apps onde a experiência de marca dentro do checkout é crítica |

---

## Um terceiro método da RedotPay que este pacote **não** cobre: "Payment Links" do dashboard

A RedotPay também tem, no próprio painel deles (`business.redotpay.com` → menu "Payment links"), uma funcionalidade **no-code**: o comerciante cria manualmente, na interface web da RedotPay, um link de pagamento reutilizável (valor fixo ou aberto) — **sem nenhuma chamada à nossa API**.

Este pacote **não gera nem gere isso**, e propositadamente não tenta — como esse link é criado à mão no painel da RedotPay, não fica ligado a nenhuma das tuas faturas (`Invoice`) nem ao `outerOrderSn` usado internamente, por isso não entraria na reconciliação/webhook automática do AcapaPay. Se precisares desse tipo de link (ex: para uma doação avulsa fora do teu fluxo normal de faturação), terás de o criar e geri-lo diretamente no painel da RedotPay, como um processo totalmente à parte do AcapaPay.

---

**A seguir:** [05-webhooks-e-eventos.md](05-webhooks-e-eventos.md) — confirmar pagamentos de forma assíncrona e fiável.
