# Runbook: certificados digitais

Para quem opera a Moduhub. Cobre onde ficam os certificados e as senhas, a rotina de vencimento e o que fazer quando algo vaza.

## Onde está cada segredo

| Segredo | Onde fica | Protegido por |
|---|---|---|
| e-CNPJ A1 da Moduhub (selo dos documentos, consulta de convênios no ADN), enviado em **Admin › Certificado** | `platform_certificates.pfx` e `password` (só a linha atual; as antigas têm os dois apagados) | AES-256 com `CERTIFICATES_ENCRYPTION_KEY` (`.env`) |
| Reserva do anterior, usada só quando o painel não tem nenhum | arquivo em `SIGNATURES_PLATFORM_CERT_PATH` | senha em `SIGNATURES_PLATFORM_CERT_PASSWORD` (`.env`) |
| e-CNPJ A1 de cada emitente de NFS-e, e a senha dele | `invoice_issuers.certificate_pfx` e `certificate_password` | AES-256 com `CERTIFICATES_ENCRYPTION_KEY` (`.env`) |
| Chave que cifra os certificados dos emitentes e o da plataforma | `CERTIFICATES_ENCRYPTION_KEY` | acesso ao servidor e ao gerenciador de segredos |

`CERTIFICATES_ENCRYPTION_KEY` é diferente da `APP_KEY` de propósito: vazar a `APP_KEY` não abre os certificados.

**Perder a `CERTIFICATES_ENCRYPTION_KEY` significa perder todos os certificados dos clientes.** Mantenha uma cópia no gerenciador de senhas da empresa, fora do servidor.

## Rotina

| Comando | Quando roda | O que faz |
|---|---|---|
| `invoices:check-certificates` | todo dia, 08:00 (Brasília) | Avisa donos, admins e o e-mail fiscal do emitente 30, 15, 7 e 1 dia antes do vencimento e no dia em que vence. Dispara o webhook `invoice.certificate_expiring`. |
| `signatures:check-platform-certificate` | todo dia, 08:00 (Brasília) | Alerta `SIGNATURES_PLATFORM_CERT_ALERT_EMAILS` quando o certificado da Moduhub está vencendo ou não abre. Grava `critical` no log. |

Sem `SIGNATURES_PLATFORM_CERT_ALERT_EMAILS` o alerta da plataforma só vai para o log. Configure em produção.

Para ver quem usou o certificado de um emitente, abra a tela **Notas fiscais › Emitente**. A trilha completa fica na tabela `invoice_certificate_events`:

```sql
SELECT created_at, type, purpose, actor_type, actor_label, ip_address, certificate_fingerprint, data
FROM invoice_certificate_events
WHERE issuer_id = '<id do emitente>'
ORDER BY created_at DESC;
```

## Renovar o certificado da Moduhub

Pelo painel, que é o caminho normal:

1. Compre e emita o e-CNPJ A1 novo na certificadora.
2. Entre como admin da plataforma em **Admin › Certificado** (`/admin/certificado`), envie o `.pfx`, a senha dele e a senha da sua conta.
3. Só entra e-CNPJ emitido dentro da ICP-Brasil (cadeia até uma raiz do ITI, ver abaixo) e com a raiz do CNPJ em `SIGNATURES_PLATFORM_CNPJ` (matriz ou filial). Sem essa variável a tela recusa o envio.
4. Confira o e-mail de aviso em `SIGNATURES_PLATFORM_CERT_ALERT_EMAILS` e rode `php artisan signatures:check-platform-certificate`, que deve dizer "sem pendências". Não precisa reiniciar workers: o certificado é lido do banco a cada documento.
5. Apague o `.pfx` do seu computador.

O certificado anterior sai de uso na hora, com arquivo e senha apagados do banco. O histórico (quem enviou, quem trocou, de qual IP) fica na mesma tela e na tabela `platform_certificates`. Remover pelo painel volta para o certificado do servidor, se houver.

Pelo servidor, só como reserva (o do painel vale sobre este):

1. Copie o `.pfx` para fora da pasta da aplicação, com dono root, grupo do PHP da Moduhub e permissão `0440`.
2. Atualize `SIGNATURES_PLATFORM_CERT_PATH` e `SIGNATURES_PLATFORM_CERT_PASSWORD`, rode `php artisan config:cache` e `php artisan queue:restart`.
3. Apague o `.pfx` antigo do servidor.

**Troca que ninguém reconhece** (e-mail de aviso inesperado): trate como invasão da conta de admin. Troque a senha da conta indicada no aviso, tire `is_platform_admin` dela se for preciso, envie de novo o certificado certo e veja no histórico o IP de quem trocou. A tela nunca mostra nem devolve o `.pfx` ou a senha, então a conta invadida não vaza a chave. O risco é o selo ficar fora do ar.

