# Runbook: deploy em produção

O que o servidor precisa ter, o `.env` completo, o que montar na AWS e a ordem dos passos. Os detalhes de cada assunto ficam nos runbooks próprios:
- arquivos: [armazenamento.md](armazenamento.md);
- e-mail: [email.md](email.md);
- certificados: [certificados.md](certificados.md);
- cobrança (Asaas): [cobranca.md](cobranca.md).

## Pendente no próximo deploy (situação em 2026-10-20)

O último commit é `7b68334` (arquivos no S3). Ainda **não commitado** e indo neste deploy:

- **Módulo de e-mail (`App\Modules\Mailing`):**
  - envio por Amazon SES;
  - rastreio de entrega, abertura e clique;
  - domínio de envio por workspace;
  - credenciais SMTP (o relay ainda não existe);
  - cota de disparo;
  - telas **E-mail → Enviados** (visão geral, mensagens, bloqueados).
- **Medidores de uso** do plano na barra lateral (assinaturas, notas, e-mails).
- **Nova tela inicial:**
  - ritmo do faturamento em notas;
  - conta na Moduhub;
  - assinaturas pendentes;
  - saúde dos e-mails;
  - alertas.
- Dependências novas no Composer: `aws/aws-sdk-php` e `aws/aws-php-sns-message-validator`.
- Migrations novas:
  - `2026_10_19_000012` a `000013`;
  - `2026_10_20_000001` a `000003`.

  Elas criam as tabelas de e-mail, a coluna `plan_module.daily_quota`, a tabela `tenant_module_usage` e o módulo `mailing`.

- **Cobrança pelo Asaas (`App\Modules\Billing`):**
  - tela **Workspace → Assinatura**, com Pix ou cartão recorrente;
  - webhook `/webhooks/asaas`;
  - excedente cobrado no fechamento do ciclo;
  - bloqueio só leitura por falta de pagamento;
  - tarefa `billing:cycle` de hora em hora.

  Migrations `2026_10_20_000004` e `000005`. A `000005` cria o carrinho: a coluna `tenant_subscriptions.plan_id` vira item em `tenant_subscription_items` e é apagada. Ela também cria `users.is_platform_admin`.

  Variáveis `ASAAS_*` e `BILLING_GRACE_DAYS`.

  **Antes de abrir as vendas:**
  - crie o admin com o `PlatformAdminSeeder`;
  - cadastre os planos de cada produto em `/admin/planos`;
  - desligue os combos antigos.

  Detalhes em [cobranca.md](cobranca.md), seção "Admin da plataforma". **Antes de subir**, leia "Workspaces antigos" em [cobranca.md](cobranca.md): teste grátis vencido sem assinatura vira só leitura na primeira rodada.

- **SEO das páginas públicas:**
  - SSR do Inertia, com um processo Node novo (veja "Processos que precisam estar rodando");
  - páginas de produto (`/assinatura-eletronica`, `/emissor-nfse`, `/disparo-de-email`);
  - meta tags, Open Graph e JSON-LD;
  - `robots.txt`, `sitemap.xml` e `llms.txt` gerados pelo Laravel. O `public/robots.txt` estático foi apagado e, se o deploy copiar arquivos, não pode voltar.

  O `APP_URL` vira o endereço canônico de todas as páginas e o do sitemap. Use o domínio onde o site vai ser encontrado.

  Depois do deploy, envie `https://<APP_URL>/sitemap.xml` ao Google Search Console.

Se a produção ainda não recebeu os commits anteriores (S3 e certificados), siga também a seção "Servidor novo ou primeiro deploy com S3".

## Servidor

