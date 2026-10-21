# Moduhub — Diretrizes de arquitetura

Este repositório é um SaaS multi-tenant modular (assinatura de documentos, notas fiscais) em Laravel 13 + Inertia (React).
As regras abaixo são **vinculantes** — toda contribuição deve seguir.

## Separação de camadas (obrigatória)

Cada responsabilidade mora numa camada. Nunca misturar.

| Camada | Responsabilidade | Pode conter | Nunca contém |
|---|---|---|---|
| **Controllers** (`App\Http\Controllers`, `App\Modules\*\Http\Controllers`) | Receber HTTP, chamar Service, devolver Response. Só isso. | Inertia::render, redirect, resposta JSON. | Queries Eloquent, lógica de negócio, `DB::transaction`, cálculos. |
| **Form Requests** (`App\Http\Requests`, `App\Modules\*\Http\Requests`) | Validação + autorização a nível de request. | `rules()`, `authorize()`, `prepareForValidation()`. | Operações de banco, side-effects. |
| **Services** (`App\Platform`, `App\Modules\*\Services`) | Regra de negócio. Orquestra Repositories, Models, Events, Jobs. | `DB::transaction`, dispatch de events/jobs, chamada a repos. | Acesso direto a `Request`, retorno de Response. |
| **Repositories** (`App\Modules\*\Repositories`, `App\Platform\Repositories`) | Queries complexas e persistência. Retornam Models ou Collections. | `Model::query()`, scopes, joins, agregações. | Regra de negócio, dispatch de events. |
| **Contracts** (`App\Modules\*\Contracts`, `App\Platform\Contracts`) | Interfaces para Services e Repositories. Habilita injeção e testes. | Apenas declaração de métodos. | Implementação. |
| **Policies** (`App\Policies`, `App\Modules\*\Policies`) | Autorização em cima de Models (`$user->can('x', $model)`). | Regras `view/create/update/delete/custom`. | Mutação de dados, chamadas externas. |
| **Gates** (`App\Providers\AuthServiceProvider` ou registry) | Autorização ad-hoc sem Model (ex.: `can('access-admin-panel')`). | Closure de verificação. | Idem Policies. |
| **Models** (`App\Models`, `App\Modules\*\Models`) | Esquema Eloquent, relações, casts, scopes locais, accessors simples. | `$fillable`, `$casts`, `relações`, `scope*()`. | Lógica de aplicação, queries complexas de consumo externo (vai pro Repository). |
| **Jobs** (`App\Jobs`, `App\Modules\*\Jobs`) | Trabalho assíncrono. Finos — delegam para Services. | `handle()` que chama Service. | Regra de negócio inline. |

## Regras invioláveis

1. **Zero queries em Controllers.** Se precisa buscar algo, chama Repository. Se precisa agir, chama Service.
2. **Zero regra de negócio em Controllers.** Cálculos, status transitions, side-effects → Service.
3. **Validação sempre em FormRequest.** `$request->validate([...])` inline só é aceitável em prototipagem; nunca em código mergeado.
4. **Autorização sempre em Policy ou Gate.** Nunca `if ($user->role === 'admin')` espalhado.
5. **Transações em Service, não em Controller.** `DB::transaction()` vive na camada de negócio.
6. **Repositories retornam Models/Collections**, nunca arrays shape. Se precisa de DTO, use um `*Resource` ou `*Data` object (podemos adotar `spatie/laravel-data` quando necessário).
7. **Models não contêm lógica de aplicação.** Métodos como `isDraft()`, `isCompleted()`, `hasModule('x')` são aceitáveis (consultas sobre o próprio estado). Qualquer coisa que mude outros registros → Service.
8. **Multi-tenancy é automática.** Models tenant-scoped usam o trait `App\Tenancy\BelongsToTenant`. Nunca duplicar `where('tenant_id', ...)` à mão. Para romper o scope (ex.: lookup por token público, worker de fila), use `withoutTenantScope()` explicitamente — e documente por quê.
9. **Nomes de métodos refletem intenção, não CRUD.** `UserService::invite()` é melhor que `UserService::create()`. Controllers podem ter `store()`, mas o Service chamado deve ter nome do que faz.

## Estrutura esperada de um módulo

