# AcapaPay Laravel SDK — Instalação e Configuração

* **Versão:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026
* **Requisitos:** PHP 8.1+, Laravel 9.x a 14.x

---

## 1. Instalação via Composer

Instale o pacote no seu projeto Laravel utilizando o Composer:

```bash
composer require devkussema/acapapay-laravel
```

O Service Provider e o Facade `AcapaPay` são registados automaticamente pelo mecanismo de Package Discovery do Laravel.

---

## 2. Publicação da Configuração

Publique o ficheiro de configuração `config/acapapay.php`:

```bash
php artisan vendor:publish --tag=acapapay-config
```

Este comando cria o ficheiro `config/acapapay.php` com as definições padrão da plataforma.

---

## 3. Variáveis de Ambiente (`.env`)

Obtenha as credenciais da sua aplicação no **Portal do Desenvolvedor do AcapaDev ID** (`https://id.acapadev.me/apps` ou no seu ambiente local/staging).

Adicione as seguintes variáveis no ficheiro `.env` da sua aplicação:

```env
# ==========================================
# ACAPAPAY PAYMENT GATEWAY & BILLING
# ==========================================

# Modo de operação: 'sandbox' (desenvolvimento/testes) ou 'live' (produção)
ACAPAPAY_MODO=sandbox

# Credenciais OAuth2 M2M (Client Credentials)
ACAPAPAY_CLIENT_ID=seu_client_id_aqui
ACAPAPAY_CLIENT_SECRET=seu_client_secret_em_texto_limpo_aqui

# Segredo para validação de assinatura dos Webhooks (HMAC-SHA256)
ACAPAPAY_WEBHOOK_SECRET=whsec_seu_segredo_de_webhook_aqui

# URL base do servidor AcapaDev ID (Padrão: https://id.acapadev.me)
ACAPAPAY_BASE_URL=https://id.acapadev.me

# Moeda padrão da aplicação (AOA ou USD)
ACAPAPAY_DEFAULT_CURRENCY=AOA

# Método preferido opcional (GPO, REF, RDP, WIP)
ACAPAPAY_PREFERRED_METHOD=GPO
```

> [!IMPORTANT]
> **Segredo do Webhook (`ACAPAPAY_WEBHOOK_SECRET`):** Este segredo é gerado na secção **Webhooks** da sua aplicação no Developer Portal. O SDK rejeitará qualquer webhook cujo cabeçalho `X-AcapaDev-Signature` não corresponda exatamente ao HMAC do payload com este segredo.

---

## 4. Rotação de Chaves e Migração entre Ambientes

* **Chaves Visíveis:** No Developer Portal do AcapaDev ID, ao gerar ou regenerar o segredo, a chave é apresentada em texto legível para permitir cópia imediata.
* **Migração de Ambiente:** Se a sua app foi desenvolvida em `test` (sandbox) e pretende movê-la para `live` (produção) mantendo as mesmas credenciais e configurações, utilize o botão **"Migrar App para Live (Produção)"** no ecrã de detalhes da sua aplicação no Portal do Desenvolvedor.

---

## 5. Teste de Conexão (Ping)

Verifique se a sua aplicação consegue comunicar e autenticar-se contra a API do AcapaDev ID através do comando Artisan incluído no SDK:

```bash
php artisan acapapay:test-connection
```

Ou diretamente no seu código (ou via `tinker`):

```php
use AcapaPay\Laravel\Facades\AcapaPay;

try {
    $res = AcapaPay::ping();
    dump($res);
    // Retorna: ['status' => 'success', 'message' => 'Ligação M2M ao AcapaDev SSO estabelecida com sucesso.']
} catch (\Exception $e) {
    dump("Erro de ligação: " . $e->getMessage());
}
```
