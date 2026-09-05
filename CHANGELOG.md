# Changelog

Todas as alterações relevantes deste pacote são documentadas neste ficheiro.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o versionamento segue o [SemVer](https://semver.org/lang/pt-BR/).

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