```
app/Modules/<ModuleName>/
├── Contracts/
│   ├── <Thing>RepositoryContract.php
│   └── <Thing>ServiceContract.php    (opcional, use quando a interface agregar valor para testes)
├── Models/
├── Repositories/
│   └── <Thing>Repository.php          implements <Thing>RepositoryContract
├── Services/
│   └── <Thing>Service.php             implements <Thing>ServiceContract
├── Policies/
├── Providers/
│   └── <ModuleName>ServiceProvider.php (binda Contracts → implementações)
├── Jobs/
├── Events/
├── Listeners/
├── Http/
│   ├── Controllers/
│   └── Requests/
└── routes.php                         (opcional, incluído no routes/web.php)
```

## Nomenclatura

- Classes PHP, identificadores de código, nomes de arquivos: **inglês** (padrão Laravel).
- Mensagens de UI, flash messages, textos de validação customizados: **português (pt-BR)**.
- Mensagens de commit, PRs e descrições: **português**.

## Padrões de código

- PHP 8.4+, strict types onde fizer sentido, promoção de propriedades em construtores.
- Nullable antes de default: `?Type $x = null`.
- Prefira `readonly` em DTOs/Value Objects.
- Evite comentários do que o código já diz. Comente **por quê**, não **o quê**.
- Nada de `// TODO: ...` sem issue associada.

## Tenancy

- Rotas de painel (web): `App\Http\Middleware\SetCurrentTenantFromSession` resolve o tenant via session.
- Rotas de API (`/api/v1/*`): `api.key` middleware resolve o tenant via `Authorization: Bearer mdhb_live_...`.
- Rotas públicas (ex.: `/invitations/{token}`, página pública de assinatura): resolvem o contexto pelo token e **não** devem depender do scope global — use `withoutTenantScope()`, ou entre no tenant do registro (como faz `EnterSignerTenant` em `/sign/{token}`). Lembre que o visitante pode estar logado em **outro** workspace.
- O `TenantScope` é **fechado**: sem tenant no contexto, a consulta lança `MissingTenantContext` em vez de trazer todos os workspaces.
- Jobs e comandos que trabalham para um tenant usam `app(CurrentTenant::class)->run($tenant, fn () => ...)`. Serviços que recebem `Tenant $tenant` como argumento também. `run()` recusa trocar para outro tenant quando já há um no contexto.
- Jobs despachados dentro de um tenant levam o `tenant_id` no payload e rodam nele no worker (`App\Tenancy\QueueTenancy`). Despachado sem contexto, roda sem contexto.

## Certificados digitais

- O `.pfx` e a senha do emitente usam o cast `CertificateEncrypted` (chave `CERTIFICATES_ENCRYPTION_KEY`, nunca a `APP_KEY`) e nunca saem em Resource, log ou resposta.
- O e-CNPJ da Moduhub (selo e ADN) é enviado em `/admin/certificado` (`platform_certificates`, mesmo cast) e só aceita e-CNPJ com cadeia até as raízes do ITI (`IcpChainValidator`, `resources/certificates/icp-brasil/`) e com a raiz de `SIGNATURES_PLATFORM_CNPJ`. O `.env` (`SIGNATURES_PLATFORM_CERT_PATH`) é só reserva. Quem precisa do certificado lê por `PlatformCertificateService::pkcs12()`, nunca do arquivo direto.
- A chave privada do cliente só é aberta por `IssuerCredentialsLoader::load($issuer, CertificateEvent::PURPOSE_*)`, que grava o uso na trilha `invoice_certificate_events`. Uso novo = finalidade nova em `CertificateEvent`, não um atalho que pule o `load()`.
- Operação, vencimento e incidentes: `docs/runbooks/certificados.md`.

## Arquivos (S3)

- Arquivo de cliente vai para um dos discos privados: `documents` (PDFs e imagens de assinatura) ou `fiscal` (XMLs da NFS-e). `FILE_STORAGE_DRIVER=s3` manda os dois para o bucket. Nunca `storage_path()` nem disco `local` para dado de cliente.
- Nada de `Storage::disk(...)->path()`: no S3 não existe caminho. Ferramenta que exige arquivo local (FPDI, TCPDF, openssl) usa `LocalScratch` (baixa, processa, sobe e limpa). O TCPDF aceita imagem em memória com `'@'.$bytes`.
- XML fiscal novo vira atributo virtual com o trait `ArchivesXml` (coluna `*_path` no banco, conteúdo no disco `fiscal`), não coluna `longText`. XML fiscal nunca é apagado.
- Operação, bucket, IAM e migração: `docs/runbooks/armazenamento.md`.

