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

1. **Bucket** em `sa-east-1` (São Paulo), por causa da LGPD e da latência. Exemplo: `moduhub-prod-files`.
   - *Block Public Access*: tudo ligado.
   - *Object Ownership*: **Bucket owner enforced** (ACL desligada). A aplicação não manda ACL.
   - *Default encryption*: SSE-S3, ou SSE-KMS com uma chave própria (veja `AWS_KMS_KEY_ID`). A aplicação também pede a cifragem em cada upload.
   - *Versioning*: ligado. Protege contra exclusão ou sobrescrita acidental.
2. **Object Lock no prefixo `fiscal/`** (recomendado): os XMLs da NFS-e têm guarda legal de 5 anos. Só dá para ligar o Object Lock ao criar o bucket. Se quiser WORM, crie o bucket com Object Lock e use retenção padrão em modo *Governance* de 5 anos, ou use um bucket separado para o fiscal. O código nunca apaga XML fiscal.
3. **Lifecycle**: `fiscal/` vai para *S3 Standard-IA* depois de 90 dias e para *Glacier Instant Retrieval* depois de 1 ano. Versões antigas (*noncurrent*) expiram em 30 dias. Não ponha expiração de objetos correntes em `fiscal/`.
4. **Usuário IAM** só para a aplicação (ou role, se rodar em EC2/ECS), com esta política:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
      "Resource": "arn:aws:s3:::moduhub-prod-files/*"
    },
    {
      "Effect": "Allow",
      "Action": "s3:ListBucket",
      "Resource": "arn:aws:s3:::moduhub-prod-files"
    }
  ]
}
```

   Com SSE-KMS, inclua `kms:GenerateDataKey` e `kms:Decrypt` na chave. `s3:DeleteObject` só é usado para apagar PDFs de rascunho excluídos. Com Object Lock, o fiscal fica protegido mesmo com essa permissão.

5. **`.env` de produção**:

```dotenv
FILE_STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=...        # vazio se usar role de instância
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=sa-east-1
AWS_BUCKET=moduhub-prod-files
AWS_KMS_KEY_ID=              # ARN da chave, se usar SSE-KMS
```

   Depois: `php artisan config:cache`.

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
