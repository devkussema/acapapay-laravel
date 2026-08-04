# Modo Sandbox no AcapaPay Laravel

O modo Sandbox é uma ferramenta essencial para o desenvolvimento local. Ele permite testar os fluxos de pagamento da tua aplicação (redirecionamento, cancelamento e recebimento de webhooks) de forma 100% fiel, sem gerar lixo nas bases de dados dos gateways de pagamento reais (ex: Pay4All) e sem necessitares de fundos.

## Como funciona?

O SDK AcapaPay intercepta o facto de o teu ambiente estar configurado como `sandbox` e comunica ativamente esse estado ao SSO Acapadev. 

1. O fluxo começa normalmente: o SDK contacta a API do SSO e cria uma fatura e sessão de pagamento.
2. É gerado um link de redirecionamento para o ecrã de pagamento do SSO, **exatamente como em produção**.
3. No ecrã de pagamento, o SSO irá identificar que esta é uma sessão de Sandbox.
4. Em vez de gerar referências reais na EMIS, o SSO adicionará um painel de testes exclusivo para poderes "Simular Pagamento com Sucesso" ou "Simular Falha".
5. Ao simular o sucesso, o SSO emite internamente os recibos "Fake", altera o estado da fatura para `paid` e **dispara instantaneamente o evento Webhook** para o URL configurado na tua App Satélite.

Este processo garante que possas depurar a integridade da tua integração desde o clique de compra até ao processamento do Webhook de resposta no teu servidor.

## Configuração

A configuração é simples. No teu ficheiro `config/acapapay.php` foi adicionada a seguinte chave:

```php
    /*
    |--------------------------------------------------------------------------
    | Ambiente (Modo)
    |--------------------------------------------------------------------------
    | 'production' para pagamentos reais.
    | 'sandbox' para simulação de pagamentos sem faturar de verdade.
    */
    'modo' => env('ACAPAPAY_MODO', 'production'),
```

Para ativá-la durante o desenvolvimento, basta adicionares no teu ficheiro `.env` da tua aplicação (ex: Token ou Contratoo):

```env
ACAPAPAY_MODO=sandbox
```

> **Aviso:** Nunca coloques `ACAPAPAY_MODO=sandbox` no servidor de Produção, pois os clientes poderão pagar faturas simuladas de borla. Se a chave não existir no `.env`, o pacote assume automaticamente o comportamento de Produção.

## O que deves observar

- **No Checkout do SSO:** Verás uma aba Roxa extra chamada **"Sandbox"** selecionada por defeito. Nela tens opções para simular o comportamento de um utilizador.
- **Painel Acapadev (Listagem):** No backoffice de listagem de faturação, todas as faturas geradas em ambiente de testes pela tua App serão marcadas com a tag `TESTE` para não afetarem as contas e contabilidade comercial real.

## Resolvendo Problemas de Webhook Local

Quando testas localmente (usando o Laragon ou o `php artisan serve`), o teu projeto web não tem um domínio público (ex: `meuprojeto.test`). 
Para que o SSO Acapadev consiga "falar" com a tua máquina para entregar o Webhook e marcares o plano do cliente como ativo, certifica-te de que:

1. Estás a utilizar uma solução de túnel reverso, como o `ngrok` ou `cloudflare tunnels`, para criar um endereço HTTPS público (ex: `https://meuprojeto.ngrok.app`).
2. Adicionaste esse domínio temporário nas definições da tua aplicação satélite dentro do painel Developer do SSO Acapadev (na secção de Webhooks).
3. O SSL do SSO local está a permitir as chamadas, certificando-te que não usas certificados auto-assinados restritos que o Guzzle vai barrar no Webhook Dispatch.