## E-mail (SES)

- Produção usa o mailer `ses-v2`. Remetente padrão no domínio da Moduhub.
- E-mail que o workspace manda para fora (signatário, tomador) pega `from`/`replyTo` de `TenantSender`, que só devolve o domínio próprio do workspace quando ele está verificado (`SendingDomain`). E-mail da conta (senha, cadastro, convite de membro) nunca usa o domínio do cliente.
- Todo envio passa por `Mail`/Mailable. Nada de cliente SES chamado direto: o listener `RecordSentEmail` só rastreia o que sai pelo Laravel.
- `email_deliveries` guarda um status por destinatário, atualizado pelos eventos do SES em `/webhooks/ses` (SNS com assinatura e tópico conferidos no `SesNotificationRequest`). O status só avança; não sobrescreva à mão.
- Envio pelo sistema do cliente: relay próprio `smtp.moduhub.com.br` (decisão de 2026-10-20; credencial SMTP da AWS por cliente descartada por não permitir cota nem bloqueio antes do envio). Credenciais em `smtp_credentials` (usuário `mdhb_smtp_*`, senha só como SHA-256, só com domínio verificado). O relay em si ainda não existe: `MAIL_RELAY_ENABLED=false` deixa o painel em "em implantação".
- Cota do disparo (decisão de 2026-10-20): conta **só** o que o cliente envia pelo relay SMTP e pela API, **por mensagem** (não por destinatário). Convites de assinatura, códigos e NFS-e não consomem. Grátis: 100/dia e 3.000/mês. Plano com o módulo `mailing`: `plan_module.daily_quota` e `included_quota` (nulo = sem limite), mês = ciclo da assinatura. Passou de qualquer limite, **bloqueia até renovar**, sem excedente. Dia vira à meia-noite de Brasília. Todo envio do relay/API chama `EmailQuotaService::reserve()` antes do SES e `release()` se o SES recusar; contador diário em `tenant_module_usage`.
- Histórico e relatório (E-mail → Enviados): `email_delivery_events` guarda cada evento do SES deduplicado pelo horário em milissegundos (o SNS reentrega). Abertura e clique vêm por mensagem e marcam todos os destinatários dela; só aparecem com `MAIL_TRACK_ENGAGEMENT=true`. Link clicado é gravado sem query string. Destinatário e assunto são dado de terceiro: só dono e admin veem.
- Bounce permanente e spam entram em `email_suppressions` (`EmailSuppressionService`). O relay e a API consultam `isSuppressed()` antes de enviar. Spam não é liberado pelo workspace; liberar um bounce tira também da lista da conta SES, que vale para todos.
- Configuração na AWS, suporte e alarmes: `docs/runbooks/email.md`.

## Cobrança (Asaas)

- Módulo `App\Modules\Billing` (não é módulo vendido: sem linha em `modules`). Gateway atrás de `PaymentGatewayContract` (`ASAAS_DRIVER=asaas|fake`).
- Pix (cobrança mensal que o cliente paga) ou cartão (recorrência cobrada sozinha). O cartão passa pelo backend direto para o Asaas e **nunca** é gravado nem logado: só bandeira, final e token. Campo novo de cartão entra no `dontFlash` de `bootstrap/app.php`.
- Quem libera acesso é o webhook `/webhooks/asaas` (token no header `asaas-access-token`), nunca a resposta da API. `BillingStandingService::refresh()` recalcula a situação do zero a partir de `billing_payments`.
- Sem pagamento o workspace fica **só leitura** (`TenantSubscription::isBlocked()`, conferido em `module.web` e `module`). A assinatura pública de documentos não é bloqueada.
- Carrinho: a assinatura tem um plano por produto (`tenant_subscription_items`, com o preço contratado). Para saber cota e excedente de um produto use `$subscription->termsFor(Module::X)` (carregue `plans.modules`); nunca "o plano da assinatura".
- Excedente: módulo que cobra além do plano implementa `BillableUsageContract` com a tag `billable-usage`; `billing:cycle` cobra no fechamento do ciclo.
- Planos são cadastrados pela equipe em `/admin/planos` (gate `access-admin-panel`, `users.is_platform_admin`, criado pelo `PlatformAdminSeeder`). O `PlanSeeder` é só para desenvolvimento.
- Operação, Asaas e problemas comuns: `docs/runbooks/cobranca.md`.

