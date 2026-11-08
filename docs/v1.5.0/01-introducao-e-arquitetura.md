# AcapaPay Laravel SDK — Guia de Arquitetura e Introdução

* **Versão do SDK:** `v1.5.0`
* **Data da Documentação:** 08 de Novembro de 2026
* **Ambientes Homologados:** Live (Produção) & Sandbox (Testes)
* **Plataforma Central:** AcapaDev ID (`id.acapadev.me` / `finance.acapadev.me`)

---

## 1. Visão Geral

O **AcapaPay Laravel SDK** é a biblioteca oficial mantida pela AcapaDev para conectar aplicações Laravel ao ecossistema de faturação, subscrições e pagamentos digitais do **AcapaDev ID**.

A arquitetura do SDK foi concebida para oferecer:
1. **Segurança de Nível Bancário:** Autenticação Machine-to-Machine (M2M) via OAuth 2.0 (Client Credentials Grant) com rotação segura de segredos.
2. **Webhooks Assinados:** Todas as notificações de eventos são validadas via HMAC-SHA256 (`X-AcapaDev-Signature`).
3. **Flexibilidade de Checkout:** Suporte total a **Checkout Hospedado** (estilo Stripe) e **Checkout Direto Headless** (construção da sua própria interface de pagamento).
4. **Multi-Provedor Nacional e Internacional:** Processamento nativo em Kwanzas (AOA) via **AppyPay / EMIS** (Multicaixa Express e Referência Multicaixa) e WiPay, bem como liquidações internacionais em Dólares (USD) e Criptomoedas via **RedotPay**.
5. **Reembolsos Automatizados (Novo na v1.5.0):** Estornos totais e parciais acionados diretamente pelo SDK com crédito imediato na conta bancária do cliente via Multicaixa Express.

---

## 2. Modos de Integração

O SDK disponibiliza dois modelos principais de integração para satisfazer diferentes experiências de utilizador:

### Modelo A: Checkout Hospedado (Hosted Flow)
* **Caso de Uso:** Aplicações SaaS, lojas virtuais ou portais que desejam delegar toda a interface de pagamento para a página segura do AcapaPay.
* **Experiência:** A app satélite gera uma sessão com `AcapaPay::checkoutSession(...)` ou fatura com `AcapaPay::createInvoice(...)`, recebendo um URL seguro para redirecionar o cliente (ou abrir em modal/iFrame).
* **Vantagens:** Zero preocupações com conformidade PCI-DSS ou construção de ecrãs de pagamento.

### Modelo B: Pagamento Direto via API (Headless Flow)
* **Caso de Uso:** Apps que pretendem manter o cliente 100% dentro da sua própria interface ou aplicação mobile, desenhando os seus próprios componentes visuais.
* **Experiência:** A app cria a fatura e gera cobranças programáticas via `AcapaPay::direct()->charge(...)`, recebendo diretamente os dados da Referência Multicaixa (Entidade, Referência, Montante) ou despoletando o push de autorização do Multicaixa Express no telemóvel do cliente.

---

## 3. Matriz de Métodos de Pagamento e Moedas

| Código (`PaymentMethod`) | Nome Amigável | Moeda | Provedor de Gateway | Requer Telefone | Suporta Reembolso Automático? | Validade Padrão |
| :--- | :--- | :--- | :--- | :---: | :---: | :--- |
| `PaymentMethod::GPO` | **Multicaixa Express** | `AOA` | AppyPay / EMIS | **Sim** | **Sim (v1.5.0)** | 60 a 120 segundos |
| `PaymentMethod::REF` | **Referência Multicaixa** | `AOA` | AppyPay / EMIS | Não | Não (Exige TED/Transf. Manual) | 3 dias |
| `PaymentMethod::RDP` | **Criptomoeda (RedotPay)** | `USD` | RedotPay | Não | Não | 1 hora |
| `PaymentMethod::WIP` | **WiPay Hosted** | `AOA` | WiPay | Opcional | Não | Definida pelo provedor |

---

## 4. Ciclo de Vida da Fatura e Transação

```mermaid
stateDiagram-v2
    [*] --> Pending: createInvoice() / charge()
    Pending --> Paid: Webhook invoice.paid / Polling
    Pending --> Failed: Webhook invoice.failed / Rejeição
    Pending --> Expired: Tempo limite excedido
    Paid --> Refunded: AcapaPay::refund() (Total)
    Paid --> PartiallyRefunded: AcapaPay::refund() (Parcial)
    PartiallyRefunded --> Refunded: AcapaPay::refund() (Restante)
    Refunded --> [*]
    Failed --> [*]
    Expired --> [*]
```

1. **Criação da Fatura (`pending`):** A fatura é registada no sistema central com o valor, moeda e itens discriminados.
2. **Geração da Cobrança:** É associada uma tentativa de pagamento ao método selecionado (Multicaixa Express, Referência ou Cripto).
3. **Liquidação (`paid`):** Assim que o cliente autoriza o pagamento no Multicaixa Express ou efetua o pagamento no ATM/Homebanking, o gateway notifica o AcapaDev ID, que assina e despacha o webhook `invoice.paid` para a sua app.
4. **Reembolso (`refunded` / `partially_refunded`):** A sua app pode emitir uma ordem de devolução via API a qualquer momento, debitando o seu TPA Virtual e creditando o cliente no Multicaixa Express.

---

## 5. Próximos Passos

Para começar a integração passo a passo:
* [02. Instalação e Configuração](02-instalacao-e-configuracao.md)
* [03. Checkout Hospedado](03-checkout-hospedado.md)
* [04. Pagamento Direto via API](04-pagamento-direto-api.md)
* [05. Reembolsos e Estornos (Novo)](05-reembolsos-e-estornos.md)
* [06. Webhooks e Eventos](06-webhooks-e-eventos.md)
