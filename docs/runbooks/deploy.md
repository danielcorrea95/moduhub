# Runbook: deploy em produção

O que o servidor precisa ter, o `.env` completo, o que montar na AWS e a ordem dos passos. Os detalhes de cada assunto ficam nos runbooks próprios:
- arquivos: [armazenamento.md](armazenamento.md);
- e-mail: [email.md](email.md);
- certificados: [certificados.md](certificados.md).

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

Se a produção ainda não recebeu os commits anteriores (S3 e certificados), siga também a seção "Servidor novo ou primeiro deploy com S3".

## Servidor

| Item | Exigência |
|---|---|
| PHP | 8.4 ou mais novo (o desenvolvimento roda em 8.5). Extensões: `bcmath`, `curl`, `dom`, `gd` ou `imagick`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `xml`, `zip`, `fileinfo`. |
| Banco | MySQL 8. Os relatórios usam `CONVERT_TZ` com offset fixo (`-03:00`), que não depende das tabelas de fuso do MySQL. |
| Node | Só para o build (`npm ci && npm run build`). Não precisa rodar no servidor se o build vier pronto. |
| HTTPS | Obrigatório. O SNS só entrega eventos do SES em `https://`, e a assinatura de documentos exige conexão segura. |
| Fuso do app | `UTC` (já fixo em `config/app.php`). Datas de negócio (dia da cota, mês das notas) usam Brasília no código. |

## Processos que precisam estar rodando

1. **PHP-FPM / servidor web** apontando para `public/`.
2. **Fila:** `php artisan queue:work --tries=3 --max-time=3600`, sob Supervisor ou systemd.
   - Por ela passam e-mails (convites, NFS-e), webhooks dos clientes e entrega da nota por e-mail.
   - Sem fila rodando, nada disso sai.
3. **Agendador:** uma linha no cron.

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
SES_KEY=                                  # vazio = usa AWS_ACCESS_KEY_ID / role da instância
SES_SECRET=
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
AWS_KMS_KEY_ID=

# NFS-e
INVOICES_SEFIN_DRIVER=http
INVOICES_SEFIN_TIMEOUT=30

# Certificados (docs/runbooks/certificados.md)
SIGNATURES_PLATFORM_CERT_PATH=/caminho/seguro/moduhub.pfx   # fora de public/, legível só pelo usuário do PHP
SIGNATURES_PLATFORM_CERT_PASSWORD=...
SIGNATURES_PLATFORM_CERT_ALERT_EMAILS=financeiro@...,tecnico@...
CERTIFICATES_ENCRYPTION_KEY=...           # diferente da APP_KEY; perder = perder os certificados dos clientes
CERTIFICATES_ENCRYPTION_PREVIOUS_KEYS=
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
npm ci && npm run build                           # se o build não vier pronto

php artisan migrate --force
php artisan db:seed --class=ModuleSeeder --force  # garante os módulos (o mailing também entra pela migration)

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart                         # os workers pegam o código novo
php artisan up
```

Depois do primeiro deploy com o módulo de e-mail:

1. Crie a assinatura HTTPS do SNS (seção acima).
2. Ligue `MAIL_TRACK_ENGAGEMENT=true` só depois de marcar *Opens* e *Clicks*, e rode `php artisan config:cache`.

## Conferir depois do deploy

- [ ] Login e tela inicial abrem sem erro. Como proprietário, aparecem "Sua conta na Moduhub" e os alertas.
- [ ] **Workspace → Domínio de envio:** mande um teste para o seu e-mail. Em segundos, **E-mail → Enviados → Mensagens** mostra *Entregue*. Isso prova que o SNS está chegando. Ao abrir o e-mail, aparece *Aberto*.
- [ ] Envie um documento para assinatura e confira que o convite chegou.
- [ ] Emita uma NFS-e em produção restrita (ou baixe o XML e o DANFSe de uma existente), para validar S3 e certificado.
- [ ] `php artisan schedule:list` mostra as cinco tarefas.
- [ ] O worker da fila está vivo (`supervisorctl status` ou equivalente).
- [ ] No `storage/logs/laravel.log` não aparece `critical` de certificado da plataforma.

## O que ainda não está pronto (não bloqueia o deploy)

| Item | Situação |
|---|---|
| Relay `smtp.moduhub.com.br` | Não existe. As credenciais SMTP aparecem no painel com aviso de "em implantação" (`MAIL_RELAY_ENABLED=false`). |
| Planos de e-mail | Grade anotada (grátis, R$ 24,90, R$ 99, R$ 179, R$ 499), mas não cadastrada em `plans`/`plan_module`. Sem plano, todo workspace usa a cota grátis. |
| Cobrança | A tela inicial mostra a **previsão** do ciclo (plano + excedente de notas). Não existe fatura nem integração de pagamento. |
| Saída do sandbox do SES | Pedir à AWS antes de vender o disparo. |