## Páginas públicas e SEO

- O tráfego principal é orgânico. Páginas públicas (`Welcome`, `Products/*`, `Docs/*`) passam por SSR. A lista fica em `PublicPagesSsrGateway::PUBLIC_COMPONENTS`, e o processo é o `inertia:start-ssr`. Nelas, nada de `window`/`document` fora de `useEffect`.
- Título, descrição, canônico, Open Graph e JSON-LD saem do Blade via `->withViewData('seo', Seo)`, e não do `<Head>`, para funcionarem mesmo sem SSR. Conteúdo, perguntas frequentes e JSON-LD vêm de `App\Http\Seo\MarketingPages`: a pergunta que aparece na tela é a mesma do `FAQPage`.
- Página pública nova: rota, `Seo`, entrada no `sitemap()` e componente na lista do SSR.

## Testes (quando houver)

- Feature tests em `tests/Feature/<Module>/` — testam o fluxo HTTP → Service → DB.
- Unit tests em `tests/Unit/<Module>/` — testam Service e Repository isoladamente, mockando o contract.
- Policies ganham tests dedicados em `tests/Feature/Policies/`.

## Decisões registradas

### SMS — levantamento de 2026-10-15, implementação adiada

**Contexto.** `auth_method='sms'` existe no banco mas só envia e-mail. Volume inicial previsto: ~100 SMS/mês. Objetivo declarado: ser dono do sistema de envio, sem depender de revendedor tipo Twilio.

**Como a cadeia funciona.** App → gateway próprio (ESME) → SMPP → SMSC da operadora → celular. Ninguém entrega sem passar pela operadora dona do número. "Ser dono" significa ser dono do gateway e ter contrato direto com Vivo, Claro e TIM, ou seja, virar integradora/broker homologado. No Brasil só existe short code de 5 ou 6 dígitos, não há remetente alfanumérico, e a portabilidade exige base da ABR Telecom ou consulta HLR para rotear.

**Caminhos avaliados.**
1. Integradora homologada com bind SMPP direto nas 3 teles: destino final, só faz sentido com milhões/mês. Condições comerciais das operadoras não são públicas.
2. Gateway próprio (Jasmin) + rotas SMPP de atacado: caminho realista a partir de 10–20 mil/mês.
3. Provedor nacional via API HTTP sem mensalidade: fase zero.
4. SIM box/chipeira ou Android com app de gateway: rota cinza, descartado (bloqueio de chip, sem DLR confiável, OTP sai de número comum, sem valor probatório).
5. Virar MVNO: desproporcional.

**Decisão.** Com ~100/mês não compensa montar gateway. Jasmin + RabbitMQ num servidor custaria ~R$ 1,50/SMS só de infra, contra R$ 0,07–0,20 num provedor nacional pré-pago. Fica para um segundo momento.

