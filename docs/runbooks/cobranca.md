# Runbook: cobrança das assinaturas (Asaas)

Como a Moduhub cobra os workspaces, o que configurar no Asaas e o que fazer quando algo dá errado.
Código em `app/Modules/Billing`.

## Como funciona

- **Carrinho:** a assinatura tem um plano por produto (assinaturas, NFS-e, e-mail), todos cobrados juntos numa mensalidade só no Asaas.
  - Os itens ficam em `tenant_subscription_items`, com o preço do dia em que o plano entrou no carrinho.
  - Cota e excedente de cada produto vêm do plano daquele produto (`TenantSubscription::termsFor()`).
  - Na página de vendas, o carrinho vira o link `/register?plans=chave1,chave2` (visitante) ou `/workspace/billing?plans=...` (dono logado).
  - Cadastro sem carrinho: o teste grátis libera o plano mais barato de cada produto.
- **Planos:** cadastrados pela equipe em **/admin/planos** (veja "Admin da plataforma" abaixo). Cada plano é de um produto só.
- **Onde o cliente assina:** Workspace → **Assinatura** (`/workspace/billing`). Só o dono do workspace vê a tela.
- **Formas de pagamento:**
  - **Cartão:** recorrência no Asaas. O cartão é cobrado sozinho a cada vencimento, mês a mês.
  - **Pix:** o Asaas gera a cobrança do mês e manda por e-mail. O cliente também pode pagar pelo QR Code na tela de Assinatura.
- **O cartão nunca é gravado.** O formulário manda o cartão ao nosso backend, que repassa direto para o Asaas.
  - Fica salvo só a bandeira, o final e o token do Asaas (cifrado com a `APP_KEY`).
  - Os campos do cartão estão no `dontFlash` e não vão para a sessão nem para o log.
- **Teste grátis:** quem assina durante o teste não paga nada na hora. A primeira mensalidade vence no último dia do teste. No cartão, o Asaas só valida o cartão na assinatura.
- **Depois do teste:** a mensalidade vence no dia em que o cliente assina. O workspace fica só para consulta (`incomplete`) até o pagamento ser confirmado.
- **Situações (`tenant_subscriptions.status`):**

  | Situação | Significado | Acesso |
  |---|---|---|
  | `trial` | Teste grátis, com ou sem assinatura no Asaas. | Liberado |
  | `incomplete` | Assinou depois do teste e a primeira mensalidade ainda não caiu. | Só leitura |
  | `active` | Em dia. | Liberado |
  | `past_due` | Alguma cobrança venceu, seja mensalidade ou excedente. | Liberado durante a carência |
  | `suspended` | O teste acabou sem assinatura, ou o atraso passou da carência (`BILLING_GRACE_DAYS`, 7 dias). | Só leitura |
  | `cancelled` | O cliente cancelou. | Liberado até o fim do ciclo pago, depois só leitura |

- **"Só leitura":** `GET` continua funcionando, mas `POST`, `PATCH` e `DELETE` nos módulos são recusados.
  - No painel, o usuário volta para a página anterior com aviso.
  - Na API, a resposta é `402` com `code: subscription_blocked`.
  - A página pública de assinatura (`/sign/{token}`) **não** é bloqueada. O signatário não tem culpa.
- **Quem decide se está pago é o webhook.** A resposta da criação da assinatura nunca libera acesso.
  - Cada evento grava a cobrança em `billing_payments`.
  - Depois disso, a situação é recalculada do zero: qualquer cobrança vencida deixa em atraso; sem nenhuma vencida, a assinatura volta a ativa.
- **Rotina `billing:cycle`** (de hora em hora):
  - Teste acabou: com assinatura no Asaas, abre o primeiro ciclo; sem ela, bloqueia.
  - Ciclo fechou: cobra o excedente do ciclo e abre o próximo.
  - Atraso passou da carência: bloqueia.
