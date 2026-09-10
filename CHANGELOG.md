# Changelog

Todas as alterações relevantes deste pacote são documentadas neste ficheiro.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o versionamento segue o [SemVer](https://semver.org/lang/pt-BR/).

---

## [1.3.0] — 2026-09-11

Correcções ao caminho **USD/criptomoeda (RedotPay)** para apps satélite, encontradas ao
confrontar a implementação com o contrato oficial da RedotPay. **Não quebra nada**: as
assinaturas públicas mantêm-se, e quem já cobra em AOA não precisa de mudar uma linha.

> Boa parte destas correcções é no servidor AcapaPay (`id.acapadev`). Este pacote passa a
> depender delas — actualiza o pacote **e** confirma que o SSO já está na versão que as traz.

### Corrigido

- **`direct()->createInvoice()` gerava cobranças reais em sandbox.** Ao contrário de
  `checkoutSession()` e de `AcapaPay::createInvoice()`, não marcava a fatura com
  `metadata.sandbox_mode`, que é o que faz o servidor usar o gateway simulado. Quem
  construía o seu próprio checkout com a API direta estava a cobrar a sério em modo de teste.
- **`success_url` e `cancel_url` eram descartados em faturas avulsas.** O servidor não os
  aceitava no `POST /v1/billing/invoices`, pelo que o cliente que pagava em cripto era
  devolvido à página do AcapaPay em vez de voltar à app satélite.
- **Moeda em minúsculas.** `'usd'` passava pela validação e era gravado tal e qual, para
  depois ser rejeitado pela RedotPay como `orderCurrency` — já com a fatura criada. Agora é
  normalizado no SDK e no servidor.
- **Moedas não suportadas eram aceites.** A validação do servidor era `size:3`, pelo que
  `EUR` criava uma fatura que nenhum gateway consegue liquidar. Agora é `AOA` ou `USD`.
- **Faturas acima de 10 000 USD eram enviadas truncadas à RedotPay.** O limite por linha de
  produto do contrato oficial é `0 < goodsAmount < 10000` (e não 99 999,99): o total é agora
  repartido por linhas, e uma fatura acima do tecto de 10 linhas falha com mensagem clara em
  vez de criar uma ordem com valor errado. Ver [04-pagamentos-cripto-redotpay.md](docs/04-pagamentos-cripto-redotpay.md).
- **Pagamentos ficavam pendentes para sempre depois de trocar de método.** Se o cliente
  gerava uma referência Multicaixa e só depois escolhia cripto, o polling continuava a
  consultar a primeira tentativa e a fatura nunca era dada como paga.
- **A liquidação rebentava com erro de SQL** quando a fatura tinha mais do que uma cobrança
  pendente (estado `cancelled` inexistente no esquema).

### Alterado

- `Currency::isValid()` continua igual, mas o SDK passa a **normalizar** a moeda para
  maiúsculas e a lançar `ValidationException` antes do pedido, em vez de deixar o servidor
  falhar mais tarde.
- `POST /v1/billing/invoices` passa a devolver também `preferred_payment_method`.

### Documentação

- [04-pagamentos-cripto-redotpay.md](docs/04-pagamentos-cripto-redotpay.md): tecto de valor
  por fatura, comportamento em sandbox, e o facto de o webhook da RedotPay se configurar no
  painel do comerciante (o pedido de criação de ordem não tem campo de callback).

---

## [1.2.0] — 2026-09-04

Suporte completo a pagamentos em **USD e criptomoedas (RedotPay)** e uma nova
**API de pagamento direta**, que permite às apps satélite construírem o seu
próprio ecrã de checkout.

**Esta versão não quebra nada.** Todas as assinaturas públicas da v1.1.0
mantêm-se inalteradas, `new AcapaPayManager()` continua a funcionar, e as
excepções novas continuam a ser apanhadas por `catch (\Exception $e)`.

### Corrigido

- **Pagamentos avulsos deixavam o webhook em ciclo infinito.** Um `invoice.paid`
  sem subscrição associada (como acontece em qualquer pagamento em cripto)
  lançava uma excepção, devolvia HTTP 500, e o SSO reenviava o webhook
  indefinidamente. O pagamento nunca era registado na app satélite.
- **`createInvoice()` enviava campos que o servidor rejeita.** Enviava
  `{user_id, amount, description}` quando o endpoint `/v1/billing/invoices`
  espera `{customer_name, items[]}` — devolvia sempre `422`. (Este método nunca
  chegou a ser publicado numa versão estável.)
- **Um 401 fazia falhar o pedido em curso.** O SDK limpava a cache do token mas
  não repetia o pedido; agora repete uma vez com um token novo.
- **`checkoutSession()` falhava em filas/consola**, por depender de `request()`.
- O componente iFrame **não validava a origem** das mensagens `postMessage` —
  qualquer página podia forjar um `acapapay.success`.

### Adicionado

- `AcapaPayPaymentReceived` — evento disparado em **qualquer** fatura paga, com
  ou sem subscrição. É o evento a usar em apps com pagamentos avulsos.
  Traz `invoiceId`, `amount`, `currency`, `paymentMethod` e os helpers
  `isSubscription()`, `isOneOff()` e `isCrypto()`.
- **API de pagamento direta** via `AcapaPay::direct()`:
  `createInvoice()`, `charge()`, `chargeWithCrypto()`, `status()`, `isPaid()`,
  `find()` e `simulate()`.
- `AcapaPay::createCryptoCharge()` — cria fatura em USD e cobrança em cripto num
  só passo.
- `AcapaPay::ping()` — testa a conectividade com a API.
- Constantes `PaymentMethod` (`REF`, `GPO`, `EKZ`, `RDP`) e `Currency`
  (`AOA`, `USD`), com helpers `requiresPhoneNumber()`, `requiresUsd()` e `label()`.
- Hierarquia de excepções: `AcapaPayException`, `ApiException`,
  `ValidationException` (com `errors()`), `AuthenticationException`,
  `NotFoundException`, `ForbiddenException` e `ConnectionException`.
- `ChargeResult` — objecto de resultado com `payUrl()`, `reference()`,
  `expiresAt()`, `isMock()`… e que também funciona como array.
- `ChargeResult::paymentMethods()`, `qrCodeUrls()` e `appUrl(string $walletId)`
  — para quem quer construir o próprio ecrã de pagamento em cripto com o QR
  code embutido, em vez de redirecionar para a página da RedotPay. Ver a
  secção "Como apresentar o pagamento ao utilizador: 2 fluxos possíveis" no
  README para a explicação completa (incluindo o porquê de teres de gerar tu
  a imagem do QR a partir do link que a RedotPay devolve).
- Timeouts, retentativas e TTL do token configuráveis
  (`ACAPAPAY_TIMEOUT`, `ACAPAPAY_CONNECT_TIMEOUT`, `ACAPAPAY_RETRY_TIMES`,
  `ACAPAPAY_TOKEN_TTL`).
- `iframe_allowed_origins` para autorizar origens adicionais no componente iFrame.
- Vistas publicáveis: `php artisan vendor:publish --tag="acapapay-views"`.
- O `AcapaPayManager` passa a poder ser injectado por type-hint no construtor.

### Alterado

- A lógica HTTP e do token OAuth2 foi extraída para `AcapaPayClient`, agora
  partilhada por todo o SDK — incluindo o `acapapay:test-connection`, que antes
  duplicava a autenticação e ignorava a cache.
- O componente iFrame usa `allow="payment"` (o `allowpaymentrequest` foi removido
  dos browsers) e gera um `id` único por instância, permitindo dois checkouts na
  mesma página.
- README: corrigido o exemplo de listener da secção 6, que usava uma propriedade
  (`$event->payload`) que nunca existiu.

### Notas de migração

Nada a fazer para continuar a funcionar como antes.

Se a tua app aceita **pagamentos avulsos** (sem subscrição), passa a escutar
`AcapaPayPaymentReceived` — o `AcapaPayInvoicePaid` continua a ser disparado
apenas em subscrições, por isso nunca serás notificado de um pagamento avulso
através dele. Se escutares os dois, usa `$event->isOneOff()` no listener novo
para evitares processar uma subscrição duas vezes.

---

## [1.1.0]

- Modo sandbox para desenvolvimento local e simulação de pagamentos.
- Eventos `AcapaPayInvoiceFailed` e `AcapaPayInvoiceExpired`.
- Cache do token OAuth2 no `AcapaPayManager`.
- Configuração de moeda e método de pagamento preferidos.

## [1.0.0]

- Lançamento inicial: `checkoutSession()`, `getInvoiceStatus()`, `syncPlans()`,
  componente Blade de iFrame, webhook com validação HMAC e evento
  `AcapaPayInvoicePaid`, comandos `acapapay:test-connection` e
  `acapapay:sync-plans`.
