# Runbook: envio de e-mail (Amazon SES)

Para quem opera a Moduhub. Cobre como o e-mail sai, como configurar o SES, como confirmar se um e-mail chegou e o que vigiar.

## Como funciona

Todo e-mail da plataforma sai pelo mailer `ses-v2`. O remetente padrão é o domínio da Moduhub (`MAIL_FROM_ADDRESS`).

O workspace pode cadastrar o próprio domínio em **Workspace → Domínio de envio**. Depois de verificado, quatro e-mails saem com o remetente dele: convite de assinatura, código de verificação, documento concluído e NFS-e (`TenantSender`). E-mails da conta (senha, verificação de cadastro, convite de membro) e alertas internos continuam saindo pela Moduhub.

- Ao cadastrar, a aplicação cria a identidade no SES (DKIM 2048, MAIL FROM `bounce.<domínio>`, configuration set padrão) e mostra os registros DNS ao cliente.
- `mailing:check-domains` roda de hora em hora e confere todos os domínios. Um domínio que perde o DNS vira `failed`, e os e-mails voltam na hora para o remetente da Moduhub (log `E-mail: domínio de envio deixou de estar verificado`).
- Se o DKIM ficar `FAILED` (o SES desiste depois de 72h sem achar os registros), o cliente remove e adiciona o domínio de novo. Isso gera tokens novos.
- Remover o domínio no painel apaga a identidade no SES.
- Em dev e nos testes, `SES_IDENTITY_DRIVER=fake`: nada vai para a AWS, e o domínio verifica no primeiro "Verificar agora".

```
Mailable → SES (configuration set) → destinatário
                 └→ eventos → SNS → POST /webhooks/ses → email_deliveries
```

- Quando o SES aceita o envio, o listener `RecordSentEmail` grava uma linha por destinatário em `email_deliveries` com o `message_id` do SES, o workspace, a classe do Mailable e o assunto (status `sent`).
- O configuration set publica `Delivery`, `Bounce`, `Complaint`, `Reject`, `DeliveryDelay`, `Open` e `Click` num tópico SNS. O SNS chama `/webhooks/ses`, que confere a assinatura da AWS e o tópico (`SES_SNS_TOPIC_ARN`) e atualiza a linha.
- O status só avança (`sent` → `delayed` → `delivered` → `bounced`/`rejected` → `complained`), porque os eventos chegam fora de ordem. As datas (`delivered_at`, `bounced_at`, `complained_at`) ficam sempre registradas.
- Bounce `Transient` (caixa cheia, servidor fora) vira `delayed`: o SES ainda tenta de novo.
- Com outro mailer (`log`, `smtp`/Mailpit, `array`) não há id do SES e nada é gravado.

## Montar na AWS

Tudo em `sa-east-1`. Nos exemplos o domínio é `moduhub.com.br`; troque pelo real.

1. **Identidade do domínio**: SES → *Identities* → *Create identity* → *Domain*. Easy DKIM com RSA 2048. Publique os 3 CNAMEs de DKIM no DNS.
2. **MAIL FROM próprio** na mesma identidade: `bounce.moduhub.com.br`, com *Behavior on MX failure* = *Use default MAIL FROM*. No DNS:
   - `bounce.moduhub.com.br` MX `10 feedback-smtp.sa-east-1.amazonses.com`
   - `bounce.moduhub.com.br` TXT `"v=spf1 include:amazonses.com ~all"`