## Raízes da ICP-Brasil

O envio pelo painel confere a cadeia contra `resources/certificates/icp-brasil/roots.pem` (AC Raiz v5, v6, v7, v10, v11, v12 e v13, baixadas em 2026-10-21). As ACs intermediárias em `intermediates.pem` só servem para montar o caminho quando o `.pfx` vem sem a cadeia, e não são confiáveis por si só. Revogação (LCR) não é consultada.

Atualize quando o ITI publicar uma raiz nova ou quando o painel recusar um e-CNPJ legítimo por causa da cadeia. A v5 vence em 02/03/2029.

1. Baixe `https://acraiz.icpbrasil.gov.br/credenciadas/CertificadosAC-ICP-Brasil/ACcompactado.zip` e cada raiz pelo link da página "Repositório AC-Raiz" do ITI no gov.br.
2. Confira se as raízes do pacote são idênticas às baixadas pelo link da página (`openssl x509 -noout -fingerprint -sha256`).
3. Em `roots.pem` ficam só as raízes ainda válidas. Em `intermediates.pem` fica o resto do pacote, sem os vencidos. Cada bloco leva o comentário com o titular, a impressão digital SHA-256 e o vencimento.
4. O teste `test_bundled_icp_brasil_chain_closes_at_the_iti_roots` confere se toda intermediária fecha numa raiz. Rode-o antes do commit.

## Incidente: certificado de um cliente pode ter vazado

Sinais: o cliente vê no histórico um uso que não reconhece, ou avisa que o `.pfx` vazou do lado dele.

1. **Pare o uso.** Remova o certificado na tela do emitente, entrando no workspace como dono, ou peça ao cliente que remova. A remoção fica na trilha.
2. **Revogação.** Só o titular revoga, junto à certificadora que emitiu o certificado. Oriente o cliente a revogar e emitir outro.
3. **Investigue.** Consulte a trilha com a query acima. Compare `actor_label`, `ip_address` e os horários com o que o cliente reconhece. Notas emitidas no período aparecem em **Notas fiscais**.
4. **Notas indevidas.** Se houver, o cliente cancela ou substitui pela tela, dentro do prazo do município.
5. **Comunicação.** Se o vazamento partiu da Moduhub, trate como incidente de segurança com dados pessoais. Pela regulamentação da ANPD (Resolução CD/ANPD nº 15/2024), a comunicação à ANPD e aos titulares é feita em até 3 dias úteis. Confirme o prazo e o texto com o jurídico.

## Incidente: certificado da Moduhub vazou

1. Peça a revogação na certificadora imediatamente.
2. Emita um certificado novo e siga a seção [Renovar o certificado da Moduhub](#renovar-o-certificado-da-moduhub). Enquanto o novo não chega, remova o revogado em **Admin › Certificado** (e do servidor, se estiver lá).
3. Documentos selados com o certificado revogado continuam com o selo da data em que foram assinados. Novos documentos só saem com selo depois da troca. Enquanto ela não acontece, eles saem sem selo e o log registra `critical`.

## Incidente: `CERTIFICATES_ENCRYPTION_KEY` vazou ou o servidor foi invadido

**Só a chave vazou** (por exemplo, num print ou num commit), sem acesso ao banco:

1. Gere uma chave nova:
   ```bash
   php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
   ```
2. No `.env`, coloque a nova em `CERTIFICATES_ENCRYPTION_KEY` e a vazada em `CERTIFICATES_ENCRYPTION_PREVIOUS_KEYS`.
3. Rode `php artisan config:cache`, depois `php artisan invoices:reencrypt-certificates`.
4. Se o comando terminar com sucesso, tire a chave vazada de `CERTIFICATES_ENCRYPTION_PREVIOUS_KEYS` e rode `config:cache` de novo. Se algum certificado falhar, **não** tire a chave antiga: investigue antes.

**A chave e o banco vazaram juntos, ou o servidor foi invadido:** considere comprometidos todos os certificados guardados.

1. Siga os passos acima para trocar a chave. Isso protege daqui para frente, não o que já saiu.
2. Avise todos os clientes com certificado cadastrado para revogarem e emitirem um novo. O e-mail de cada emitente está em `invoice_issuers.email`. Os donos e admins de cada workspace estão em `tenant_user`, com `role` igual a `owner` ou `admin`.
3. Siga a etapa de comunicação do incidente de cliente (ANPD e titulares).
4. Revogue também o certificado da Moduhub, que estava no mesmo servidor.

## Troca de rotina da chave

Uma vez por ano, ou quando alguém com acesso ao `.env` de produção sai da equipe: siga os passos 1 a 4 de "Só a chave vazou".
