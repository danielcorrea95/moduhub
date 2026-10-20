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

## Testar

- **Local**: o Sail sobe o Mailpit. Use `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` e abra http://localhost:8025. Nada vai para `email_deliveries`.
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
