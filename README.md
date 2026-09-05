# AcapaPay Laravel SDK

O **AcapaPay Laravel SDK** é a biblioteca oficial para integrar de forma rápida e segura a gateway de pagamentos centralizada do ecossistema AcapaDev em qualquer projeto baseado no Laravel — Multicaixa (Referência e Express), E-Kwanza, e pagamentos em USD/criptomoeda via RedotPay.

> [!WARNING]
> **Compatibilidade exclusiva:** este pacote foi desenhado exclusivamente para o **Laravel Framework**. Tem uma proteção em runtime que impede a sua execução em ambientes PHP puro ou noutras frameworks.

## Funcionalidades principais

- **Autenticação automática** — gestão transparente de tokens M2M (OAuth2 Client Credentials), com cache, retentativa automática em 401, e retentativa em falhas de rede.
- **Checkout hospedado** — uma chamada (`checkoutSession()`) devolve um URL pronto a redirecionar ou a embutir num `<x-acapapay::iframe>`.
- **API de pagamento direta** — constrói a tua própria interface de checkout (`AcapaPay::direct()`), sem redirecionar nem usar iFrame.
- **Pagamentos em USD/criptomoeda (RedotPay)** — com duas formas de apresentar ao utilizador: redirecionar, ou o teu próprio QR code embutido.
- **Webhooks validados por HMAC**, com eventos nativos do Laravel (`AcapaPayPaymentReceived`, `AcapaPayInvoicePaid`, `AcapaPayInvoiceFailed`, `AcapaPayInvoiceExpired`).
- **Exceções tipadas** (`ValidationException`, `AuthenticationException`, etc.), todas compatíveis com `catch (\Exception $e)`.
- **Modo Sandbox** para testar todo o fluxo sem gerar cobranças reais.
- **CLI de diagnóstico** (`acapapay:test-connection`) e de sincronização de planos (`acapapay:sync-plans`).

## Instalação rápida

```bash
composer require devkussema/acapapay-laravel
```

```env
ACAPAPAY_HOST=https://id.acapadev.com
ACAPAPAY_API_HOST=https://api.acapadev.com
ACAPAPAY_CLIENT_ID=o-teu-client-id
ACAPAPAY_CLIENT_SECRET=o-teu-client-secret
ACAPAPAY_WEBHOOK_SECRET=o-teu-webhook-secret
```

```bash
php artisan acapapay:test-connection
```

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$url = AcapaPay::checkoutSession(
    auth()->id(),
    'PRO_MONTHLY',
    [],
    url('/pagamento/sucesso'),
    url('/pagamento/cancelado')
);

return redirect($url);
```

Isto é só o essencial para arrancar — para tudo o resto (config completa, iFrame, API direta, cripto, webhooks, erros, sandbox, sync de planos), ver a documentação completa abaixo.

## 📚 Documentação Completa

| # | Ficheiro | Conteúdo |
|---|---|---|
| 1 | [Instalação e Configuração](docs/01-instalacao-e-configuracao.md) | Requisitos, `.env`, todas as chaves de config, comando de diagnóstico. |
| 2 | [Checkout Hospedado](docs/02-checkout-hospedado.md) | `checkoutSession()`, `createInvoice()`, componente `<x-acapapay::iframe>`. |
| 3 | [API de Pagamento Direta](docs/03-pagamento-direto-api.md) | Construir o teu próprio checkout: `AcapaPay::direct()`, `ChargeResult`, polling. |
| 4 | [Pagamentos em USD/Cripto (RedotPay)](docs/04-pagamentos-cripto-redotpay.md) | Os **2 fluxos** de apresentar o pagamento (redirecionar vs. QR próprio), geração de QR code, e o que o pacote **não** cobre (Payment Links do dashboard RedotPay). |
| 5 | [Webhooks e Eventos](docs/05-webhooks-e-eventos.md) | Como o webhook funciona, todos os eventos disponíveis, exemplos de listener. |
| 6 | [Tratamento de Erros](docs/06-tratamento-de-erros.md) | A hierarquia de exceções e como as apanhar. |
| 7 | [Sandbox e Testes](docs/07-sandbox-e-testes.md) | Simular pagamentos sem gerar cobranças reais, webhooks locais com túnel. |
| 8 | [Sincronização de Planos](docs/08-sincronizacao-de-planos.md) | `acapapay:sync-plans`, `toAcapaPayFormat()`. |
| 9 | [Referência Rápida](docs/09-referencia-rapida.md) | Todas as tabelas (métodos, eventos, exceções, config) num só sítio. |

> Estes ficheiros são a **única fonte de documentação mantida** deste pacote (substituem o antigo `docs.html`, que ficou desatualizado e foi removido). Se encontrares alguma divergência entre esta documentação e o código, por favor abre uma *issue*.

## Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para o histórico de versões.

## Licença

Distribuído sob a licença **MIT**.
