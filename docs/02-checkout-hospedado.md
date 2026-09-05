# 2. Checkout Hospedado (redirecionar ou iFrame)

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).

Esta é a forma mais rápida de aceitar pagamentos: o utilizador é enviado para uma página de pagamento já pronta, hospedada pelo Acapadev SSO. Não precisas de construir nenhuma interface de checkout.

> Se preferires construir a tua própria interface (mostrar a referência, o QR code, etc., sem sair da tua página), ver [03-pagamento-direto-api.md](03-pagamento-direto-api.md).

## Iniciar um pagamento (plano/subscrição)

O pacote regista uma Facade global `AcapaPay`. Para criar uma sessão de checkout e obteres o URL para onde enviar o utilizador, usa `checkoutSession()`:

```php
AcapaPay::checkoutSession(
    mixed  $userId,               // ID do utilizador na tua base de dados
    string $planReference,        // Referência do plano (ex: 'PRO_MONTHLY')
    array  $metadata = [],        // Dados extras devolvidos no webhook
    ?string $successUrl = null,   // URL de retorno após pagamento
    ?string $cancelUrl = null,    // URL de retorno se o utilizador cancelar
    ?string $currency = null,     // 'AOA' (padrão) ou 'USD'
    ?string $preferredMethod = null, // Ver 04-pagamentos-cripto-redotpay.md
): string // Devolve o URL da sessão de checkout
```

### Exemplo completo no controller

```php
<?php

namespace App\Http\Controllers;

use AcapaPay\Laravel\Facades\AcapaPay;

class PagamentoController extends Controller
{
    public function comprarPlano()
    {
        $userId = auth()->id();
        $plano = 'PRO_YEARLY';

        try {
            $urlDePagamento = AcapaPay::checkoutSession(
                $userId,
                $plano,
                ['minha_metadata' => '123'],
                url('/pagamento/sucesso'),
                url('/pagamento/cancelado')
            );

            return view('pagamento.checkout', compact('urlDePagamento'));

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
```

> **Metadata é a tua âncora:** tudo o que colocares em `$metadata` é devolvido integralmente no payload do webhook quando o pagamento for confirmado. Usa sempre um identificador teu (ex: `local_user_id`, `local_order_id`) para conseguires ligar o pagamento ao registo certo na tua base de dados.

## Fatura avulsa (sem plano)

Se quiseres cobrar um montante específico sem estar associado a um plano de catálogo, usa `createInvoice()`:

```php
$url = AcapaPay::createInvoice(
    userId: auth()->id(),
    amount: 49.99,
    description: 'Consultoria Premium - 1 hora',
    currency: 'AOA',            // ou 'USD' — ver 04-pagamentos-cripto-redotpay.md
    preferredMethod: null,
    metadata: ['sessao_id' => $sessaoId],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado')
);

return redirect($url);
```

## Embutir num iFrame (sem sair do teu site)

Em vez de redirecionar, podes manter o utilizador dentro do teu site com o componente Blade `<x-acapapay::iframe>`:

```blade
<x-layout>
    <h1>Concluir Pagamento</h1>

    <x-acapapay::iframe
        :checkout-url="$urlDePagamento"
        height="700px"
        width="100%"
    />
</x-layout>
```

### Props do componente

| Prop | Padrão | Descrição |
|---|---|---|
| `checkout-url` | *(obrigatório)* | O URL devolvido por `checkoutSession()`/`createInvoice()`. |
| `height` | `700px` | Altura do iFrame. |
| `width` | `100%` | Largura do iFrame. |
| `id` | gerado automaticamente | Útil se precisares de referenciar o iFrame no teu próprio JS; permite ter mais do que um checkout na mesma página. |

### Como funciona a comunicação (postMessage)

1. O utilizador completa o pagamento dentro do iFrame, no domínio do SSO.
2. O SSO confirma a transação e emite um evento `postMessage` com `{ event: 'acapapay.success' }` para a janela pai.
3. O componente escuta esse evento e dispara um `CustomEvent` nativo do browser: `acapapay-success` (e `acapapay-cancel` / `acapapay-status`, consoante o caso).
4. Tu escutas esse evento na tua página para reagir (ex: redirecionar, mostrar uma mensagem):

```javascript
window.addEventListener('acapapay-success', (e) => {
    console.log('Pagamento concluído!', e.detail);
    window.location.href = '/pagamento/sucesso';
});
```

> [!IMPORTANT]
> Desde a v1.2.0, o componente **valida a origem** (`event.origin`) das mensagens `postMessage` recebidas — só aceita mensagens vindas do domínio do checkout ou do `ACAPAPAY_HOST`. Se precisares de autorizar uma origem adicional (ex: um subdomínio de staging diferente), usa a config `iframe_allowed_origins` (ver [01-instalacao-e-configuracao.md](01-instalacao-e-configuracao.md)).

### Nota de segurança cross-origin

O SSO valida o `origin_domain` enviado durante a criação da sessão de checkout. Garante que o domínio da tua app está registado na configuração da OAuth App no painel SSO — caso contrário, o browser bloqueia o iFrame por política de segurança (CSP / X-Frame-Options).

---

**A seguir:** [03-pagamento-direto-api.md](03-pagamento-direto-api.md) — construir a tua própria interface de pagamento, sem redirecionar nem usar iFrame.
