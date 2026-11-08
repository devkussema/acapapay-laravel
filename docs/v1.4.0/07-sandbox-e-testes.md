# 7. Modo Sandbox e Testes

> Faz parte da documentação do [`devkussema/acapapay-laravel`](../README.md). Ver o [índice completo](../README.md#-documentação-completa).

O modo Sandbox é uma ferramenta essencial para o desenvolvimento local. Permite testar os fluxos de pagamento da tua aplicação (redirecionamento, cancelamento e receção de webhooks) de forma 100% fiel, sem gerar lixo nas bases de dados dos gateways de pagamento reais (Pay4All, E-Kwanza, RedotPay) e sem necessitares de fundos.

## Como funciona

O SDK comunica ao SSO Acapadev que o teu ambiente está em `sandbox`:

1. O fluxo começa normalmente: o SDK contacta a API do SSO e cria uma fatura e sessão de pagamento.
2. É gerado um link de redirecionamento para o ecrã de pagamento do SSO, **exatamente como em produção**.
3. No ecrã de pagamento, o SSO identifica que esta é uma sessão de Sandbox.
4. Em vez de gerar referências reais (na EMIS, ou uma cobrança real na RedotPay), o SSO adiciona um painel de testes exclusivo para poderes "Simular Pagamento com Sucesso" ou "Simular Falha".
5. Ao simular o sucesso, o SSO emite internamente os recibos "fake", altera o estado da fatura para `paid`, e **dispara instantaneamente o webhook `invoice.paid`** para o URL configurado na tua App Satélite.

Este processo garante que consigas depurar a integridade da tua integração desde o clique de compra até ao processamento do webhook de resposta no teu servidor.

## Ativar

```env
ACAPAPAY_MODO=sandbox
```

> [!DANGER]
> Nunca coloques `ACAPAPAY_MODO=sandbox` no servidor de produção — os clientes poderiam "pagar" faturas simuladas de graça. Se a chave não existir no `.env`, o pacote assume automaticamente `production`.

## O que deves observar

- **No checkout do SSO:** verás uma aba extra chamada **"Sandbox"**, selecionada por defeito, com opções para simular o comportamento de um utilizador.
- **Painel Acapadev (listagem):** todas as faturas geradas em ambiente de testes pela tua App são marcadas com a tag `TESTE`, para não afetarem a contabilidade comercial real.
- **RedotPay (cripto/USD):** ao contrário de `REF`/`GPO`/`EKZ`, uma cobrança `RDP` gerada com a tua App em modo sandbox **chama sempre a API real de sandbox da RedotPay** (não um mock interno) — porque a RedotPay já tem o seu próprio ambiente de testes seguro, com chaves próprias geradas no painel deles. Ver [04-pagamentos-cripto-redotpay.md](04-pagamentos-cripto-redotpay.md).

## Simular via API direta (sem passar pelo checkout hospedado)

Se estiveres a usar a [API de pagamento direta](03-pagamento-direto-api.md), podes marcar a fatura como paga diretamente, sem precisares de abrir o painel do checkout:

```php
AcapaPay::direct()->simulate($invoiceId);
```

Isto dispara o webhook `invoice.paid` tal como um pagamento real. Lança `ForbiddenException` se a App não estiver em modo sandbox — ver [06-tratamento-de-erros.md](06-tratamento-de-erros.md).

## Resolvendo problemas de webhook local

Quando testas localmente (Laragon, `php artisan serve`, etc.), o teu projeto não tem um domínio público (ex: `meuprojeto.test`). Para que o SSO consiga entregar o webhook e marcares o pedido do cliente como ativo:

1. Usa uma solução de túnel reverso, como `ngrok` ou `cloudflared`, para criar um endereço HTTPS público (ex: `https://meuprojeto.ngrok.app`).

   ```bash
   ngrok http 80
   # ou
   cloudflared tunnel --url http://localhost:80
   ```

2. Adiciona esse domínio temporário nas definições da tua App Satélite, no painel Developer do SSO Acapadev, na secção de Webhooks.
3. Confirma que o SSL do teu servidor local não está a bloquear as chamadas — se usares certificados auto-assinados, o Guzzle vai recusá-los no envio do webhook. Nesse caso, garante `ACAPAPAY_VERIFY_SSL=false` **apenas em ambiente local** (nunca em produção — ver [01-instalacao-e-configuracao.md](01-instalacao-e-configuracao.md)).

---

**A seguir:** [08-sincronizacao-de-planos.md](08-sincronizacao-de-planos.md) — sincronizar o teu catálogo de planos com o AcapaPay.