| Item | Exigência |
|---|---|
| PHP | 8.4 ou mais novo (o desenvolvimento roda em 8.5), com a última correção de segurança da linha: no mínimo 8.4.27 (RCE de out/2026). Extensões: `bcmath`, `curl`, `dom`, `gd` ou `imagick`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `xml`, `zip`, `fileinfo`. Em produção o PHP vem do PPA `ondrej/php`, que o `unattended-upgrades` do Ubuntu não atualiza sozinho: veja "Atualização do PHP". |
| Memória do FPM | `memory_limit = 512M` em `/etc/php/8.4/mods-available/moduhub.ini`, ligado só ao FPM (`fpm/conf.d/99-moduhub.ini`). O CLI fica sem limite (`-1`). Com `pm.max_children = 5`, o pior caso é 2,5 GB. |
| Banco | MySQL 8. Os relatórios usam `CONVERT_TZ` com offset fixo (`-03:00`), que não depende das tabelas de fuso do MySQL. |
| Node | Só para o build (`npm ci && npm run build`). Não precisa rodar no servidor se o build vier pronto. |
| nginx | `fastcgi_buffer_size 16k; fastcgi_buffers 16 16k;` no `location ~ \.php$`. O padrão de 4 KB não comporta o cabeçalho `Link` de preload somado aos cookies: o PHP responde e o nginx devolve 502 (`upstream sent too big header` no `error.log`). O app já limita o `Link` a 8 assets; o buffer maior é a folga. |
| HTTPS | Obrigatório. O SNS só entrega eventos do SES em `https://`, e a assinatura de documentos exige conexão segura. |
| Fuso do app | `UTC` (já fixo em `config/app.php`). Datas de negócio (dia da cota, mês das notas) usam Brasília no código. |

## Processos que precisam estar rodando

1. **PHP-FPM / servidor web** apontando para `public/`.
2. **Fila:** `php artisan queue:work --tries=3 --max-time=3600`, sob Supervisor ou systemd.
   - Por ela passam e-mails (convites, NFS-e), webhooks dos clientes e entrega da nota por e-mail.
   - Sem fila rodando, nada disso sai.
3. **SSR (Node 20+):** `php artisan inertia:start-ssr`, sob Supervisor ou systemd, com reinício automático.
   - Renderiza no servidor as páginas públicas: página inicial, `/assinatura-eletronica`, `/emissor-nfse`, `/disparo-de-email` e `/docs/api`. Assim, Google e robôs de IA leem o texto sem executar JavaScript. O painel não passa por ele.
   - Escuta em `127.0.0.1:13714` (`INERTIA_SSR_URL`); não exponha essa porta.
   - Se o processo cair, o site continua no ar, só que renderizado no navegador. Título, descrição e JSON-LD continuam no HTML, porque saem do Blade. O texto da página some para os robôs até o processo voltar.
   - Depois de cada `npm run build`, rode `php artisan inertia:stop-ssr`. O Supervisor sobe o processo de novo com o bundle novo.
   - Exemplo pronto para a fila e o SSR: [supervisor/moduhub.conf](supervisor/moduhub.conf). As instruções de instalação estão no topo do arquivo.
4. **Agendador:** uma linha no cron.

   ```cron
   * * * * * cd /caminho/do/moduhub && php artisan schedule:run >> /dev/null 2>&1
   ```

   | Comando | Quando |
   |---|---|
   | `mailing:check-domains` | de hora em hora: confere o DNS dos domínios de envio dos clientes |
   | `invoices:sync-documents` | de hora em hora: notas recebidas (ADN) |
   | `invoices:sync-agreements` | domingo, 03:00: convênios dos municípios |
   | `invoices:check-certificates` | 08:00 de Brasília: aviso de certificado do emitente vencendo |
   | `signatures:check-platform-certificate` | 08:00 de Brasília: certificado da Moduhub |
   | `billing:cycle` | de hora em hora: fim do teste, excedente do ciclo, bloqueio por atraso |

## Atualização do PHP

O servidor de produção (Ubuntu 24.04) instala o PHP pelo PPA `ondrej/php`. Por padrão, o `unattended-upgrades` só aplica o repositório de segurança do Ubuntu, e foi assim que a produção ficou de out/2025 a out/2026 na 8.4.13. **Pendente:** criar `/etc/apt/apt.conf.d/52unattended-ondrej-php` para incluir o PPA, e conferir com `unattended-upgrade --dry-run --debug 2>&1 | grep ondrej`:

```
Unattended-Upgrade::Allowed-Origins {
    "LP-PPA-ondrej-php:${distro_codename}";
};
```

Atualização manual, quando sai um alerta de segurança:

```bash
apt update
apt install --only-upgrade -o Dpkg::Options::="--force-confold" 'php8.4*' php-common
php-fpm8.4 -t && systemctl restart php8.4-fpm
supervisorctl restart all          # a fila e o SSR seguram o binário antigo na memória
php -v; php-fpm8.4 -v              # os dois na versão nova
```

O `--force-confold` mantém o `php.ini` e o pool editados. O servidor roda outros sistemas no mesmo PHP e no mesmo Supervisor: o `restart all` reinicia todos eles.

## `.env` de produção

