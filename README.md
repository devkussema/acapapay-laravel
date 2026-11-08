# AcapaPay Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devkussema/acapapay-laravel.svg?style=flat-square)](https://packagist.org/packages/devkussema/acapapay-laravel)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

O **AcapaPay Laravel SDK** é a biblioteca oficial para integrar de forma rápida, robusta e segura a gateway de pagamentos centralizada do ecossistema **AcapaDev ID** em qualquer projeto baseado no Laravel.

Suporta **Multicaixa Express (GPO)** e **Referência Multicaixa (REF)** via AppyPay / EMIS, pagamentos internacionais em USD e criptomoeda via **RedotPay (RDP)**, **WiPay (WIP)** e **Reembolsos Automatizados (Refunds)** com estorno direto no cartão do cliente.

> [!WARNING]
> **Compatibilidade exclusiva:** este pacote foi desenhado exclusivamente para o **Laravel Framework** (versões 9.x a 14.x) com PHP 8.1+. Tem uma proteção em runtime que impede a sua execução em ambientes PHP puro ou noutras frameworks.

## Funcionalidades principais

- **Autenticação automática** — gestão transparente de tokens M2M (OAuth2 Client Credentials), com cache, retentativa automática em 401 e retentativa em falhas de rede.
- **Reembolsos Automatizados (Novo na v1.5.0)** — estornos totais ou parciais de cobranças (`AcapaPay::refund()`) com crédito imediato no cartão do cliente via Multicaixa Express.
- **Checkout hospedado** — uma chamada (`AcapaPay::checkoutSession()`) devolve um URL pronto a redirecionar ou a embutir num `<x-acapapay::iframe>`.
- **API de pagamento direta (Headless)** — constrói a tua própria interface de checkout (`AcapaPay::direct()`), sem redirecionar nem usar iFrame.
- **Pagamentos em USD/criptomoeda (RedotPay)** — liquidação em USDT/USDC/BTC com interface pronta ou QR code nativo.
- **Webhooks validados por HMAC-SHA256**, com eventos nativos do Laravel (`AcapaPayPaymentReceived`, `AcapaPayInvoicePaid`, `AcapaPayInvoiceRefunded`, `AcapaPayInvoiceFailed`, `AcapaPayInvoiceExpired`).
- **Exceções tipadas** (`ValidationException`, `AuthenticationException`, `ForbiddenException`, etc.), todas compatíveis com `catch (\Exception $e)`.
- **Modo Sandbox** para testar todo o ciclo de vida (cobrança, liquidação, estorno) sem dinheiro real.
- **CLI de diagnóstico e sincronização** (`acapapay:test-connection` e `acapapay:sync-plans`).

## Instalação rápida

```bash
composer require devkussema/acapapay-laravel
```

```env
ACAPAPAY_MODO=sandbox
ACAPAPAY_CLIENT_ID=o-teu-client-id
ACAPAPAY_CLIENT_SECRET=o-teu-client-secret
ACAPAPAY_WEBHOOK_SECRET=o-teu-webhook-secret
ACAPAPAY_BASE_URL=https://id.acapadev.me
```

```bash
php artisan acapapay:test-connection
```

```php
use AcapaPay\Laravel\Facades\AcapaPay;

$url = AcapaPay::checkoutSession(
    userId: auth()->id(),
    planReference: 'PRO_MONTHLY',
    metadata: ['empresa_id' => 12],
    successUrl: url('/pagamento/sucesso'),
    cancelUrl: url('/pagamento/cancelado')
);

return redirect()->away($url);
```

---

## 📚 Documentação Oficial (v1.5.0 — Novembro 2026)

A documentação completa, versionada e atualizada está organizada na pasta [`docs/v1.5.0/`](docs/v1.5.0/):

| # | Ficheiro | Conteúdo |
|---|---|---|
| 01 | [Introdução e Arquitetura](docs/v1.5.0/01-introducao-e-arquitetura.md) | Visão geral do ecossistema, modos de integração e ciclo de vida. |
| 02 | [Instalação e Configuração](docs/v1.5.0/02-instalacao-e-configuracao.md) | Requisitos, `.env`, publicação de config e teste de conectividade. |
| 03 | [Checkout Hospedado](docs/v1.5.0/03-checkout-hospedado.md) | `checkoutSession()`, `createInvoice()`, componentes Blade e iFrame. |
| 04 | [Pagamento Direto via API](docs/v1.5.0/04-pagamento-direto-api.md) | Checkout Headless: Multicaixa Express (GPO), Referência (REF), Cripto (RDP). |
| 05 | [Reembolsos e Estornos (Novo)](docs/v1.5.0/05-reembolsos-e-estornos.md) | Estorno total e parcial via API (`AcapaPay::refund()`), regras e TPA Virtual. |
| 06 | [Webhooks e Eventos](docs/v1.5.0/06-webhooks-e-eventos.md) | Assinatura HMAC, todos os eventos do SDK e Listeners práticos. |
| 07 | [Sandbox e Testes](docs/v1.5.0/07-sandbox-testes-e-simulacao.md) | Simulações instantâneas (`simulate()`), estornos de teste e túneis locais. |
| 08 | [Sincronização de Planos](docs/v1.5.0/08-sincronizacao-de-planos.md) | Gestão centralizada de planos no AcapaDev ID (`acapapay:sync-plans`). |
| 09 | [Tratamento de Erros](docs/v1.5.0/09-tratamento-de-erros-e-resiliencia.md) | Hierarquia de exceções, resiliência e boas práticas. |
| 10 | [Referência Rápida (Cheat Sheet)](docs/v1.5.0/10-referencia-rapida.md) | Resumo de métodos da Facade, API direta, Enums e payloads. |

> Versões legadas de documentação encontram-se arquivadas em [`docs/v1.4.0/`](docs/v1.4.0/).

---

## Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para o histórico completo de versões.

## Licença

Distribuído sob a licença **MIT**.