- **Excedente:** o ciclo fechado vira uma cobrança avulsa, que vem dos módulos que implementam `BillableUsageContract` (tag `billable-usage`):
  - notas marcadas `is_overage`;
  - documentos enviados além da cota.

  Como é cobrado:
  - **Pix:** cobrança com vencimento em 3 dias.
  - **Cartão com token:** cobrado no cartão no mesmo dia.
  - **Cartão sem token:** sai uma fatura (`UNDEFINED`) e o cliente escolhe como pagar.

  Excedente abaixo de **R$ 5,00** não é cobrado, porque o Asaas não aceita cobrança menor. Cada ciclo gera no máximo uma cobrança de excedente (índice único).
- **Troca no carrinho** (entrar, sair ou trocar de plano num produto): os módulos mudam na hora. O novo valor vale a partir da próxima mensalidade em aberto (`updatePendingPayments`), sem cobrança proporcional. Plano que continua no carrinho mantém o preço contratado.
- **Cancelamento:** apaga a assinatura no Asaas, que remove as mensalidades em aberto. O excedente do último ciclo ainda é cobrado.

## Admin da plataforma

- Criar ou promover o admin (no servidor, uma vez):

  ```bash
  PLATFORM_ADMIN_EMAIL=voce@moduhub.com.br PLATFORM_ADMIN_PASSWORD='...' php artisan db:seed --class=PlatformAdminSeeder --force
  ```

  - Usuário que já existe só ganha a marca `users.is_platform_admin`; a senha não muda.
  - Sem `PLATFORM_ADMIN_PASSWORD`, o usuário novo recebe uma senha aleatória, mostrada uma única vez no terminal.
  - Para tirar o acesso: `is_platform_admin = 0` no banco.
- O admin vê **Admin → Planos** no menu. Lá cadastra e edita os planos de cada produto: preço, teste grátis, cota mensal, cota diária (só e-mail), excedente, ordem e se está à venda.
- **O que muda para quem já assina quando você edita um plano:**
  - **Preço:** só vale para assinaturas novas. Quem já assina segue pagando o preço contratado.
  - **Cota e excedente:** mudam na hora para todos.
  - **Produto:** não pode mudar se o plano tem assinantes. Crie um plano novo.
- **Tirar um plano da venda:** desligue "À venda". Quem já assina continua com ele. Não existe exclusão.
- **Em produção, não rode o `PlanSeeder`.** Ele cria a grade de exemplo, que serve só para desenvolvimento. Os planos de produção são cadastrados no admin.
- **Combos antigos** (`starter`, `signatures-pro`, `invoices-pro`): aparecem no admin em "Combos antigos". Ficam fora do carrinho e valem para quem já tinha.

## Configurar no Asaas

1. **Conta:** use a conta PJ da Moduhub. Para testar, crie uma conta no sandbox (`sandbox.asaas.com`).
2. **Chave de API:** Integrações → Chave de API. Vai em `ASAAS_API_KEY`. A chave do sandbox e a de produção são diferentes.
3. **Webhook:** Integrações → Webhooks → novo.
   - URL: `https://<APP_URL>/webhooks/asaas`.
   - Versão da API: v3. Envio: **sequencial**.
   - **Token de autenticação:** gere um aleatório de 32+ caracteres (`openssl rand -hex 24`) e coloque o mesmo valor em `ASAAS_WEBHOOK_TOKEN`.
   - Eventos de **cobrança**: `PAYMENT_CREATED`, `PAYMENT_UPDATED`, `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED`, `PAYMENT_OVERDUE`, `PAYMENT_DELETED`, `PAYMENT_RESTORED`, `PAYMENT_REFUNDED`, `PAYMENT_CREDIT_CARD_CAPTURE_REFUSED`, `PAYMENT_REPROVED_BY_RISK_ANALYSIS`, `PAYMENT_CHARGEBACK_REQUESTED`, `PAYMENT_CHARGEBACK_DISPUTE`.