**Quando implementar, o desenho é este.**
- Módulo `App\Modules\Messaging` com `SmsGatewayContract` (driver pattern), `SmsMessage` tenant-scoped (gateway_message_id, status, parts, error_code), `SmsService`, `SendSmsJob` fino, webhook de DLR em rota pública com `withoutTenantScope()` (o gateway não tem sessão de tenant), eventos `sms.delivered`/`sms.failed` no EventCatalog.
- Fase 0: driver HTTP de provedor nacional homologado sem mensalidade. Critérios: Pix/boleto sem plano fixo, créditos sem vencimento curto, webhook de DLR, short code homologado nas 3 teles. Drivers `log` e `fake` para dev e testes. Segundo provedor em failover é só outro driver.
- Fase 1 (≥10–20 mil/mês, ou roteamento por operadora, ou bind SMPP mais barato que HTTP): driver Jasmin. Jasmin = Python/Twisted, Apache 2, HTTP API na porta 1401 (`/send` com `dlr=yes`, `dlr-url`, `dlr-level=3`; resposta `Success "uuid"`), callback de DLR exige HTTP 200 com corpo `ACK/Jasmin`. Dois conectores `smppccm` em `FailoverMTRoute` + `DefaultRoute`, filtro `^55\d{10,11}$`. Depende de RabbitMQ e Redis. Config via jCli (porta 8990), sem painel. Um usuário Jasmin para a plataforma, `tags` por tenant; ledger de cobrança no Laravel, não no billing do Jasmin (saldo em memória, rate sem moeda). Imagem do Docker Hub está parada; buildar do repo.
- Fase 2: bind direto com operadora entra como novo conector + rota. Laravel não muda.
- Texto sem acento: GSM-7 dá 160 caracteres/parte, UCS-2 cai para 70 e dobra o custo. `from` é ignorado (short code do fornecedor). Número no formato 55+DDD+número.
- Opt-out, consentimento LGPD, templates, janela de horário e rate limit por tenant/signatário ficam no SmsService, não no gateway.

**Preços de referência (out/2026, páginas comerciais, confirmar em cotação).** Rota nacional R$ 0,05–0,10 (R$ 0,12–0,15 até 5 mil/mês). Hub internacional US$ 0,025–0,035, evitar: trânsito internacional está sob regulação da Anatel. Twilio US$ 0,0599/segmento. WhatsApp mensagem de serviço US$ 0,008 com 1.000 grátis/número/mês: candidato a canal principal de OTP, com SMS como fallback.

**Regulatório em movimento.** Acórdão Anatel 201/2025 pede plano de governança do SMS A2P. ABR Telecom vai centralizar diretório de short codes (jan/2026), com cadeia operadora → broker → cliente final registrada. MP 1.396/2026 (13/10/2026) manda bloquear mensagens com códigos de acesso irregulares em 30 dias e dá 60 dias para a Anatel regulamentar.

### E-mail — decisão de 2026-10-19

**Agora.** Amazon SES no plano *Essentials* (sem mensalidade, US$ 0,16 por mil destinatários, anexo US$ 0,12/GB; cobrança por destinatário, faixas marginais). Servidor SMTP próprio descartado: subir é fácil, mas porta 25 bloqueada nas clouds, aquecimento de IP e blocklists fazem um volume baixo entregar pior que qualquer provedor.

**Depois: módulo de disparo para clientes** (cada cliente com domínio próprio). Desenho previsto:
- Domínio do cliente verificado pela API do SES: **já existe** (Workspace → Domínio de envio, `SendingDomainService`), hoje usado nos e-mails de assinatura e NFS-e. O módulo de disparo reaproveita. Só domínio próprio; nunca `@gmail.com` e afins (o DMARC deles reprova).
- Isolamento por cliente: a AWS revisa a conta inteira com bounce > 5% ou reclamação > 0,1%. O Laravel pausa o cliente bem antes, por configuration set e eventos. O recurso *Tenants* do SES só vem incluído no plano Enterprise (US$ 500/mês).
- Marketing (newsletter, campanhas) numa conta AWS separada da transacional, para não derrubar os e-mails de assinatura.

**SMS pela AWS (End User Messaging), consultado em 2026-10-19.** Brasil a US$ 0,03/SMS (~R$ 0,17), long code só por chamado de suporte, short code dedicado com US$ 330 de setup + US$ 330/mês. Mais caro que provedor nacional e com origem incerta (risco Anatel): no máximo um driver de failover. WhatsApp pelo mesmo serviço: US$ 0,005 da AWS + taxa da Meta (autenticação/utilidade US$ 0,0051, marketing US$ 0,0471), ou seja, OTP a ~US$ 0,01.

## Comandos do dia-a-dia

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev          # Vite HMR
./vendor/bin/sail npm run build        # produção
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail artisan queue:work
```

URL do app: http://localhost:8080 · MySQL: localhost:3308

Deploy em produção (servidor, `.env`, AWS, ordem dos passos e o que conferir): `docs/runbooks/deploy.md`. Mudou algo que o deploy precisa saber (variável, migration de dados, tarefa agendada, permissão na AWS)? Atualize esse arquivo junto.
