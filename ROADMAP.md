# Moduhub — Roadmap

Documento vivo de planejamento. Atualize à medida que as fatias se movem entre colunas.

---

## ✅ Entregue

### Core multi-tenant
- Tenancy row-level (`BelongsToTenant` + `TenantScope` + `CurrentTenant`)
- Workspaces (criação, troca, membros, convites por link)
- API Keys (`mdhb_live_` / `mdhb_test_`) com scopes
- Webhooks com HMAC-SHA256 + backoff exponencial
- Policies/Gates por recurso

### Módulo Signatures
- Upload de PDF, inspeção, hash SHA-256
- Signatários (paralelo ou sequencial), reordenação, auth por e-mail/SMS
- Composição de PDF com footer em toda página + página final "Relatório de Assinaturas"
- Assinatura por código via e-mail (TTL 15min, max 5 tentativas, bcrypt)
- Assinatura em canvas (visual, opcional)
- Selo ICP-Brasil da plataforma (modelo ZapSign): todo documento concluído recebe PKCS#7 com o e-CNPJ A1 da Moduhub e a cadeia até a Raiz v5 (`PlatformSealService`). Validado em 2026-10-16 com o cert real (DCS SOFTWARE ENGINEERING LTDA, AC SyngularID Múltipla, válido até 16/10/2027): assinatura e cadeia conferem no `openssl cms -verify` e adulteração quebra o selo
- Assinatura com certificado do próprio signatário removida em 2026-10-16 (TCPDF só grava um PKCS#7 por PDF e o selo da plataforma ocupa essa vaga)
- QR code de validação na página de assinaturas
- Selo "DOCUMENTO SELADO · ICP-BRASIL" no relatório de assinaturas e na página de verificação
- Página pública de validação `/v/{SHORT_ID}` com trilha de auditoria
- Webhooks: `document.viewed/signed/completed/rejected`
- Mailpit configurado para desenvolvimento

---

## 🧪 Validação pendente do selo

- [x] **validar.iti.gov.br (16/10/2026 18:04): "Assinatura aprovada", assinatura eletrônica qualificada (MP 2.200-2/01 e Lei 14.063/20), e-CNPJ DCS Software Engineering Ltda reconhecido**
- [ ] Ler o Relatório de Conformidade do ITI (alertas de política PAdES ou carimbo de tempo)
- [ ] Abrir um PDF selado no **Adobe Reader** e confirmar o painel de assinaturas verde (cadeia ICP-Brasil confiável). Já conferido via `openssl cms -verify`; falta o Adobe
- [ ] Produção: certificado fora da pasta do app (`SIGNATURES_PLATFORM_CERT_PATH`), `chmod 600`, senha em `SIGNATURES_PLATFORM_CERT_PASSWORD` no secret da hospedagem
- [ ] Alerta de vencimento do certificado da plataforma (vence em 16/10/2027) e de documentos concluídos sem selo (`Log::critical` hoje)

---

## 🔧 Antes de novos módulos — polir Signatures

Fatias para deixar o módulo pronto para produção antes de expandir:

### Ajustes no sistema atual
- [ ] Rate limiting nas rotas públicas (`/sign/{token}/*`, `/v/{short}`, `/sign/{token}/code`)
- [ ] Prevenção de enumeração/timing attacks no code check
- [x] Convite por e-mail funcional (TenantInvitationMail + Blade responsivo + dispatch no MemberService)
- [ ] Logo customizado por tenant (hoje só cor primária; adicionar upload)
- [ ] Reenvio de código com throttle próprio (hoje libera sempre)
- [x] Fluxo ZapSign-style: email 1 (link) ao enviar, email 2 (código) sob demanda, próximo signer notificado automaticamente em sequencial
- [ ] Expiração automática de documentos (job agendado para marcar `expired`)
- [ ] Cleanup de PDFs e canvas de signatários deletados
- [ ] Reindexar `document_signers.token` como unique (verificar se já é)
- [ ] Fix: validação de ordem sequencial em edição de signer (não só em add)

### Documentação
- [ ] **README do projeto** — stack, como rodar, como contribuir
- [ ] **Guia do usuário** — tutorial: criar workspace, convidar time, enviar primeiro documento, assinar
- [ ] **Guia do desenvolvedor** — arquitetura, convenções (já em CLAUDE.md), como criar um novo módulo
- [ ] **Documentação da API REST** — OpenAPI/Swagger ou Scribe, com exemplos de curl
- [ ] **Guia de integração webhook** — eventos disponíveis, formato do payload, verificação HMAC, retries
- [ ] **Guia do selo ICP-Brasil** — o que o selo da plataforma garante (integridade) e o que não garante (assinatura qualificada do signatário), como verificar no Adobe, carimbo de tempo (quando entrar)
- [ ] **Changelog** — manter CHANGELOG.md convencional

### API REST pública
- [ ] `/api/v1/signatures/documents` — CRUD completo via API key
- [ ] `/api/v1/signatures/documents/{id}/signers` — add/update/remove
- [ ] `/api/v1/signatures/documents/{id}/send`
- [ ] `/api/v1/signatures/documents/{id}/download` (original e signed)
- [ ] Autenticação via `mdhb_live_` / `mdhb_test_` com scope `signatures:*`
- [ ] Resources (`JsonResource`) para shape consistente

### Billing (requer estudo de provedor — Stripe/Pagar.me/Asaas)
- [ ] Planos (Free / Starter / Pro / Enterprise)
- [ ] Limites por plano (docs/mês, signatários/doc, módulos ativos)
- [ ] Checkout + webhook de pagamento
- [ ] Faturas, dunning, cancelamento

---

## 📚 Estudado e adiado

### Módulo de envio de SMS (capability do core)
Levantamento feito em 2026-10-15. **Decisão: adiar.** Com ~100 SMS/mês não compensa gateway próprio (Jasmin); fase zero será um provedor nacional via HTTP atrás de `SmsGatewayContract`. Desenho completo, fases, preços e contexto regulatório estão em `CLAUDE.md` → "Decisões registradas → SMS".

Impacto quando entrar: desbloqueia `auth_method='sms'` e `email_and_sms` em Signatures, e vira capability reusável para outros módulos (2FA de login, notificações).

---

## 🚀 Próximos módulos

### Módulo NFS-e Nacional
Previsto como segundo módulo da plataforma. Estratégia: "cada nota emitida = lucro".

- **Escopo**: integração direta com o padrão nacional NFS-e (não com cada prefeitura individualmente)
- **Base técnica**: API REST da Sefin Nacional (JSON por fora, XML da DPS assinado por dentro, gzip + base64), mTLS com certificado ICP-Brasil A1 em toda chamada, inclusive para abrir o Swagger
- **Primeiro município de validação**: Novo Hamburgo (RS, IBGE 4313409). Testes via API e painel a partir do CRM Auren.
- **Certificados**: o A1 do módulo Signatures é da plataforma; para NFS-e cada tenant sobe o próprio e-CNPJ A1 (um emitente por workspace nesta fase, tabela `invoice_issuers`).

#### Entregue em 2026-10-16: emitente + certificado
- [x] `App\Modules\Invoices` com `Issuer` (tenant-scoped), `IssuerService`, `IssuerRepository`, `IssuerPolicy` (view: membro; manage: owner/admin), `IssuerCertificateInspector`
- [x] Painel: `/invoices/issuer` com dados cadastrais, enquadramento (opSimpNac, regApTribSN, regEspTrib), ambiente (produção restrita/produção), série da DPS e upload/remoção do .pfx
- [x] API: `GET|PUT /api/v1/invoices/issuer`, `POST|DELETE /api/v1/invoices/issuer/certificate` (multipart ou `certificate_base64`), escopos `invoices:read|write`, documentada em `/docs/api`
- [x] Certificado validado na entrada (senha, validade, e-CNPJ da mesma raiz de CNPJ, não-AC, keyUsage assinatura + não repúdio, EKU clientAuth) e guardado com cast `encrypted`; a API nunca devolve o arquivo nem a senha
- [x] `ValidCnpj` aceita o CNPJ alfanumérico de 2026
- [x] Testes: `tests/Feature/Invoices/IssuerSettingsTest`, `tests/Feature/Api/InvoicesIssuerApiTest`, trait `Tests\Support\CreatesPkcs12`

#### Entregue em 2026-10-16: emissão, sincronização e cancelamento
- [x] `Invoice` (pending → issued | rejected; issued → cancelled), número da DPS reservado com lock no emitente; validação/schema não consomem número
- [x] DPS v1.01 montada à mão (`DpsBuilder`), assinatura XMLDSig própria (`XmlSigner`, RSA-SHA1 configurável) e validação contra os XSD oficiais em `resources/nfse/schemas` (pattern de `serie` corrigido, ver README da pasta)
- [x] `SefinClientContract`: `SefinHttpClient` (mTLS com o pfx do emitente, gzip+base64) e `FakeSefinClient` (testes; `INVOICES_SEFIN_DRIVER=fake`)
- [x] Sem resposta da Sefin → `pending`; "Sincronizar" consulta `GET /dps/{id}` e só reenvia a mesma DPS se não houver nota
- [x] Cancelamento (evento 101101) assinado; recusa da Sefin mantém a nota emitida
- [x] Painel (lista, nova nota, detalhe com rejeições, XML, cancelar) e API `/api/v1/invoices` com idempotência por `external_id`; webhooks `invoice.issued|rejected|cancelled`

#### Próximas fatias
- [x] **Primeiro envio real em produção restrita (2026-10-16)**: mTLS, `dpsXmlGZipB64`, gzip+base64, assinatura RSA-SHA1 e leiaute aceitos; a Sefin chegou à regra de negócio e devolveu `E0312` (código de tributação nacional `010101` não administrado pelo município de incidência). Emitente de teste: DCS Software Engineering, **São Paulo/SP (3550308)**, que é Conveniado Ativo e usa o Emissor Nacional desde 22/12/2025
- [x] Parâmetros municipais **não** ficam na Sefin (`/parametros_municipais` dá 404): ficam no ADN, `https://adn[.producaorestrita].nfse.gov.br/parametrizacao` (swagger em `/parametrizacao/swagger/v1/swagger.json`, mTLS). Rotas: `/{codMun}/convenio`, `/{codMun}/{codServico}/{competencia}/aliquota`, `/{codMun}/{codServico}/historicoaliquotas`, `/{codMun}/{codServico}/{competencia}/regimes_especiais`, `/{codMun}/{competencia}/retencoes`, benefícios. `codServico` tem 9 dígitos com pontos: cTribNac + cTribMun (`08.02.01.000`)
- [x] São Paulo (3550308): aderente ao Emissor Nacional nos dois ambientes; sem alíquota para `08.02.01.000` nem `01.01.01.000` em 16/10/2026 (produção e restrita). Na restrita, `01.01.01.000` teve alíquota de 17/03/2025 a 01/09/2025. Para comparação: NH tem `08.02.01.000` a 2% desde 11/05/2026, Porto Alegre a 5%
- [x] **Primeira NFS-e emitida em 16/10/2026** na restrita: DCS/SP, `01.01.01`, competência 15/08/2025, NFS-e nº 1, chave `35503082256310735000176000000000000126108607888201`. Resposta de sucesso (`chaveAcesso`, `nfseXmlGZipB64`) validada
- [x] Cancelamento dessa nota pelo painel aceito pela Sefin real (evento 101101, `pedidoRegistroEventoXmlGZipB64`). Ciclo emitir → XML → cancelar validado de ponta a ponta
- [ ] Descobrir com o contador o de-para do código municipal 05762 de SP (instrução/treinamento, item 8.02) para cTribNac + cTribMun
- [x] Validar código de serviço no ADN antes de enviar a DPS (2026-10-16): `ServiceCodeChecker` + `MunicipalParametersContract` (`AdnParametersClient` real, `FakeMunicipalParameters` nos testes). Não parametrizado → 422 em `service.national_tax_code` sem consumir número de DPS; ADN fora do ar → segue; local da prestação diferente do município do emitente → não checa. Formulário mostra "parametrizado · alíquota X%" ou o aviso ao digitar. Conferido no ADN real: SP `01.01.01.000` não parametrizado em 16/10/2026 e 5% em 15/08/2025, igual ao que a Sefin fez
- [ ] Ainda não exercitado contra a Sefin real: `GET /dps/{id}` (sincronização de nota pendente)
- [x] Grupo IBS/CBS na DPS (2026-10-17): só declaratório (`finNFSe` 0, `indFinal`, `cIndOp`, `indDest` 0, `gIBSCBS/CST` + `cClassTrib`); a Sefin calcula base e valores. Códigos padrão no emitente (`ibs_cbs_cst|class_code|operation_code`), sobrescrita por nota em `values.ibs_cbs`; cClassTrib tem de começar pelo CST. Sem códigos, a DPS sai sem o grupo. Validado no XSD v1.01, **ainda não enviado à Sefin real**
- [ ] Definir com o contador CST, cClassTrib e cIndOp da DCS (Simples ME/EPP) antes de 01/01/2027
- [x] DANFSe em PDF conforme NT 008 v1.02 (14/07/2026), cópia em `docs/nfse/nt-008-danfse-v1-02.pdf`: A4 retrato, página única, coordenadas em cm por campo, QR code `https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave={chave}` (mín. 1,52 cm), "NFS-e SEM VALIDADE JURÍDICA" em vermelho quando tpAmb=2, marca "CANCELADA"/"SUBSTITUÍDA", canhoto opcional. Entregue em 2026-10-16: `DanfseData` (XML → campos) + `DanfseRenderer` (TCPDF), painel "Ver DANFSe" e `GET /api/v1/invoices/{id}/pdf`. Canhoto entregue em 2026-10-19. Nome do município do tomador/intermediário resolvido em 2026-10-17 com a tabela do IBGE (`resources/nfse/municipios-ibge.json`, 5.571 municípios, API de localidades do IBGE), que também valida `city_code` e alimenta a busca de município no painel
- [x] Substituição de NFS-e (2026-10-17): DPS com `subst` (`chSubstda`, `cMotivo` 01–05/99, `xMotivo`); painel "Substituir" e `POST /api/v1/invoices/{id}/replace`; original vira `replaced` quando a substituta sai, webhook `invoice.replaced`, DANFSe "SUBSTITUÍDA". **Ainda não exercitada na Sefin real** (confirmar que a Sefin registra o e105102 sozinha)
- [x] Intermediário (`interm`, ISS retido por ele = `tpRetISSQN` 3), descontos condicionado/incondicionado, dedução por valor ou percentual (`vDR`/`pDR`, sem lista de documentos), tomador estrangeiro (NIF/cNaoNIF + `endExt`), exportação (`cPaisResult` + `comExt`) e tipo de imunidade (2026-10-17). Validados no XSD, **não enviados à Sefin real**
- [x] Dedução por documentos (2026-10-19): `vDedRed/documentos/docDedRed` com os seis tipos (chNFSe, chNFe, NFSeMun, NFNFS, nDocFisc, nDoc), `tpDedRed` 1–9/99 + `xDescOutDed`, valores dedutível/deduzido e fornecedor (`fornec`). XSD exige `nNFSeMun` com 15 dígitos, `nNFS` com 7 e `modNFS` com 15: completados com zeros. Validado no XSD, **não enviado à Sefin real**
- [x] Municípios conveniados (2026-10-19): o ADN não lista convênios; `invoices:sync-agreements` varre os 5.571 códigos IBGE em `GET /parametrizacao/{codMun}/convenio` com o certificado da plataforma (selo), uma sessão mTLS, ~0,1 s por município, agendado domingo 03:00. Tabela `nfse_municipal_agreements` por ambiente; painel "Municípios" e `GET /api/v1/invoices/municipalities[/{código}]`. Respostas reais: 200 com `aderenteAmbienteNacional`, `aderenteEmissorNacional`, `situacaoEmissaoPadraoContribuintesRFB` (significado não documentado), `aderenteMAN`, `permiteAproveitametoDeCreditos` (sic); 404 "ainda não está ativo" e 404 "não encontrado". Critério para emitir pela Sefin: convênio ativo + `aderenteEmissorNacional` = 1 (comentário de `cLocEmi` no XSD). NH em produção: ativo, emissor nacional = 0
- [x] Leiaute completo da DPS v1.01 (2026-10-19): obra (`cObra`/`cCIB`/endereço), evento (`atvEvento`), `infoCompl` (observações, pedido, ART), `vReceb`, tributos federais (`tribFed`: PIS/COFINS com CST e retenção, IRRF, CSLL, INSS; alíquota → valor calculado sobre o serviço), benefício municipal (`BM`), exigibilidade suspensa (`exigSusp`, processo com 30 dígitos), IBS/CBS completo (`tpOper`, `gRefNFSe`, `tpEnteGov`, `dest` com `indDest`=1, `imovel`, `gReeRepRes`). Tudo validado no XSD, **não enviado à Sefin real**
- [x] Padrões do emitente (2026-10-19): serviço padrão (código nacional + municipal + NBS + descrição) e tributos aproximados; a nota pode vir só com tomador e valor (pensado para o Auren)
- [x] E-mail ao tomador (2026-10-19): fila, DANFSe + XML anexados, aviso de cancelamento/substituição, reenvio manual (`POST /invoices/{id}/email`), `notify_taker` por nota e `email_taker` no emitente
- [x] Distribuição do ADN (2026-10-19): `invoices:sync-documents` (de hora em hora) consome `GET /contribuintes/DFe/{NSU}?lote=true` com o certificado de cada emitente; validado contra o ADN real (NSU exclusivo, 404 E2220 no fim, XML gzip+base64). Arquiva em `nfse_documents`; cria notas recebidas (tomador, intermediário, prestador de nota emitida pelo tomador); aplica eventos de terceiros nas nossas notas (cancelamento externo/por ofício/deferido, rejeição/confirmação do tomador, bloqueio, análise fiscal). Swagger do ADN contribuintes: também `GET /NFSe/{chave}/Eventos`
- [x] Eventos registrados pelo contribuinte (2026-10-19): `EventRequestBuilder` genérico (101101, 101103, 202201/202205, 203202/203206, 204203/204207), validados no XSD. Análise fiscal (`POST /invoices/{id}/cancellation-review`) e manifestação de nota recebida (`POST /invoices/received/{id}/manifest`). **Não exercitados na Sefin real**
- [x] Canhoto do DANFSe (NT 008 item 2.1.13, opcional por emitente) e cota do plano (`plan_module.included_quota`): conta DPS não rejeitadas no período; excedente marcado em `is_overage` quando o plano tem preço de excedente e a assinatura está ativa; no teste grátis ou sem preço de excedente, bloqueia (`errors.quota`). `GET /invoices/usage`

#### Antes de produção (situação em 2026-10-19: código completo, **ainda não vai para produção**)

O leiaute nacional está coberto, mas a validação real é mínima: na restrita só passaram a emissão simples, o cancelamento e a distribuição do ADN. Para considerar o módulo pronto, faltam os três primeiros itens; os demais não bloqueiam.

- [ ] **Testar na restrita, um caso por vez**, com o certificado da DCS: substituição (confirmar o e105102 automático), análise fiscal (101103), manifestação (precisa de uma nota emitida contra a DCS), tributos federais, IBS/CBS, dedução por documentos, tomador estrangeiro/exportação, obra e evento, intermediário, `GET /dps/{id}`. O XSD aceita, mas a Sefin tem regras de negócio próprias (como o E0312) que só aparecem enviando
- [ ] **Dados do contador** da DCS: CST, cClassTrib e cIndOp do IBS/CBS e o de-para do municipal 05762 (itens acima). Sem isso a emissão real da DCS em SP não sai
- [ ] **Primeira nota em produção acompanhada**: nada foi emitido ainda no ambiente com validade fiscal
- [ ] **Infra de produção**: `php artisan migrate` (11 migrations de 17 e 19/10), cron com `schedule:run` a cada minuto (distribuição do ADN de hora em hora, municípios aos domingos), `queue:work` para os e-mails, `INVOICES_SEFIN_DRIVER=http`, certificado da plataforma configurado (`signatures.seal.*`) para a varredura de convênios
- [ ] Cobrança do excedente (`is_overage`) entra quando o billing existir
- [ ] Confirmar o status de Novo Hamburgo no Emissor Nacional (planilha de 28/09 e o ADN ainda marcam emissor nacional = não)
- [ ] **Fora do escopo por ora**: os ~2.574 municípios só no ADN (sem Emissor Nacional) exigem emitir no sistema da prefeitura (Betha, IPM, Ginfes…), que o Moduhub não integra; hoje o sistema avisa e bloqueia. Exceção: ME/EPP do Simples emitem pela Sefin em qualquer município a partir de 01/11/2026. Integrar padrões municipais é projeto à parte, só se algum cliente precisar

#### Levantamento de 2026-10-15
- **Novo Hamburgo**: planilha oficial de adesões de 28/09/2026 marca o município como "Conveniado Ativo", ADN = Sim, **Emissor Nacional = Não** (linha sem atualização desde 26/12/2024). A Prefeitura anunciou em 10/06/2026 que passaria a usar o Emissor Nacional como sistema único em 01/09/2026, desligando o sistema próprio (IPM Atende.net) em 31/08/2026, com base na Resolução CGSN 189/2026. A CGSN 191/2026 (04/08/2026) revogou a 189 e moveu a obrigação do Simples Nacional para **01/11/2026**. Não há comunicado da prefeitura confirmando nova data. **Confirmar com o ISSQN de NH: (51) 3594-9952, issqn@novohamburgo.rs.gov.br.** Independentemente disso, ME/EPP do Simples Nacional em qualquer município emitem pela Sefin Nacional a partir de 01/11/2026.
- **Endpoints Sefin Nacional** (produção `https://sefin.nfse.gov.br/SefinNacional`, homologação `https://sefin.producaorestrita.nfse.gov.br/SefinNacional`): `POST /nfse` (DPS → NFS-e síncrono; substituição é uma DPS com `subst/chSubstda`), `GET /nfse/{chaveAcesso}`, `POST /nfse/{chaveAcesso}/eventos` (cancelamento), `GET /nfse/{chaveAcesso}/eventos`, `GET|HEAD /dps/{id}`. Parâmetros municipais em `GET /parametros_municipais/{codMun}/convenio|{codServico}|{cnpj}` (alíquotas, regimes, retenções, benefícios). ADN para consulta de notas recebidas: `GET /DFe/{NSU}`. Swaggers: `/docs/index` em cada base (403 sem certificado cliente).
- **DANFSe**: API nacional de PDF desativada em 03/08/2026 (NT 008/2026). O PDF é responsabilidade do emissor, no layout padronizado pela NT 008. Reaproveitar TCPDF.
- **Leiaute**: namespace `http://www.sped.fazenda.gov.br/nfse`, XSD v1.01 (09/02/2026) em produção; NT 009 v1.01 (11/09/2026) traz o próximo layout com grupos IBS/CBS (AnexoVI v1.04.01), já em produção restrita. `infDPS`: tpAmb, dhEmi, verAplic, serie, nDPS, dCompet, tpEmit, cLocEmi, subst?, prest, toma?, interm?, serv, valores, IBSCBS?. Campos IBS/CBS obrigatórios para regime regular desde 01/10/2026 (sem rejeição até 31/12/2026); Simples Nacional a partir de 01/01/2027.
- **Certificado**: e-CNPJ A1 (o mesmo do módulo Signatures) serve para mTLS e para a assinatura XMLDSig enveloped da DPS. Regras do Anexo I: X.509 v3, KeyUsage com assinatura digital e não-repúdio, extensão OtherName com CNPJ, não pode ser certificado de AC, cadeia e LCR válidas.
- **Dependências a adicionar**: `robrichards/xmlseclibs` (assinatura XML); mTLS via `Http::withOptions(['cert' => ..., 'ssl_key' => ...])` convertendo o .pfx em PEM com o `IcpCertificateService`.
- **Documentação oficial**: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica (Anexo I DPS/NFS-e, Anexo II eventos, Anexo B lista de serviços/NBS, XSD; manuais de API em "Documentação Atual"). Cópias extraídas em texto ficaram no scratchpad desta sessão, não no repo.
- **Features**:
  - Cadastro de empresa emitente (CNPJ, inscrição municipal, certificado A1)
  - Emissão de nota (prestador → tomador → serviço → valor)
  - Consulta de nota, cancelamento, substituição
  - Lotes assíncronos via `Jobs`
  - PDF da DANFSe
  - Webhook `invoice.issued`, `invoice.cancelled`, `invoice.rejected`
- **Reaproveitamento**: o `IcpCertificateService` do módulo Signatures é 100% reusável para assinar XMLs de NFS-e
- **Dependência**: antes de começar, Signatures precisa estar estável + Billing funcionando (porque NFS-e será cobrado por emissão)

### Outras ideias (ordem indefinida)
- Módulo Contratos (templates + variáveis + geração automática + Signatures)
- Módulo CPF/CNPJ (consulta Serpro / Receita)
- Módulo Boleto (geração + registro + conciliação)
- Módulo Procuração digital
- Marketplace de modelos de documento

---

## 🔭 Backlog técnico (sem fatia específica)

- Testes automatizados (feature + unit) — zero cobertura hoje
- CI/CD (GitHub Actions): lint PHP + ESLint + testes + build
- Observability: Sentry, logs estruturados, métricas de uso
- Backup automatizado de PDFs assinados (S3 cross-region)
- Reserva de slugs de subdomínio para `tenant.moduhub.com.br`
- Carimbo de tempo ICP-Brasil (ACT) via BRy/cert.sign
- Assinatura com certificado do próprio signatário junto com o selo da plataforma (requer biblioteca de incremental PDF signing; opção (c) de 2026-10-16)
- Embutir OCSP/CRL no selo (LTV) para o Adobe validar mesmo depois do vencimento do certificado
- Internacionalização (i18n) do painel — hoje é hard-coded pt-BR