3. **DMARC** em `_dmarc.moduhub.com.br`: comece com `v=DMARC1; p=none; rua=mailto:dmarc@moduhub.com.br`. Depois de 2 a 4 semanas de relatórios limpos, suba para `p=quarantine`.
4. **Tópico SNS** `moduhub-ses-events` (Standard).
5. **Configuration set** `moduhub-transactional` → *Event destinations* → SNS → o tópico acima, com os eventos *Deliveries*, *Hard bounces*, *Complaints*, *Rejects*, *Delivery delays*, *Opens* e *Clicks*. Depois de marcar *Opens* e *Clicks*, ligue `MAIL_TRACK_ENGAGEMENT=true`: sem isso o painel esconde abertura e clique em vez de mostrar 0%.
6. **Deploy** com `SES_SNS_TOPIC_ARN` preenchido **antes** do passo 7. Sem ele o webhook recusa tudo (403), inclusive a confirmação.
7. **Inscrição** no tópico: protocolo HTTPS, endpoint `https://<app>/webhooks/ses`. A aplicação confirma sozinha (log `SES: inscrição do webhook no tópico SNS confirmada`). Confira em SNS → *Subscriptions* que o status virou *Confirmed*.
8. **Lista de supressão da conta**: SES → *Suppression list* → ligada para *Bounce* e *Complaint*. O SES deixa de tentar esses endereços e o envio aparece como `bounced` em `email_deliveries`.
9. **Sair do sandbox**: SES → *Account dashboard* → *Request production access*. Tipo *Transactional*. Explique: e-mails de assinatura de documentos e NFS-e enviados só a destinatários informados pelo cliente; bounces e reclamações tratados pelo configuration set, via SNS, com lista de supressão da conta. A aprovação leva de 1 a 2 dias.
10. **Plano**: *Essentials* (sem mensalidade, US$ 0,16 por mil destinatários; anexo a US$ 0,12/GB). Ver [preços](https://aws.amazon.com/ses/pricing/).

### Usuário IAM

Use um usuário ou role só para o e-mail (`SES_KEY`/`SES_SECRET`). Sem eles, a aplicação usa as mesmas credenciais do S3. O envio e a gestão de identidades valem para `identity/*`, porque cada workspace cadastra o próprio domínio.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["ses:SendEmail", "ses:SendRawEmail"],
      "Resource": [
        "arn:aws:ses:sa-east-1:<conta>:identity/*",
        "arn:aws:ses:sa-east-1:<conta>:configuration-set/moduhub-transactional"
      ]
    },
    {
      "Effect": "Allow",
      "Action": [
        "ses:CreateEmailIdentity",
        "ses:GetEmailIdentity",
        "ses:DeleteEmailIdentity",
        "ses:PutEmailIdentityMailFromAttributes"
      ],
      "Resource": "arn:aws:ses:sa-east-1:<conta>:identity/*"
    },
    {
      "Effect": "Allow",
      "Action": "ses:CreateEmailIdentity",
      "Resource": "arn:aws:ses:sa-east-1:<conta>:configuration-set/moduhub-transactional"
    },
    {
      "Effect": "Allow",
      "Action": "ses:DeleteSuppressedDestination",
      "Resource": "*"
    }
  ]
}
```

### `.env` de produção

```
MAIL_MAILER=ses-v2
MAIL_FROM_ADDRESS="nao-responda@moduhub.com.br"
MAIL_FROM_NAME="Moduhub"
SES_REGION=sa-east-1
SES_CONFIGURATION_SET=moduhub-transactional
SES_SNS_TOPIC_ARN=arn:aws:sns:sa-east-1:<conta>:moduhub-ses-events
```

## Relay SMTP (`relay.moduhub.com.br`)

Não é `smtp.moduhub.com.br`: esse nome (e o MX, o `pop`) é da Locaweb, onde ficam as caixas `@moduhub.com.br` da equipe. Não mexa nele.

O sistema do cliente (site, ERP, outro Laravel) envia por SMTP com a credencial `mdhb_smtp_*` gerada em **Workspace → Domínio de envio → SMTP**. Nada muda na AWS por cliente: tudo sai pela mesma conta SES, com a identidade que o painel criou para o domínio dele.

```
sistema do cliente → relay.moduhub.com.br:587 (STARTTLS) ou :465 (TLS)
                     Haraka (smtp-relay/, plugin moduhub.js)
                     → POST APP_URL/api/v1/internal/smtp-relay/auth      (no AUTH)
                     → POST APP_URL/api/v1/internal/smtp-relay/messages  (no fim do DATA)
                       Laravel (SmtpRelayService) → SES → email_deliveries
