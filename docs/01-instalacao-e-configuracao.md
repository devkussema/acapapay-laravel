# 1. Instalação e Configuração

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).

## Requisitos

| Componente | Versão mínima |
|---|---|
| PHP | 8.1 |
| Laravel | 9.x, 10.x, 11.x, 12.x, 13.x ou 14.x |
| Credenciais SSO | Client ID + Client Secret, criados no painel Developer do Acapadev SSO |

> [!WARNING]
> **Compatibilidade exclusiva:** este pacote foi desenhado exclusivamente para o **Laravel Framework**. Tem uma proteção em runtime que impede a sua execução em ambientes PHP puro ou noutras frameworks (lança `\RuntimeException` no `register()` do Service Provider se a instância da app não for `Illuminate\Foundation\Application`).

## Instalação

```bash
composer require devkussema/acapapay-laravel
```

O Laravel faz o *auto-discovery* do `AcapaPayServiceProvider` e da Facade `AcapaPay` automaticamente — não precisas de os registar manualmente.

## Publicar o ficheiro de configuração (opcional)

O SDK funciona inteiramente com variáveis `.env`. Só precisas de publicar a configuração se quiseres personalizar comportamentos avançados (ex: a Model de planos para sincronização — ver [08-sincronizacao-de-planos.md](08-sincronizacao-de-planos.md)):

```bash
php artisan vendor:publish --tag="acapapay-config"
```

Isto copia `config/acapapay.php` para o teu projeto. A partir daí, as tuas alterações são respeitadas.

Também podes publicar as views (o componente iFrame), se quiseres personalizar o HTML/JS dele:

```bash
php artisan vendor:publish --tag="acapapay-views"
```

Isto copia para `resources/views/vendor/acapapay/components/iframe.blade.php`.

## Variáveis de ambiente (`.env`)

A configuração mínima obrigatória é o par de credenciais OAuth:

```env
# ─── Ecossistema AcapaDev ───────────────────────────────────────
# URL do servidor de identidade/OAuth (usado para obter tokens)
ACAPAPAY_HOST=https://id.acapadev.com

# URL da API (checkout, faturas, planos, cobranças)
ACAPAPAY_API_HOST=https://api.acapadev.com

# ─── Credenciais da tua App Satélite ────────────────────────────
# Criadas no painel Developer → Apps Satélite → Nova App
ACAPAPAY_CLIENT_ID=9a8b7c6d-1234-5678-abcd-ef0123456789
ACAPAPAY_CLIENT_SECRET=super_secret_do_teu_client...

# ─── Segurança de Webhooks ───────────────────────────────────────
# Chave HMAC para validar que os webhooks são legítimos.
# Ver 05-webhooks-e-eventos.md.
ACAPAPAY_WEBHOOK_SECRET=hmac_secret_configurado_no_painel...

# ─── Ambiente ─────────────────────────────────────────────────
# 'production' (padrão) para pagamentos reais.
# 'sandbox' para simular pagamentos sem cobrar nada — ver 07-sandbox-e-testes.md.
ACAPAPAY_MODO=production

# ─── SSL (opcional) ──────────────────────────────────────────────
# Define como false APENAS em ambientes locais com certificados
# auto-assinados. NUNCA usar em produção.
# ACAPAPAY_VERIFY_SSL=false

# ─── Moeda e método preferidos (opcional) ────────────────────────
# Útil para apps que cobram exclusivamente em USD/cripto via RedotPay.
# Ver 04-pagamentos-cripto-redotpay.md.
# ACAPAPAY_PREFERRED_CURRENCY=USD
# ACAPAPAY_PREFERRED_METHOD=RDP

# ─── Cliente HTTP (opcional) ──────────────────────────────────────
# Timeouts e retentativas das chamadas à API. Só falhas de rede são
# repetidas — um erro de validação (422) nunca é.
# ACAPAPAY_TIMEOUT=30
# ACAPAPAY_CONNECT_TIMEOUT=10
# ACAPAPAY_RETRY_TIMES=2
```

> [!DANGER]
> Nunca *commites* as credenciais! `ACAPAPAY_CLIENT_SECRET` e `ACAPAPAY_WEBHOOK_SECRET` são segredos sensíveis. Garante que o teu `.env` está no `.gitignore`.

## Obter as credenciais no painel SSO

1. Acede a `https://id.acapadev.com` e faz login com a tua conta de developer.
2. Navega até **Developer → Apps Satélite → Nova App**.
3. Define o nome da tua aplicação e o URL do Webhook (`https://teudominio.com/webhooks/acapapay` — o pacote regista essa rota automaticamente, ver [05-webhooks-e-eventos.md](05-webhooks-e-eventos.md)).
4. Após a criação, copia o **Client ID** e o **Client Secret** gerados.
5. Na secção de Webhooks da App, define/obtém também o **Webhook Secret**.

## Testar a conexão (diagnóstico)

Antes de escreveres qualquer código, o SDK tem um comando Artisan que envia um *ping* M2M ao servidor central e valida as tuas credenciais e a conectividade de rede:

```bash
php artisan acapapay:test-connection
```

- ✅ **Sucesso:** confirmação de que as credenciais são válidas e o servidor responde.
- ❌ **Falha:** relatório indicando o problema exato (credenciais inválidas, firewall, SSL, etc.) — o comando distingue entre falha de autenticação, falha de rede e erro da API, com uma mensagem própria para cada caso.

## Todas as chaves de `config/acapapay.php`

| Chave | Env var | Padrão |
|---|---|---|
| `client_id` | `ACAPAPAY_CLIENT_ID` | `null` |
| `client_secret` | `ACAPAPAY_CLIENT_SECRET` | `null` |
| `host` | `ACAPAPAY_HOST` | `https://id.acapadev.com` |
| `api_host` | `ACAPAPAY_API_HOST` | `https://api.acapadev.com` |
| `webhook_secret` | `ACAPAPAY_WEBHOOK_SECRET` | `null` |
| `modo` | `ACAPAPAY_MODO` | `production` |
| `verify_ssl` | `ACAPAPAY_VERIFY_SSL` | `true` |
| `plan_model` | *(sem env — definir no ficheiro publicado)* | `null` |
| `preferred_currency` | `ACAPAPAY_PREFERRED_CURRENCY` | `null` |
| `preferred_method` | `ACAPAPAY_PREFERRED_METHOD` | `null` |
| `timeout` | `ACAPAPAY_TIMEOUT` | `30` |
| `connect_timeout` | `ACAPAPAY_CONNECT_TIMEOUT` | `10` |
| `retry_times` | `ACAPAPAY_RETRY_TIMES` | `2` |
| `retry_sleep` | `ACAPAPAY_RETRY_SLEEP` | `200` (ms) |
| `token_ttl` | `ACAPAPAY_TOKEN_TTL` | `3000` (segundos) |
| `status_poll_interval` | `ACAPAPAY_STATUS_POLL_INTERVAL` | `15` (segundos) |
| `iframe_allowed_origins` | *(array, sem env)* | `[]` |

---

**A seguir:** [02-checkout-hospedado.md](02-checkout-hospedado.md) — iniciar o teu primeiro pagamento.