4. **Tokenização de cartão (produção):** peça ao gerente de contas. Sem ela:
   - trocar o **valor** de uma assinatura no cartão (troca de plano) é recusado pelo Asaas;
   - o excedente do cliente de cartão sai como fatura, em vez de cobrado direto no cartão.

   No sandbox ela já vem ligada.
5. **Notificações ao cliente:** o Asaas manda por padrão e-mail de cobrança criada, vencendo e vencida. Deixe ligado: é o que leva o Pix do mês até o cliente.

## `.env`

```dotenv
ASAAS_DRIVER=asaas                               # fake em dev e testes
ASAAS_API_KEY=...                                # $aact_... de produção
ASAAS_BASE_URL=https://api.asaas.com/v3          # sandbox: https://api-sandbox.asaas.com/v3
ASAAS_WEBHOOK_TOKEN=...                          # igual ao do webhook no Asaas
BILLING_GRACE_DAYS=7
```

## Validar no sandbox antes de ligar em produção

Estes pontos foram implementados pela documentação e precisam ser conferidos uma vez no sandbox:

- [ ] Assinar com cartão `4111 1111 1111 1111` (validade futura, CVV qualquer) e conferir no painel do Asaas:
  - a assinatura, com o vencimento no fim do teste;
  - o retorno com a bandeira e o final.
- [ ] Assinar com um cartão terminado em **0002**, que o Asaas recusa. A mensagem do Asaas aparece no formulário e nada é gravado.
- [ ] Trocar de Pix para cartão e de cartão para Pix (`PUT /subscriptions/{id}` com `billingType` e depois `PUT /subscriptions/{id}/creditCard`).
- [ ] Trocar de plano numa assinatura no cartão (exige tokenização).
- [ ] Pagar um Pix no sandbox (botão "Confirmar pagamento" da cobrança) e ver a situação virar *Ativa* em segundos.
- [ ] Rodar `php artisan billing:cycle` com um ciclo vencido de propósito e conferir a cobrança de excedente.

## Problemas comuns

**Cliente pagou e continua bloqueado.**
1. No Asaas, abra a cobrança e confira se está *Recebida* ou *Confirmada*.
2. Em Integrações → Webhooks → Logs, procure o evento.
   - **Fila pausada** (o Asaas pausa depois de várias falhas): corrija a causa e reative a fila. Os eventos ficam guardados por 14 dias.
   - **401/403:** o `ASAAS_WEBHOOK_TOKEN` não bate com o token do webhook.
3. No banco, confira `billing_webhook_events` (o evento chegou?) e `billing_payments` (status da cobrança).
4. Se o evento chegou e mesmo assim a situação não mudou, existe **outra** cobrança vencida. Basta uma vencida, até de excedente, para manter o workspace em atraso.

**Excedente não foi cobrado.**
- Procure em `billing_payments` a linha `kind=overage` do ciclo.
  - **Com `gateway_payment_id` nulo:** o Asaas recusou. O motivo está em `failure_reason`, e a rotina tenta de novo a cada hora.
  - **Sem linha nenhuma:** o total ficou abaixo de R$ 5,00, ou o ciclo era de teste grátis.

**Liberar um cliente na mão** (acordo comercial, erro nosso). Isto não mexe no Asaas, então a próxima cobrança vencida volta a bloquear:

```bash
php artisan tinker
>>> $s = App\Models\TenantSubscription::withoutTenantScope()->where('tenant_id', '<ulid>')->latest('id')->first();
>>> $s->update(['status' => 'active', 'past_due_since' => null, 'suspended_at' => null]);
```

**Workspaces antigos.** No primeiro `billing:cycle` depois do deploy, todo workspace com teste grátis vencido e sem assinatura fica **só leitura**. Antes do deploy, confira quem é cliente real e estenda o teste de quem precisar (`trial_ends_at`).