Os valores secretos ficam no gerenciador de senhas da empresa. Nada disso vai para o Git.

```dotenv
APP_NAME=Moduhub
APP_ENV=production
APP_DEBUG=false
APP_KEY=                                  # php artisan key:generate, uma vez só
APP_URL=https://app.moduhub.com.br        # o domínio real; o SNS chama APP_URL/webhooks/ses
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=moduhub
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database                      # ou redis, se houver

# E-mail (docs/runbooks/email.md)
MAIL_MAILER=ses-v2
MAIL_FROM_ADDRESS="nao-responda@moduhub.com.br"
MAIL_FROM_NAME="Moduhub"
SES_KEY=...                               # obrigatório: o usuário AWS_* só enxerga o S3 (armazenamento.md, passo 6)
SES_SECRET=...
SES_REGION=sa-east-1
SES_CONFIGURATION_SET=moduhub-transactional
SES_SNS_TOPIC_ARN=arn:aws:sns:sa-east-1:851725549821:moduhub-ses-events
SES_IDENTITY_DRIVER=ses                   # domínio de envio dos clientes pela API do SES
MAIL_TRACK_ENGAGEMENT=true                # só depois de marcar Opens e Clicks no configuration set
MAIL_RELAY_ENABLED=false                  # o relay smtp.moduhub.com.br ainda não existe
MAIL_RELAY_HOST=smtp.moduhub.com.br
MAIL_FREE_DAILY_QUOTA=100
MAIL_FREE_MONTHLY_QUOTA=3000

# Arquivos (docs/runbooks/armazenamento.md)
FILE_STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=sa-east-1
AWS_BUCKET=moduhub-prod-files
AWS_KMS_KEY_ID=arn:aws:kms:sa-east-1:851725549821:key/...   # alias/moduhub-files, ARN completo

# NFS-e
INVOICES_SEFIN_DRIVER=http
INVOICES_SEFIN_TIMEOUT=30

# Certificados (docs/runbooks/certificados.md)
SIGNATURES_PLATFORM_CNPJ=00.000.000/0001-00   # CNPJ da Moduhub: o certificado enviado em /admin/certificado precisa ser dele
# Opcional: reserva no servidor, usada só quando o painel não tem certificado
SIGNATURES_PLATFORM_CERT_PATH=/caminho/seguro/moduhub.pfx   # fora de public/, legível só pelo usuário do PHP
SIGNATURES_PLATFORM_CERT_PASSWORD=...
SIGNATURES_PLATFORM_CERT_ALERT_EMAILS=financeiro@...,tecnico@...
CERTIFICATES_ENCRYPTION_KEY=...           # diferente da APP_KEY; perder = perder os certificados dos clientes
CERTIFICATES_ENCRYPTION_PREVIOUS_KEYS=

# Cobrança (docs/runbooks/cobranca.md)
ASAAS_DRIVER=asaas
ASAAS_API_KEY=...                         # chave de produção
ASAAS_BASE_URL=https://api.asaas.com/v3
ASAAS_WEBHOOK_TOKEN=...                   # igual ao token do webhook no painel do Asaas
BILLING_GRACE_DAYS=7
```

**Atenção:**
- `CERTIFICATES_ENCRYPTION_KEY` e `APP_KEY` não podem mudar entre deploys. Trocar a `APP_KEY` derruba sessões e tokens cifrados. Trocar a chave dos certificados sem o procedimento do runbook perde os certificados dos clientes.
- O `.env` **local** hoje está com `MAIL_MAILER=ses-v2` e `MAIL_FROM_ADDRESS=noreply@moduhub.local`. Não copie o local para produção. Para desenvolver, prefira voltar o local para `smtp` (Mailpit).

## AWS

### S3
Bucket, IAM, lifecycle e Object Lock: [armazenamento.md](armazenamento.md), seção "Montar na AWS".

### SES (já feito em 2026-10-19/20, conferir)
- [x] Conta no plano Essentials, região `sa-east-1`.
- [x] `moduhub.com.br` verificado (DKIM, MAIL FROM `bounce.moduhub.com.br`, DMARC).
- [x] Configuration set `moduhub-transactional` com destino SNS no tópico `moduhub-ses-events`.
- [ ] **No destino SNS, marcar também *Opens* e *Clicks*** (além de Deliveries, Hard bounces, Complaints, Rejects e Delivery delays).
- [ ] **Política IAM do usuário da aplicação** completa (a lista está em [email.md](email.md)):
  - envio: `ses:SendEmail`, `ses:SendRawEmail`;
  - domínios dos clientes: `ses:CreateEmailIdentity`, `ses:GetEmailIdentity`, `ses:DeleteEmailIdentity`, `ses:PutEmailIdentityMailFromAttributes`;
  - lista de bloqueio: `ses:DeleteSuppressedDestination`, que é a permissão nova.
