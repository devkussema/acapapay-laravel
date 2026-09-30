# 1. Checkout Hospedado (WiPay + RedotPay)

> **Esta é a documentação v2 do pacote** — cobre só **WiPay (WIP)** e **RedotPay (RDP)**, os dois métodos de checkout hospedado (`pay_url`). Se a tua integração ainda usa REF/GPO/EKZ, consulta a [documentação v1](../02-checkout-hospedado.md).
>
> Faz parte da documentação v2 do [`devkussema/acapapay-laravel`](../../README.md). Ver o [índice v2](../../README.md#-documentação-v2-recomendado--wipay--redotpay).

O AcapaDev ID suporta cinco métodos de pagamento no total (REF, GPO, EKZ, RDP, WIP), mas esta secção da documentação foca-se nos dois métodos **hospedados** — aqueles em que o gateway externo devolve um `pay_url` para onde o cliente é enviado (ou onde é embutido num iFrame), em vez de uma referência/ticket mostrada diretamente na tua UI:

| Constante | Valor | Gateway | Moeda | Consulta de estado | QR code |
|---|---|---|---|---|---|
| `PaymentMethod::RDP` | `RDP` | RedotPay (cripto/stablecoins) | **USD** | Sim (`status()`) | Sim (carteiras cripto) |
| `PaymentMethod::WIP` | `WIP` | WiPay | **AOA** | **Não** — só webhook | Não |

> [!IMPORTANT]
> O **WIP não tem endpoint de consulta de estado**. Ao contrário do RDP (e de REF/GPO/EKZ), não há `status()`/`isPaid()` fiável para o WIP — a **única** confirmação é o [webhook `invoice.paid`](03-webhooks-e-eventos.md). Não construas UI que dependa de polling para este método.

## Iniciar um pagamento (plano/subscrição)

```php
use AcapaPay\Laravel\Facades\AcapaPay;
use AcapaPay\Laravel\Enums\Currency;
use AcapaPay\Laravel\Enums\PaymentMethod;

// WiPay — checkout hospedado, AOA
$url = AcapaPay::checkoutSession(
    userId: auth()->id(),
    planReference: 'PRO_MONTHLY',
    metadata: ['local_user_id' => auth()->id()],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado'),
    currency: Currency::AOA,
    preferredMethod: PaymentMethod::WIP,
);

return redirect($url);
```

```php
// RedotPay — cripto/stablecoins, USD
$url = AcapaPay::checkoutSession(
    userId: auth()->id(),
    planReference: 'PRO_YEARLY',
    metadata: ['local_user_id' => auth()->id()],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado'),
    currency: Currency::USD,
    preferredMethod: PaymentMethod::RDP,
);

return redirect($url);
```

> **Metadata é a tua âncora:** tudo o que colocares em `$metadata` é devolvido integralmente no payload do webhook quando o pagamento for confirmado. Usa sempre um identificador teu (ex: `local_user_id`, `local_order_id`).

## Fatura avulsa (sem plano)

```php
$url = AcapaPay::createInvoice(
    userId: auth()->id(),
    amount: 5000,
    description: 'Consultoria Premium - 1 hora',
    currency: Currency::AOA,
    preferredMethod: PaymentMethod::WIP,
    metadata: ['sessao_id' => $sessaoId],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado')
);

return redirect($url);
```

### Configuração global (se cobras sempre no mesmo método)

```env
# Para cobrar sempre via WiPay (AOA):
ACAPAPAY_PREFERRED_CURRENCY=AOA
ACAPAPAY_PREFERRED_METHOD=WIP

# Ou, para cobrar sempre via RedotPay (USD):
ACAPAPAY_PREFERRED_CURRENCY=USD
ACAPAPAY_PREFERRED_METHOD=RDP
```

## Embutir num iFrame (sem sair do teu site)

Funciona da mesma forma para WIP e RDP — o componente Blade `<x-acapapay::iframe>` embute o `pay_url` devolvido:

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

| Prop | Padrão | Descrição |
|---|---|---|
| `checkout-url` | *(obrigatório)* | O URL devolvido por `checkoutSession()`/`createInvoice()`, ou `ChargeResult::payUrl()`. |
| `height` | `700px` | Altura do iFrame. |
| `width` | `100%` | Largura do iFrame. |
| `id` | gerado automaticamente | Útil para múltiplos checkouts na mesma página. |

O componente escuta `postMessage` do domínio do checkout e dispara `acapapay-success` / `acapapay-cancel` / `acapapay-status` como `CustomEvent` do browser:

```javascript
window.addEventListener('acapapay-success', (e) => {
    console.log('Pagamento concluído!', e.detail);
    window.location.href = '/pagamento/sucesso';
});
```

> [!NOTE]
> Tal como no RDP, a confirmação **definitiva** de um pagamento WIP é sempre o webhook — o evento `acapapay-success` no iFrame é só para feedback imediato na UI. Ver [03-webhooks-e-eventos.md](03-webhooks-e-eventos.md).

---

**A seguir:** [02-pagamento-direto-api.md](02-pagamento-direto-api.md) — construir a tua própria interface de pagamento, sem redirecionar nem usar iFrame.
