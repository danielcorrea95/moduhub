# Runbook: armazenamento de arquivos (S3)

Para quem opera a Moduhub. Cobre onde ficam os arquivos dos clientes, como montar o bucket na AWS, como migrar o que já está no servidor e como testar localmente.

## O que vai para o S3

Tudo fica num bucket só (`AWS_BUCKET`), separado por prefixo. Dentro de cada prefixo, a primeira pasta é o id do workspace.

| Prefixo | Disco Laravel | Conteúdo | Caminho no banco |
|---|---|---|---|
| `documents/` | `documents` | PDF original enviado, PDF de trabalho/final (selado com o e-CNPJ da plataforma) e PNG de cada assinatura | `documents.file_path`, `final_file_path`, `document_signers.signature_image_path` |
| `fiscal/` | `fiscal` | XML da DPS assinada, da NFS-e autorizada e do cancelamento; XML de cada documento distribuído pelo ADN; XML das notas recebidas | `invoices.dps_xml_path`, `nfse_xml_path`, `cancellation_xml_path`, `nfse_documents.xml_path`, `received_invoices.nfse_xml_path` |

O DANFSe não é guardado: ele é gerado na hora a partir do XML, para que uma nota cancelada saia com a marca d'água.

**O que não vai para o S3:** os `.pfx` dos emitentes (ficam cifrados no banco, ver `certificados.md`), o `.pfx` da plataforma (`SIGNATURES_PLATFORM_CERT_PATH`), logs, cache e sessão.

Os downloads continuam passando pela aplicação (stream do S3 para o navegador). A autorização (Policy, token do signatário, chave de API) continua no Laravel e o bucket nunca fica público.

O banco guarda caminhos relativos ao disco. Trocar `FILE_STORAGE_DRIVER` não muda nenhuma linha: o mesmo caminho passa a ser lido do bucket.

## Montar na AWS

Os arquivos são contratos assinados, documentos com CPF e XMLs fiscais. Por isso são três camadas: bucket fechado só com TLS, cifragem com chave KMS própria (credencial de S3 sozinha não abre nada) e um usuário IAM que só alcança os dois prefixos e não consegue apagar XML fiscal.

Rode com um usuário **administrador** da conta (`851725549821`), nunca com a credencial da aplicação. Tudo em `sa-east-1` (São Paulo), por causa da LGPD e da latência.

```bash
export AWS_REGION=sa-east-1
BUCKET=moduhub-prod-files
```

### 1. Chave KMS

```bash
KEY_ARN=$(aws kms create-key --description "Moduhub - arquivos dos clientes (S3)" --query KeyMetadata.Arn --output text)
aws kms create-alias --alias-name alias/moduhub-files --target-key-id "$KEY_ARN"
aws kms enable-key-rotation --key-id "$KEY_ARN"
echo "$KEY_ARN"   # vai inteiro no AWS_KMS_KEY_ID
```

Custa US$ 1/mês; com *Bucket Key* (passo 2) as chamadas ao KMS ficam desprezíveis. Todo `Decrypt` fica registrado no CloudTrail. **Nunca agende a exclusão dessa chave**: sem ela, todos os arquivos viram lixo cifrado. A rotação automática não quebra nada, as versões antigas da chave continuam abrindo os arquivos antigos.

### 2. Bucket

```bash
aws s3api create-bucket --bucket "$BUCKET" --create-bucket-configuration LocationConstraint=sa-east-1
aws s3api put-public-access-block --bucket "$BUCKET" \
  --public-access-block-configuration BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
aws s3api put-bucket-ownership-controls --bucket "$BUCKET" \
  --ownership-controls 'Rules=[{ObjectOwnership=BucketOwnerEnforced}]'
aws s3api put-bucket-versioning --bucket "$BUCKET" --versioning-configuration Status=Enabled
aws s3api put-bucket-encryption --bucket "$BUCKET" --server-side-encryption-configuration \
  "{\"Rules\":[{\"ApplyServerSideEncryptionByDefault\":{\"SSEAlgorithm\":\"aws:kms\",\"KMSMasterKeyID\":\"$KEY_ARN\"},\"BucketKeyEnabled\":true}]}"
```

- *Object Ownership* em **Bucket owner enforced**: ACL desligada. A aplicação não manda ACL (`retain_visibility => false`).
- *Versioning*: sobrescrita ou exclusão acidental vira versão antiga, recuperável.
- A aplicação também pede SSE-KMS com a mesma chave em cada upload; a cifragem padrão do bucket cobre o que entrar por fora (console, CLI).

### 3. Política do bucket: só HTTPS

```bash
cat > bucket-policy.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "DenyInsecureTransport",
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:*",
      "Resource": ["arn:aws:s3:::$BUCKET", "arn:aws:s3:::$BUCKET/*"],
      "Condition": { "Bool": { "aws:SecureTransport": "false" } }
    },
    {
      "Sid": "DenyTlsBelow12",
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:*",
      "Resource": ["arn:aws:s3:::$BUCKET", "arn:aws:s3:::$BUCKET/*"],
      "Condition": { "NumericLessThan": { "s3:TlsVersion": "1.2" } }
    }
  ]
}
EOF
aws s3api put-bucket-policy --bucket "$BUCKET" --policy file://bucket-policy.json
```

