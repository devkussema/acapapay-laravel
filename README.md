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
    $payload = $event->payload;
    
    $localUserId = $payload['metadata']['local_user_id'];
    $faturaId = $payload['invoice_id'];
    
    // Atualiza a tua Base de Dados local:
    // Order::where('user_id', $localUserId)->update(['status' => 'paid']);
}
```

---

## Licença

Distribuído sob a licença **MIT**.