```

O Haraka só fala o protocolo. As regras ficam no Laravel, nesta ordem:

1. A credencial existe, a senha confere (SHA-256) e o workspace está ativo. Dez senhas erradas do mesmo IP bloqueiam o login por 5 minutos.
2. A assinatura não está bloqueada por falta de pagamento.
3. O workspace tem domínio de envio verificado, e o `From` (e o `Sender`, se houver) é desse domínio.
4. Os cabeçalhos `Bcc` e `Return-Path` são removidos. O corpo segue intacto.
5. Destinatários em `email_suppressions` são pulados. Se todos estiverem lá, a mensagem é recusada.
6. A cota conta 1 por mensagem (`EmailQuotaService::reserve()`) e é devolvida se o SES recusar.
7. O envio vai pelo cliente SES do mailer `ses-v2`, com o configuration set. A mensagem entra em `email_deliveries` com a origem "SMTP do cliente" e recebe os eventos como qualquer outra.

O Haraka nunca entrega direto na internet: toda mensagem termina em aceite ou recusa. A porta 25 continua fechada.

| Resposta ao cliente | Causa |
|---|---|
| `535 5.7.8` | Usuário ou senha errados, credencial revogada ou workspace inativo. |
| `454 4.7.0 Muitas tentativas` | Dez senhas erradas do mesmo IP. Passa em 5 minutos. |
| `454 4.7.0 Autenticação indisponível` | O Haraka não falou com o Laravel. Veja `storage/logs/smtp-relay.log`: `HTTP 403` é segredo diferente entre `.env` e `moduhub.ini`; erro de conexão é `endpoint` errado ou servidor que não alcança o próprio domínio (veja "Instalar", passo 9). |
| `550 5.7.1 O remetente precisa ser...` | `From` fora do domínio verificado do workspace. |
| `550 5.7.1 ... domínio de envio verificado` | O workspace não tem domínio verificado (ou ele caiu para `failed`). |
| `550 5.4.5` | Cota do dia ou do mês esgotada. |
| `550 5.1.1` | Todos os destinatários estão bloqueados (devolução ou spam). |
| `554 5.7.1 Mensagem recusada pelo provedor` | O SES recusou. Com a conta no sandbox, só sai para endereço verificado. |
| `450/451 4.3.0` | SES limitando a taxa ou fora do ar, ou o Laravel com erro. Veja `laravel.log` (`Relay SMTP: SES recusou`). O cliente tenta de novo sozinho. |

### Instalar no servidor (uma vez)

Os caminhos supõem o app em `/var/www/moduhub`.

1. **DNS:** registro `A` de `relay.moduhub.com.br` para o IP do servidor.
2. **Firewall:** libere a entrada TCP **587** e **465** no `ufw` e no grupo de segurança da cloud. Não abra a 25.
3. **Usuário do processo:** `sudo adduser --system --group --no-create-home haraka`
4. **Dependências** (Node 20+, o mesmo do SSR):

   ```bash
   cd /var/www/moduhub/smtp-relay && npm ci --omit=dev
   ```

5. **Segredo compartilhado:** gere com `openssl rand -hex 32`.
   - No `.env` do Laravel: `MAIL_RELAY_SECRET=<segredo>`.
   - No relay:

     ```bash
     cd /var/www/moduhub/smtp-relay/config
     sudo cp moduhub.ini.example moduhub.ini     # preencha endpoint e secret
     sudo chown root:haraka moduhub.ini && sudo chmod 640 moduhub.ini
     ```

6. **Certificado TLS** (Let's Encrypt). O deploy hook copia o certificado para `config/tls_*.pem` e reinicia o relay, na emissão e em cada renovação:

   ```bash
   sudo certbot certonly --nginx -d relay.moduhub.com.br \
     --deploy-hook /var/www/moduhub/smtp-relay/bin/install-cert.sh
   ```

   Se o `--nginx` não achar onde responder o desafio, crie no nginx um `server` na porta 80 com `server_name relay.moduhub.com.br` e rode de novo.

7. **Supervisor:** copie o bloco `[program:moduhub-smtp-relay]` de [supervisor/moduhub.conf](supervisor/moduhub.conf) e rode `sudo supervisorctl reread && sudo supervisorctl update`.
8. **Tamanho do corpo:** a mensagem vai ao Laravel em base64 (+33%). Com o limite padrão de 10 MB, o location do app no nginx precisa de `client_max_body_size 20m`, e o PHP de `post_max_size = 20M`. Confira os valores atuais antes de mudar.
9. **O servidor precisa alcançar o próprio domínio:** `curl -sI https://app.moduhub.com.br/up` rodado no servidor deve responder 200. Se não responder, adicione `127.0.0.1 app.moduhub.com.br` ao `/etc/hosts`.
10. **Ligar no painel:** `MAIL_RELAY_ENABLED=true` no `.env` e `php artisan config:cache`. A tela de SMTP deixa de mostrar "em implantação".
11. **Testar:**

    ```bash
    openssl s_client -starttls smtp -connect relay.moduhub.com.br:587 -crlf -quiet   # mostra o certificado; EHLO x lista AUTH PLAIN LOGIN
    swaks --server relay.moduhub.com.br:587 --tls --auth LOGIN \
      --auth-user mdhb_smtp_... --auth-password '...' \
      --from contato@<domínio verificado> --to success@simulator.amazonses.com
    ```

    A resposta final é `250 2.0.0 Mensagem aceita (<id do SES>)`, e a mensagem aparece em **E-mail → Enviados**.