Não exigimos o cabeçalho de cifragem por política (`s3:x-amz-server-side-encryption-aws-kms-key-id`): as partes de um upload multipart chegam sem ele e seriam recusadas. A cifragem padrão do passo 2 já garante que nada fica em claro.

### 4. Ciclo de vida

```bash
cat > lifecycle.json <<'EOF'
{
  "Rules": [
    {
      "ID": "abort-incomplete-multipart",
      "Status": "Enabled",
      "Filter": {},
      "AbortIncompleteMultipartUpload": { "DaysAfterInitiation": 7 }
    },
    {
      "ID": "documents-old-versions",
      "Status": "Enabled",
      "Filter": { "Prefix": "documents/" },
      "NoncurrentVersionExpiration": { "NoncurrentDays": 30 },
      "Expiration": { "ExpiredObjectDeleteMarker": true }
    }
  ]
}
EOF
aws s3api put-bucket-lifecycle-configuration --bucket "$BUCKET" --lifecycle-configuration file://lifecycle.json
```

- `documents/`: PDF de rascunho apagado some de vez depois de 30 dias (a versão antiga é dado pessoal que não precisa ficar).
- `fiscal/`: **sem regra**. Nada expira, nem versão antiga (guarda legal de 5 anos).
- Não vale mover o fiscal para *Standard-IA* ou *Glacier*: o XML tem poucos KB, e o S3 não transita objeto abaixo de 128 KB (e cobraria 128 KB mínimos se transitasse).

### 5. Usuário IAM da aplicação

Se a aplicação rodar em EC2/ECS, use uma role com a mesma política e deixe `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` vazios. Fora da AWS, um usuário só para isso:

```bash
cat > app-files-policy.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListBucket",
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::$BUCKET"
    },
    {
      "Sid": "ReadWriteClientFiles",
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:PutObject", "s3:AbortMultipartUpload"],
      "Resource": ["arn:aws:s3:::$BUCKET/documents/*", "arn:aws:s3:::$BUCKET/fiscal/*"]
    },
    {
      "Sid": "DeleteDraftDocuments",
      "Effect": "Allow",
      "Action": "s3:DeleteObject",
      "Resource": "arn:aws:s3:::$BUCKET/documents/*"
    },
    {
      "Sid": "FiscalIsNeverDeleted",
      "Effect": "Deny",
      "Action": ["s3:DeleteObject", "s3:DeleteObjectVersion"],
      "Resource": "arn:aws:s3:::$BUCKET/fiscal/*"
    },
    {
      "Sid": "UseFilesKeyOnlyThroughS3",
      "Effect": "Allow",
      "Action": ["kms:GenerateDataKey", "kms:Decrypt"],
      "Resource": "$KEY_ARN",
      "Condition": { "StringEquals": { "kms:ViaService": "s3.sa-east-1.amazonaws.com" } }
    }
  ]
}
EOF
aws iam create-user --user-name moduhub-app-files
aws iam put-user-policy --user-name moduhub-app-files --policy-name moduhub-files --policy-document file://app-files-policy.json
aws iam create-access-key --user-name moduhub-app-files
```

- O `SecretAccessKey` só aparece nessa saída. Vai direto para o gerenciador de senhas; nunca para Git, chat ou e-mail.
- `s3:ListBucket` é necessário para o S3 responder 404 (e não 403) a arquivo inexistente; o `fileExists()` do Laravel depende disso.
- `s3:DeleteObject` só existe em `documents/` (PDFs de rascunho excluídos). O `Deny` explícito no fiscal vale mesmo que alguém anexe outra política mais larga a esse usuário.
- `kms:ViaService` impede usar a chave fora do S3, mesmo com a credencial vazada.
- Nenhuma permissão de versão (`GetObjectVersion`, `DeleteObjectVersion`), de política ou de ACL: a aplicação não precisa.

### 6. SES com credencial própria

`config/services.php` usa `AWS_ACCESS_KEY_ID` para o SES quando `SES_KEY` está vazio. O usuário do passo 5 só enxerga o S3, então **preencha `SES_KEY`/`SES_SECRET`** com o usuário que tem a política do SES ([email.md](email.md)). Sem isso o e-mail para com `AccessDenied`. Separar os dois também limita o estrago se uma das chaves vazar.

### 7. `.env` de produção

```dotenv
FILE_STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=...        # moduhub-app-files; vazio se usar role de instância
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=sa-east-1
AWS_BUCKET=moduhub-prod-files
AWS_KMS_KEY_ID=arn:aws:kms:sa-east-1:851725549821:key/...   # o ARN do passo 1

SES_KEY=...                  # usuário do SES, não o de arquivos
SES_SECRET=...
```

Depois: `php artisan config:cache`, `php artisan queue:restart` e reiniciar o PHP-FPM.

### 8. Conferir

No servidor:

