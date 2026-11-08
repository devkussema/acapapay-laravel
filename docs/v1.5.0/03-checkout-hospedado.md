# AcapaPay Laravel SDK — Checkout Hospedado

* **Versão:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026

---

## 1. Visão Geral

O **Checkout Hospedado** é a forma mais rápida e segura de aceitar pagamentos. O cliente é redirecionado para a página de checkout hospedada pelo AcapaPay (`https://id.acapadev.me/checkout/...`), onde dispõe de uma interface otimizada para:
- Escolha intuitiva entre **Multicaixa Express (GPO)**, **Referência Multicaixa (REF)** e **Criptomoeda/USD (RedotPay)**.
- Temporizadores regressivos em tempo real e atualização automática de estado via WebSockets/Polling.
- Redirecionamento automático após confirmação de pagamento para o URL configurado na sua app.

---

## 2. Criar uma Sessão de Checkout (`checkoutSession`)

Utilize o método `checkoutSession()` para subscrições associadas a planos do catálogo:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

class SubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $user = $request->user();
        
        $checkoutUrl = AcapaPay::checkoutSession(
            userId: $user->id,
            planReference: 'pro_monthly', // Código de referência do plano no AcapaDev ID
            metadata: [
                'organization_id' => $user->organization_id,
                'tier' => 'professional',
            ],
            successUrl: route('billing.success'),
            cancelUrl: route('billing.cancel'),
            currency: 'AOA', // Ou 'USD' para pagamentos internacionais
            preferredMethod: 'GPO' // Opcional: abre com Multicaixa Express pré-selecionado
        );

        // Redireciona o utilizador para a página do AcapaPay
        return redirect()->away($checkoutUrl);
    }
}
```

---

## 3. Criar uma Fatura Hospedada com Itens (`createInvoice`)

Se pretende cobrar um valor avulso, um carrinho de compras com múltiplos itens ou um serviço personalizado, utilize `createInvoice()`:

```php
use AcapaPay\Laravel\Facades\AcapaPay;

class OrderController extends Controller
{
    public function checkout(Order $order)
    {
        $payUrl = AcapaPay::createInvoice(
            userId: $order->user_id,
            amount: $order->total_amount, // ex: 25000.00
            description: "Encomenda #{$order->number} - Loja Online",
            currency: 'AOA', // 'AOA' ou 'USD'
            preferredMethod: 'GPO',
            metadata: [
                'order_id' => $order->id,
                'customer_nif' => $order->customer_nif,
            ],
            successUrl: route('orders.thankyou', $order->id),
            cancelUrl: route('orders.cancelled', $order->id)
        );

        return redirect()->away($payUrl);
    }
}
```

---

## 4. Integração em Modal / iFrame

Se preferir manter o utilizador no mesmo ecrã sem mudar de separador, o URL devolvido pode ser incorporado num iFrame ou modal HTML:

```html
<iframe 
    src="{{ $checkoutUrl }}" 
    width="100%" 
    height="700px" 
    frameborder="0" 
    allow="payment">
</iframe>
```

> [!NOTE]
> O servidor do AcapaDev ID valida a origem HTTP (`Sec-Fetch-Site` e `Origin`) contra as origens autorizadas da sua aplicação no Portal do Desenvolvedor.