### No deploy

Só quando algo mudou em `smtp-relay/`: `cd smtp-relay && npm ci --omit=dev && sudo supervisorctl restart moduhub-smtp-relay`. Mudança só no Laravel não exige reiniciar o relay.

### Configurar no sistema do cliente

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp            # STARTTLS na 587; use smtps com a porta 465
MAIL_HOST=relay.moduhub.com.br
MAIL_PORT=587
MAIL_USERNAME=mdhb_smtp_...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="noreply@<domínio verificado>"
```

## Testar

- **Local**: o Sail sobe o Mailpit. Use `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` e abra http://localhost:8025. Nada vai para `email_deliveries`.
- **Relay local**: em `smtp-relay/config/`, use `listen=127.0.0.1:2587` e `smtps_port=2465` no `smtp.ini`, tire `user`/`group`, gere um certificado autoassinado (`openssl req -x509 -newkey rsa:2048 -nodes -keyout tls_key.pem -out tls_cert.pem -days 1 -subj /CN=localhost`) e aponte `endpoint` para o app local. Depois rode `npm install && npx haraka -c .`. Não faça commit desses arquivos.
- **Em produção, sem afetar a reputação**: mande para o simulador do SES. Cada envio é cobrado como e-mail normal.
  - `success@simulator.amazonses.com` → `delivered`
  - `bounce@simulator.amazonses.com` → `bounced`
  - `complaint@simulator.amazonses.com` → `complained`

  ```bash
  php artisan tinker --execute="Mail::raw('teste', fn (\$m) => \$m->to('bounce@simulator.amazonses.com')->subject('teste SES'));"
  ```

## Suporte: "o signatário diz que não recebeu"

```php
App\Modules\Mailing\Models\EmailDelivery::withoutTenantScope()
    ->where('recipient', 'fulano@empresa.com.br')
    ->latest('sent_at')
    ->get(['status', 'bounce_type', 'detail', 'subject', 'sent_at', 'delivered_at']);
```

| Status | O que dizer |
|---|---|
| `delivered` | O servidor do destinatário aceitou. Procurar no spam ou em filtros da empresa. |
| `delayed` | O SES ainda está tentando. Ver `detail` (caixa cheia, servidor fora). |
| `bounced` | O endereço não existe ou recusou. `detail` traz o código SMTP. Corrigir o e-mail do signatário. Se o endereço estiver na lista de supressão por engano, remova em SES → *Suppression list*. |
| `complained` | O destinatário marcou como spam. Não reenviar. |
| `rejected` | O SES recusou o conteúdo (vírus, por exemplo). |
| `sent` sem mudança por horas | Conferir a inscrição SNS e os logs do `/webhooks/ses`. |

## Lista de bloqueio

- Bounce permanente e reclamação de spam põem o endereço em `email_suppressions` do workspace (tela E-mail → Enviados → Bloqueados). O SES já tem a lista dele, da conta inteira, e descarta o envio para quem está lá; nesse caso o evento chega como bounce `OnAccountSuppressionList`.
- O cliente pode liberar só o que devolveu. Liberar tira também da lista da conta SES (`ses:DeleteSuppressedDestination`), então vale para todos os workspaces. Quem marcou como spam só sai por pedido da própria pessoa, pelo suporte.
- Abertura vem de um pixel e é aproximada: o Apple Mail baixa as imagens sozinho. Links clicados ficam no histórico sem a query string, porque os links de assinatura levam token.

## O que vigiar

A AWS revisa a conta com **bounce acima de 5%** ou **reclamação acima de 0,1%**, e pode pausar o envio com reclamação perto de 0,5%. Se pausar, **nenhum** e-mail da plataforma sai.

- Alarmes no CloudWatch: `Reputation.BounceRate` > 2% e `Reputation.ComplaintRate` > 0,05%.
- SES → *Reputation metrics* uma vez por semana no começo.
- Inscrição SNS deixou de ser *Confirmed* ou o tópico mudou: atualizar `SES_SNS_TOPIC_ARN` e refazer o passo 7.