```bash
php artisan tinker --execute 'Storage::disk("documents")->put("_ping", "ok"); echo Storage::disk("documents")->get("_ping"); Storage::disk("documents")->delete("_ping");'
```

Deve imprimir `ok`. Com o administrador, no seu computador:

```bash
aws s3api get-bucket-encryption --bucket "$BUCKET"       # aws:kms, BucketKeyEnabled true
aws s3api get-public-access-block --bucket "$BUCKET"     # quatro true
curl -sI "https://$BUCKET.s3.sa-east-1.amazonaws.com/documents/" | head -1   # 403
```

Depois do primeiro documento enviado pelo painel, `aws s3api head-object` num arquivo de `documents/` deve mostrar `ServerSideEncryption: aws:kms` e o `SSEKMSKeyId` da chave.

### Opcionais

- **Object Lock** (WORM) no fiscal: protege o XML até contra um administrador. Hoje dá para ligar num bucket já versionado (`put-object-lock-configuration`), mas a retenção padrão vale para o bucket inteiro, inclusive `documents/`. Para ligar só no fiscal, seria preciso a aplicação mandar a retenção em cada upload do disco `fiscal`, ou um bucket separado. Por enquanto o `Deny` do passo 5 cobre a aplicação.
- **CloudTrail com data events do S3** no bucket: registra quem leu cada arquivo (US$ 0,10 por 100 mil eventos). Útil para responder incidente ou pedido de titular (LGPD).

## Migrar um servidor que já tem arquivos locais

A ordem importa: os arquivos precisam estar no bucket **antes** de a aplicação passar a ler de lá.

1. Faça backup do banco e de `storage/app/private`.
2. Faça o deploy do código com a migration `move_nfse_xml_to_fiscal_disk` **ainda com `FILE_STORAGE_DRIVER=local`** e rode `php artisan migrate`. Os XMLs saem das colunas do banco e vão para `storage/app/private/fiscal`.
3. Configure as variáveis da AWS e `FILE_STORAGE_DRIVER=s3` no `.env`, **sem** rodar `config:cache` ainda e sem reiniciar o PHP-FPM ou as filas.
4. `php artisan storage:push-to-s3 --dry-run` para ver o que vai subir. Depois rode `php artisan storage:push-to-s3`. Ele pula arquivo que já está no bucket com o mesmo tamanho, então pode rodar de novo à vontade.
5. Coloque a aplicação em manutenção (`php artisan down`), rode `storage:push-to-s3` mais uma vez para pegar o que chegou nesse intervalo, depois `config:cache`, reinicie as filas (`queue:restart`) e o PHP-FPM, e `php artisan up`.
6. Confira: baixe um documento assinado e o XML e o DANFSe de uma nota pelo painel.
7. Só depois de alguns dias sem problema apague `storage/app/private/documents` e `storage/app/private/fiscal` do servidor. O comando não apaga nada.

Servidor novo, sem arquivos: configure `FILE_STORAGE_DRIVER=s3` antes do primeiro `migrate` e pronto.

## Testar com S3 no ambiente local

O Sail tem um serviço `s3` (SeaweedFS, compatível com a API do S3). A imagem comunitária do MinIO saiu do Docker Hub, por isso não é ele.

```bash
./vendor/bin/sail up -d s3
```

No `.env`:

```dotenv
FILE_STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=dev
AWS_SECRET_ACCESS_KEY=dev
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=moduhub-local
AWS_ENDPOINT=http://s3:8333
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Crie o bucket uma vez:

```bash
./vendor/bin/sail artisan tinker --execute '(new Aws\S3\S3Client(["region"=>"us-east-1","endpoint"=>"http://s3:8333","use_path_style_endpoint"=>true,"credentials"=>["key"=>"dev","secret"=>"dev"]]))->createBucket(["Bucket"=>"moduhub-local"]);'
./vendor/bin/sail artisan storage:push-to-s3
```

Os testes automatizados sempre usam `Storage::fake` nos dois discos (`tests/TestCase.php`) e nunca tocam no bucket.

## Incidentes

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| Upload de documento ou emissão de nota falha com `Unable to write file` | Credencial, permissão IAM, KMS ou bucket errado | Confira o log; teste com `php artisan tinker --execute 'Storage::disk("fiscal")->put("_ping", "ok");'` |
| A emissão respondeu erro, mas a nota existe na Sefin e aparece como "pendente" | O upload do XML da NFS-e falhou depois da autorização | O save foi desfeito e nada se perdeu: a Sefin guarda a nota. Com o S3 de volta, **Sincronizar** busca o XML de novo e grava. Se o upload falha antes do envio (XML da DPS), a transação desfaz tudo e o número da DPS não é consumido |
| `404` ao baixar um documento antigo | Arquivo não foi enviado ao bucket | `php artisan storage:push-to-s3` no servidor que ainda tem a cópia local |
| Arquivos órfãos no bucket | Transação desfeita depois do upload (falha de validação, Sefin fora do ar) | Inofensivo. Não apague nada em `fiscal/` sem cruzar com o banco |