- [ ] **Pedir a saída do sandbox** antes de vender o e-mail. No sandbox só sai para endereços verificados, até 200 por dia.
- [ ] Alarmes do CloudWatch de bounce acima de 5% e reclamação acima de 0,1% ([email.md](email.md), "O que vigiar").

### SNS: só depois que o site estiver no ar em HTTPS
- [ ] No tópico `moduhub-ses-events`, crie uma assinatura **HTTPS** para `https://<APP_URL>/webhooks/ses`. A aplicação confirma sozinha.
- [ ] Confira em *Subscriptions* que o status virou **Confirmed**.

Antes disso nenhum evento chega: entrega, abertura e devolução ficam como "Enviado".

## Passo a passo do deploy

```bash
php artisan down                                  # se houver tráfego

git pull                                          # ou o artefato do CI
composer install --no-dev --optimize-autoloader
npm ci && npm run build                           # gera public/build e bootstrap/ssr (SSR)

php artisan migrate --force
php artisan db:seed --class=ModuleSeeder --force  # garante os módulos (o mailing também entra pela migration)

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart                         # os workers pegam o código novo
php artisan inertia:stop-ssr                      # o Supervisor sobe o SSR com o bundle novo
php artisan up
```

Depois do primeiro deploy com o módulo de e-mail:

1. Crie a assinatura HTTPS do SNS (seção acima).
2. Ligue `MAIL_TRACK_ENGAGEMENT=true` só depois de marcar *Opens* e *Clicks*, e rode `php artisan config:cache`.

## Conferir depois do deploy

- [ ] `curl -s https://<APP_URL>/ | grep -c '<h1'` dá `1`. Com o SSR fora do ar, dá `0`.
- [ ] `https://<APP_URL>/robots.txt` e `https://<APP_URL>/sitemap.xml` respondem com o domínio de produção.
- [ ] Login e tela inicial abrem sem erro. Como proprietário, aparecem "Sua conta na Moduhub" e os alertas.
- [ ] **Workspace → Domínio de envio:** mande um teste para o seu e-mail. Em segundos, **E-mail → Enviados → Mensagens** mostra *Entregue*. Isso prova que o SNS está chegando. Ao abrir o e-mail, aparece *Aberto*.
- [ ] **Admin → Certificado:** envie o e-CNPJ da Moduhub (primeiro deploy ou renovação). O aviso chega em `SIGNATURES_PLATFORM_CERT_ALERT_EMAILS` e a tela mostra o titular e a validade.
- [ ] Envie um documento para assinatura e confira que o convite chegou.
- [ ] Emita uma NFS-e em produção restrita (ou baixe o XML e o DANFSe de uma existente), para validar S3 e certificado.
- [ ] `php artisan schedule:list` mostra as seis tarefas.
- [ ] Webhook do Asaas cadastrado ([cobranca.md](cobranca.md), "Configurar no Asaas"). No log de webhooks do Asaas, o primeiro evento volta 200.
- [ ] O worker da fila está vivo (`supervisorctl status` ou equivalente).
- [ ] No `storage/logs/laravel.log` não aparece `critical` de certificado da plataforma.

## O que ainda não está pronto (não bloqueia o deploy)

| Item | Situação |
|---|---|
| Relay `smtp.moduhub.com.br` | **Não existe.** As credenciais SMTP aparecem no painel com aviso de "em implantação" (`MAIL_RELAY_ENABLED=false`). A página de vendas já vende o disparo de e-mail como disponível, com exemplo de SMTP. Não cadastre plano de e-mail à venda antes de o relay entrar no ar. |
| Planos de e-mail | Grade anotada (grátis, R$ 24,90, R$ 99, R$ 179, R$ 499), mas não cadastrada em `plans`/`plan_module`. Sem plano, todo workspace usa a cota grátis. |
| Cobrança | Integrada ao Asaas. Falta validar no sandbox os pontos listados em [cobranca.md](cobranca.md) e pedir a tokenização de cartão em produção. |
| Saída do sandbox do SES | Pedir à AWS antes de vender o disparo. |
