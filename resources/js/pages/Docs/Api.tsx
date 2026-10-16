import { CodeBlock, CodeLanguageProvider, CodeSample } from '@/components/docs/code-block';
import { C, Callout, Endpoint, ParamTable, Prose, Section, type Param } from '@/components/docs/doc-parts';
import DocsLayout, { type DocsNavGroup } from '@/layouts/docs-layout';
import { buildSamples } from '@/lib/api-samples';

interface Props {
    baseUrl: string;
    scopes: { key: string; label: string }[];
    events: { key: string; label: string }[];
    rateLimitPerMinute: number;
    [key: string]: unknown;
}

const NAV: DocsNavGroup[] = [
    {
        title: 'Primeiros passos',
        items: [
            { id: 'introducao', title: 'Introdução' },
            { id: 'autenticacao', title: 'Autenticação' },
            { id: 'fluxo', title: 'Fluxo típico' },
            { id: 'erros', title: 'Erros e limites' },
        ],
    },
    {
        title: 'Documentos',
        items: [
            { id: 'objeto-documento', title: 'O objeto documento' },
            { id: 'criar-documento', title: 'Criar documento' },
            { id: 'listar-documentos', title: 'Listar documentos' },
            { id: 'consultar-documento', title: 'Consultar documento' },
            { id: 'enviar-documento', title: 'Enviar para assinatura' },
            { id: 'baixar-pdf', title: 'Baixar PDF' },
            { id: 'excluir-documento', title: 'Excluir rascunho' },
        ],
    },
    {
        title: 'Signatários',
        items: [
            { id: 'objeto-signatario', title: 'O objeto signatário' },
            { id: 'adicionar-signatario', title: 'Adicionar signatário' },
            { id: 'remover-signatario', title: 'Remover signatário' },
            { id: 'reenviar-link', title: 'Reenviar link' },
        ],
    },
    {
        title: 'Notas fiscais',
        items: [
            { id: 'objeto-emitente', title: 'O objeto emitente' },
            { id: 'consultar-emitente', title: 'Consultar emitente' },
            { id: 'atualizar-emitente', title: 'Criar ou atualizar emitente' },
            { id: 'enviar-certificado', title: 'Enviar certificado' },
            { id: 'remover-certificado', title: 'Remover certificado' },
            { id: 'objeto-nota', title: 'O objeto nota' },
            { id: 'emitir-nota', title: 'Emitir nota' },
            { id: 'listar-notas', title: 'Listar notas' },
            { id: 'consultar-nota', title: 'Consultar nota' },
            { id: 'sincronizar-nota', title: 'Sincronizar nota pendente' },
            { id: 'cancelar-nota', title: 'Cancelar nota' },
            { id: 'baixar-xml', title: 'Baixar XML' },
            { id: 'baixar-danfse', title: 'Baixar DANFSe (PDF)' },
        ],
    },
    {
        title: 'Webhooks',
        items: [
            { id: 'webhooks', title: 'Como funcionam' },
            { id: 'eventos', title: 'Eventos' },
            { id: 'verificar-assinatura', title: 'Verificar a assinatura' },
            { id: 'retentativas', title: 'Entregas e retentativas' },
        ],
    },
];

const INVOICE_ID = '01k7x6b2c3d4e5f6g7h8j9k0m1';

const INVOICE_INPUT = {
    external_id: 'pedido-1001',
    competence_date: '2026-10-16',
    taker: {
        document: '11.222.333/0001-81',
        name: 'Cliente Exemplo S.A.',
        email: 'financeiro@cliente.com.br',
        address: {
            postal_code: '93510-000',
            street: 'Rua Júlio de Castilhos',
            number: '100',
            district: 'Centro',
            city_code: '4313409',
        },
    },
    service: {
        national_tax_code: '01.07.01',
        description: 'Suporte técnico mensal - outubro/2026',
    },
    values: {
        amount: '1500.00',
        iss_withheld: false,
    },
};

const INVOICE = {
    id: INVOICE_ID,
    external_id: 'pedido-1001',
    status: 'issued',
    environment: 'restricted',
    dps: {
        id: 'DPS431340921234567800019500001000000000000001',
        series: '1',
        number: 1,
        emitted_at: '2026-10-16T15:20:00-03:00',
    },
    competence_date: '2026-10-16',
    access_key: '43134092212345678000195000000000000126104829173650',
    nfse_number: '1',
    issued_at: '2026-10-16T15:21:03-03:00',
    taker: {
        document: '11222333000181',
        name: 'Cliente Exemplo S.A.',
        email: 'financeiro@cliente.com.br',
        phone: null,
        municipal_registration: null,
        address: { postal_code: '93510000', street: 'Rua Júlio de Castilhos', number: '100', complement: null, district: 'Centro', city_code: '4313409' },
    },
    service: { national_tax_code: '010701', municipal_tax_code: null, nbs_code: null, description: 'Suporte técnico mensal - outubro/2026', city_code: null },
    values: { amount: '1500.00', iss_rate: null, iss_withheld: false, iss_taxation: 1, approximate_taxes: null },
    rejection: null,
    last_error: null,
    last_attempt_at: '2026-10-16T15:21:03-03:00',
    cancellation: null,
    has_xml: true,
    created_at: '2026-10-16T15:21:02-03:00',
    updated_at: '2026-10-16T15:21:03-03:00',
};

const INVOICE_FIELDS: Param[] = [
    {
        name: 'status',
        type: 'string',
        description: (
            <>
                <C>issued</C> emitida, <C>rejected</C> rejeitada pela Sefin (motivos em <C>rejection</C>), <C>pending</C> sem resposta
                da Sefin e <C>cancelled</C> cancelada.
            </>
        ),
    },
    { name: 'external_id', type: 'string | null', description: 'O identificador do pedido no seu sistema, se você enviou.' },
    { name: 'dps', type: 'object', description: 'Id, série, número e data/hora da DPS que originou a nota. O número é reservado na emissão.' },
    { name: 'access_key, nfse_number', type: 'string | null', description: 'Chave de acesso (50 dígitos) e número da NFS-e, preenchidos quando a nota sai.' },
    { name: 'issued_at', type: 'string | null', description: 'Data e hora de processamento na Sefin Nacional.' },
    { name: 'taker, service, values', type: 'object', description: 'Os dados enviados na emissão, já normalizados (sem máscara).' },
    {
        name: 'rejection',
        type: 'array | null',
        description: (
            <>
                Lista de <C>{'{ code, message, complement }'}</C> devolvida pela Sefin quando rejeita a DPS.
            </>
        ),
    },
    { name: 'last_error', type: 'string | null', description: 'Por que a última tentativa ficou sem resposta, quando o status é pending.' },
    { name: 'cancellation', type: 'object | null', description: 'Motivo, justificativa e data do cancelamento.' },
    { name: 'has_xml', type: 'boolean', description: 'Se o XML da NFS-e já está disponível para download.' },
];

const DOC_ID = '01k7x3q9d2f8m4n6p0r2t4v6w8';
const SIGNER_ID = '01k7x3qa1c3e5g7j9k1m3p5r7t';

const ISSUER = {
    id: '01k7x5a1b2c3d4e5f6g7h8j9k0',
    legal_name: 'Agência Vela Comunicação Ltda',
    trade_name: 'Agência Vela',
    cnpj: '12345678000195',
    municipal_registration: '123456',
    city_code: '4313409',
    city_name: 'Novo Hamburgo',
    state: 'RS',
    simples_nacional: 3,
    simples_regime: 1,
    special_regime: 0,
    phone: '5135940000',
    email: 'fiscal@example.com',
    environment: 'restricted',
    dps_series: '1',
    next_dps_number: 1,
    ready_to_issue: true,
    certificate: {
        subject: 'AGENCIA VELA COMUNICACAO LTDA:12345678000195',
        cnpj: '12345678000195',
        issuer: 'AC SOLUTI Multipla v5',
        serial: '1A2B3C4D5E6F',
        fingerprint_sha256: '9f2c4b1e0d8a7f6e5c4b3a29180716f5e4d3c2b1a09f8e7d6c5b4a3928170615',
        valid_from: '2026-10-16T00:00:00-03:00',
        valid_until: '2027-10-16T23:59:59-03:00',
        uploaded_at: '2026-10-16T15:12:00-03:00',
        expired: false,
        expires_soon: false,
    },
    created_at: '2026-10-16T15:10:00-03:00',
    updated_at: '2026-10-16T15:12:00-03:00',
};

const ISSUER_FIELDS: Param[] = [
    { name: 'legal_name, trade_name', type: 'string', description: 'Razão social e nome fantasia do prestador.' },
    { name: 'cnpj', type: 'string', description: 'Sempre devolvido só com os 14 caracteres, sem máscara. Aceita o formato alfanumérico de 2026.' },
    { name: 'municipal_registration', type: 'string | null', description: 'Inscrição municipal, quando a prefeitura exige.' },
    { name: 'city_code', type: 'string', description: 'Código IBGE (7 dígitos) do município do estabelecimento emissor. Define a prefeitura da nota.' },
    {
        name: 'simples_nacional',
        type: 'integer',
        description: (
            <>
                <C>1</C> não optante, <C>2</C> MEI, <C>3</C> ME ou EPP optante.
            </>
        ),
    },
    {
        name: 'simples_regime',
        type: 'integer | null',
        description: (
            <>
                Só para ME/EPP optante. <C>1</C> tributos federais e ISSQN pelo Simples, <C>2</C> federais pelo Simples e ISSQN fora,{' '}
                <C>3</C> federais e ISSQN fora do Simples.
            </>
        ),
    },
    {
        name: 'special_regime',
        type: 'integer',
        description: (
            <>
                <C>0</C> nenhum, <C>1</C> ato cooperado, <C>2</C> estimativa, <C>3</C> microempresa municipal, <C>4</C> notário,{' '}
                <C>5</C> profissional autônomo, <C>6</C> sociedade de profissionais.
            </>
        ),
    },
    {
        name: 'environment',
        type: 'string',
        description: (
            <>
                <C>restricted</C>: produção restrita da Sefin Nacional, para testes, sem validade fiscal. <C>production</C>: notas
                reais.
            </>
        ),
    },
    { name: 'dps_series, next_dps_number', type: 'string, integer', description: 'Série e próximo número da DPS. O número avança a cada emissão.' },
    { name: 'ready_to_issue', type: 'boolean', description: 'Se há certificado válido para emitir.' },
    {
        name: 'certificate',
        type: 'object | null',
        description: (
            <>
                Metadados do e-CNPJ A1: titular, CNPJ, AC emissora, validade, <C>expired</C> e <C>expires_soon</C> (vence em até 30
                dias). O arquivo e a senha nunca são devolvidos.
            </>
        ),
    },
];

function exampleDocument(baseUrl: string) {
    const appUrl = baseUrl.replace(/\/api\/v1$/, '');
    return {
        id: DOC_ID,
        name: 'Contrato 2026-10-0019 - William Gonçalves',
        status: 'sent',
        sign_order: 'sequential',
        message: 'Segue o contrato para assinatura.',
        external_id: 'CRM-0019',
        metadata: { cliente_id: 4821 },
        page_count: 3,
        file_size: 184233,
        original_sha256: '9f2c4b1e0d8a7f6e5c4b3a29180716f5e4d3c2b1a09f8e7d6c5b4a3928170615',
        signed_sha256: null,
        signers_count: 2,
        signers: [
            {
                id: SIGNER_ID,
                order: 1,
                name: 'Lisiane Rodrigues Fonseca',
                role: 'Responsável financeiro',
                email: 'lisiane@example.com',
                cpf: null,
                cnpj: null,
                phone: '51999990000',
                auth_method: 'email',
                status: 'pending',
                sign_url: `${appUrl}/sign/k8Qe2vR7tY1uI0oP9aS8dF7gH6jK5lZ4xC3vB2nM1qW0eR9t`,
                viewed_at: null,
                signed_at: null,
                rejected_at: null,
                rejection_reason: null,
            },
            {
                id: '01k7x3qa1c3e5g7j9k1m3p5r7v',
                order: 2,
                name: 'Dionísio Felipe Hatzenberger',
                email: 'dionisio@example.com',
                cpf: null,
                cnpj: null,
                phone: null,
                auth_method: 'email',
                status: 'pending',
                sign_url: null,
                viewed_at: null,
                signed_at: null,
                rejected_at: null,
                rejection_reason: null,
            },
        ],
        expires_at: null,
        sent_at: '2026-10-15T17:25:04-03:00',
        completed_at: null,
        created_at: '2026-10-15T17:25:02-03:00',
        downloads: {
            original: `${baseUrl}/signatures/documents/${DOC_ID}/download?type=original`,
            signed: `${baseUrl}/signatures/documents/${DOC_ID}/download?type=signed`,
        },
    };
}

const json = (v: unknown) => JSON.stringify(v, null, 2);

const DOCUMENT_FIELDS: Param[] = [
    { name: 'id', type: 'string', description: 'Identificador do documento no Moduhub.' },
    { name: 'name', type: 'string', description: 'Nome exibido para quem assina e no painel.' },
    {
        name: 'status',
        type: 'string',
        description: (
            <>
                <C>draft</C> rascunho, <C>sent</C> enviado e ninguém assinou, <C>partially_signed</C> parte já assinou,{' '}
                <C>completed</C> todos assinaram, <C>rejected</C> alguém recusou.
            </>
        ),
    },
    {
        name: 'sign_order',
        type: 'string',
        description: (
            <>
                <C>parallel</C>: todos recebem o link ao mesmo tempo. <C>sequential</C>: um de cada vez, na ordem dos signatários.
            </>
        ),
    },
    { name: 'external_id', type: 'string | null', description: 'O identificador do documento no seu sistema. Volta em todos os webhooks.' },
    { name: 'metadata', type: 'object | null', description: 'Dados livres que você queira guardar junto do documento.' },
    { name: 'original_sha256', type: 'string', description: 'Hash do PDF enviado, para conferência de integridade.' },
    { name: 'signed_sha256', type: 'string | null', description: 'Hash do PDF final, preenchido quando o documento é concluído.' },
    { name: 'signers', type: 'array', description: 'Lista de signatários, na ordem de assinatura.' },
    { name: 'downloads', type: 'object', description: <>Endereços para baixar o PDF original e o PDF com assinaturas (<C>null</C> enquanto não existe).</> },
    { name: 'sent_at, completed_at, created_at', type: 'string | null', description: 'Datas em ISO 8601.' },
];

const SIGNER_INPUT: Param[] = [
    { name: 'name', type: 'string', required: true, description: 'Nome completo de quem vai assinar.' },
    { name: 'role', type: 'string', description: 'Papel de quem assina, até 60 caracteres (ex.: "Diretor", "Responsável financeiro"). Aparece no relatório de assinaturas como "Assinado como …".' },
    { name: 'email', type: 'string', required: true, description: 'E-mail que recebe o link e o código de confirmação. Não pode repetir no mesmo documento.' },
    {
        name: 'auth_method',
        type: 'string',
        description: (
            <>
                <C>email</C> (padrão): a pessoa confirma com um código enviado por e-mail. Quem assina não precisa de certificado
                digital: o PDF final é selado com o certificado ICP-Brasil da plataforma.
            </>
        ),
    },
    { name: 'cpf', type: 'string', description: 'Com ou sem máscara. Aparece no relatório de assinaturas.' },
    { name: 'cnpj', type: 'string', description: 'Com ou sem máscara; é guardado só com dígitos.' },
    { name: 'phone', type: 'string', description: 'Telefone de contato, usado para montar o link de WhatsApp no painel.' },
];

export default function ApiDocs({ baseUrl, scopes, events, rateLimitPerMinute }: Props) {
    const doc = exampleDocument(baseUrl);
    const S = (req: Parameters<typeof buildSamples>[1]) => <CodeSample samples={buildSamples(baseUrl, req)} />;

    return (
        <CodeLanguageProvider>
            <DocsLayout title="Documentação da API" nav={NAV}>
                <Section id="introducao" title="API do Moduhub">
                    <Prose>
                        <p>
                            Com a API você envia documentos para assinatura a partir do seu sistema (CRM, ERP, plataforma própria), acompanha
                            quem já assinou e baixa o PDF final, sem passar pelo painel.
                        </p>
                        <p>
                            Todas as chamadas usam HTTPS e JSON, partindo deste endereço base. Cada workspace tem suas próprias chaves, e uma
                            chave só enxerga os documentos do workspace em que foi criada.
                        </p>
                    </Prose>
                    <CodeBlock title="Endereço base" code={baseUrl} />
                </Section>

                <Section id="autenticacao" title="Autenticação">
                    <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] [&>*]:min-w-0">
                        <div className="space-y-5">
                            <Prose>
                                <p>
                                    Crie uma chave no painel, em <strong>Integrações › Chaves de API</strong>, e envie-a no cabeçalho{' '}
                                    <C>Authorization</C> de toda requisição. O valor completo da chave aparece uma única vez, na criação:
                                    guarde-o num cofre de segredos, nunca no código ou no navegador.
                                </p>
                                <p>
                                    Chaves começam com <C>mdhb_live_</C> ou <C>mdhb_test_</C>. Hoje as duas funcionam da mesma forma e
                                    acessam os mesmos dados; o prefixo serve para você separar a chave de produção da de desenvolvimento.
                                </p>
                            </Prose>
                            <div>
                                <h3 className="text-sm font-medium">Escopos</h3>
                                <p className="mt-1 max-w-prose text-sm text-muted-foreground">
                                    Cada chave recebe escopos que limitam o que ela pode fazer. Leitura cobre consultar, listar e baixar;
                                    escrita cobre criar, enviar, excluir e gerenciar signatários.
                                </p>
                                <ul className="mt-2 divide-y rounded-xl border">
                                    {scopes.map((s) => (
                                        <li key={s.key} className="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-4 py-2.5 text-sm">
                                            <code className="w-40 shrink-0 font-mono text-[0.8125rem]">{s.key}</code>
                                            <span className="text-foreground/85">{s.label}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                        <div className="space-y-4 xl:sticky xl:top-6 xl:self-start">
                            {S({ method: 'GET', path: '/me' })}
                            <CodeBlock
                                title="Resposta 200"
                                code={json({
                                    tenant: { id: '01k7w8...', name: 'Agência Vela', slug: 'agencia-vela', modules: ['signatures'] },
                                    api_key: { id: '01k7w9...', name: 'Integração CRM', environment: 'live', scopes: ['signatures:*'] },
                                })}
                            />
                        </div>
                    </div>
                </Section>

                <Section id="fluxo" title="Fluxo típico">
                    <ol className="max-w-prose space-y-4">
                        {[
                            ['Crie e envie numa chamada só', <>Mande o PDF, os signatários e <C>send: true</C> para <C>POST /signatures/documents</C>. Guarde o <C>id</C> devolvido junto do seu registro, ou use o seu próprio <C>external_id</C>.</>],
                            ['Os signatários recebem o link por e-mail', <>Na ordem sequencial, só o primeiro recebe agora; os demais recebem quando chega a vez. Se preferir enviar por outro canal, use o <C>sign_url</C> de cada signatário.</>],
                            ['Acompanhe por webhook', <>Cadastre um endpoint e receba <C>document.signed</C> a cada assinatura e <C>document.completed</C> quando todos assinarem. Evite consultar a API em intervalos para saber o status.</>],
                            ['Baixe o PDF assinado', <>Com o documento concluído, baixe em <C>downloads.signed</C>. O PDF traz as assinaturas, a trilha de auditoria e o código de verificação.</>],
                        ].map(([title, body], i) => (
                            <li key={i} className="flex gap-4">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-medium text-primary tabular-nums">
                                    {i + 1}
                                </span>
                                <div className="pt-0.5">
                                    <p className="font-medium">{title}</p>
                                    <p className="mt-1 text-[0.9375rem] leading-relaxed text-foreground/80">{body}</p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </Section>

                <Section id="erros" title="Erros e limites">
                    <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] [&>*]:min-w-0">
                        <div className="space-y-5">
                            <Prose>
                                <p>
                                    Respostas de erro trazem sempre um <C>message</C> legível. Erros de validação (422) também trazem{' '}
                                    <C>errors</C>, com as mensagens por campo.
                                </p>
                            </Prose>
                            <ParamTable
                                title="Códigos de resposta"
                                params={[
                                    { name: '200, 201, 204', type: 'sucesso', description: 'Consulta, criação e exclusão concluídas.' },
                                    { name: '401', type: 'autenticação', description: 'Chave ausente, malformada, revogada ou expirada.' },
                                    { name: '402', type: 'módulo', description: 'O workspace não tem o módulo da rota (assinaturas ou notas fiscais) ativo.' },
                                    { name: '403', type: 'permissão', description: <>A chave não tem o escopo exigido; o escopo necessário vem em <C>required_scope</C>.</> },
                                    { name: '404', type: 'não encontrado', description: 'O documento ou signatário não existe neste workspace.' },
                                    { name: '422', type: 'validação', description: 'Dados inválidos ou ação não permitida no estado atual (por exemplo, enviar um documento já enviado).' },
                                    { name: '429', type: 'limite', description: <>Mais de {rateLimitPerMinute} requisições por minuto com a mesma chave. Aguarde o tempo indicado em <C>Retry-After</C>.</> },
                                ]}
                            />
                        </div>
                        <div className="xl:sticky xl:top-6 xl:self-start">
                            <CodeBlock
                                title="Resposta 422"
                                code={json({
                                    message: 'Adicione pelo menos um signatário antes de enviar.',
                                    errors: { signers: ['Adicione pelo menos um signatário antes de enviar.'] },
                                })}
                            />
                        </div>
                    </div>
                </Section>

                <Section id="objeto-documento" title="O objeto documento">
                    <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] [&>*]:min-w-0">
                        <ParamTable title="Campos" params={DOCUMENT_FIELDS} />
                        <div className="xl:sticky xl:top-6 xl:self-start">
                            <CodeBlock title="Documento" code={json(doc)} />
                        </div>
                    </div>
                </Section>

                <Endpoint
                    id="criar-documento"
                    title="Criar documento"
                    method="POST"
                    path="/signatures/documents"
                    scope="signatures:write"
                    code={
                        <>
                            {S({
                                method: 'POST',
                                path: '/signatures/documents',
                                multipart: {
                                    file: 'contrato.pdf',
                                    fields: {
                                        name: 'Contrato 2026-10-0019 - William Gonçalves',
                                        external_id: 'CRM-0019',
                                        sign_order: 'sequential',
                                        send: true,
                                        signers: [
                                            { name: 'Lisiane Rodrigues Fonseca', role: 'Responsável financeiro', email: 'lisiane@example.com' },
                                            { name: 'Dionísio Felipe Hatzenberger', email: 'dionisio@example.com' },
                                        ],
                                    },
                                },
                            })}
                            <CodeBlock title="Resposta 201" code={json({ data: doc })} />
                        </>
                    }
                >
                    <Prose>
                        <p>
                            Cria o documento a partir de um PDF de até 25 MB. Envie o arquivo como upload (<C>multipart/form-data</C>, campo{' '}
                            <C>file</C>) ou em base64 num corpo JSON (campo <C>file_base64</C>).
                        </p>
                        <p>
                            Com <C>send: true</C> o documento já sai para assinatura. A operação é tudo ou nada: se um signatário for inválido
                            ou o envio for recusado, nada é criado e você recebe um 422.
                        </p>
                    </Prose>
                    <ParamTable
                        title="Corpo"
                        params={[
                            { name: 'file', type: 'arquivo PDF', required: true, description: <>Obrigatório se não houver <C>file_base64</C>.</> },
                            { name: 'file_base64', type: 'string', required: true, description: <>PDF em base64, com ou sem o prefixo <C>data:application/pdf;base64,</C>. Obrigatório se não houver <C>file</C>.</> },
                            { name: 'name', type: 'string', description: <>Nome do documento. Obrigatório com <C>file_base64</C>; no upload, usa o nome do arquivo se omitido.</> },
                            { name: 'message', type: 'string', description: 'Mensagem incluída no e-mail aos signatários (até 1.000 caracteres).' },
                            { name: 'sign_order', type: 'string', description: <><C>parallel</C> (padrão) ou <C>sequential</C>.</> },
                            { name: 'expires_at', type: 'string', description: 'Data limite para assinar, em ISO 8601, no futuro.' },
                            { name: 'external_id', type: 'string', description: 'Seu identificador para este documento.' },
                            { name: 'metadata', type: 'object', description: 'Dados livres, devolvidos nas consultas.' },
                            { name: 'signers', type: 'array', description: <>Até 50 signatários, com os campos de <a href="#adicionar-signatario">adicionar signatário</a>.</> },
                            { name: 'send', type: 'boolean', description: <>Envia logo após criar. Padrão <C>false</C> (fica como rascunho).</> },
                        ]}
                    />
                </Endpoint>

                <Endpoint
                    id="listar-documentos"
                    title="Listar documentos"
                    method="GET"
                    path="/signatures/documents"
                    scope="signatures:read"
                    code={
                        <>
                            {S({ method: 'GET', path: '/signatures/documents', query: { status: 'in_progress', per_page: '50' } })}
                            <CodeBlock
                                title="Resposta 200"
                                code={json({
                                    data: [{ ...doc, signers: '…' }],
                                    links: { first: '…?page=1', last: '…?page=4', prev: null, next: '…?page=2' },
                                    meta: { current_page: 1, last_page: 4, per_page: 50, total: 159 },
                                })}
                            />
                        </>
                    }
                >
                    <Prose>
                        <p>Lista os documentos do workspace, do mais recente para o mais antigo, com paginação.</p>
                    </Prose>
                    <ParamTable
                        title="Parâmetros de consulta"
                        params={[
                            { name: 'status', type: 'string', description: <><C>in_progress</C> (enviados e parcialmente assinados), <C>completed</C>, <C>rejected</C> ou <C>draft</C>.</> },
                            { name: 'search', type: 'string', description: 'Busca no nome do documento e no nome ou e-mail dos signatários.' },
                            { name: 'external_id', type: 'string', description: 'Encontra o documento pelo seu identificador.' },
                            { name: 'per_page', type: 'integer', description: 'De 1 a 100. Padrão 20.' },
                            { name: 'page', type: 'integer', description: 'Página a buscar. Padrão 1.' },
                        ]}
                    />
                </Endpoint>

                <Endpoint
                    id="consultar-documento"
                    title="Consultar documento"
                    method="GET"
                    path="/signatures/documents/{id}"
                    scope="signatures:read"
                    code={
                        <>
                            {S({ method: 'GET', path: `/signatures/documents/${DOC_ID}` })}
                            <CodeBlock title="Resposta 200" code={json({ data: doc })} />
                        </>
                    }
                >
                    <Prose>
                        <p>Devolve o documento com os signatários e o status de cada um.</p>
                    </Prose>
                </Endpoint>

                <Endpoint
                    id="enviar-documento"
                    title="Enviar para assinatura"
                    method="POST"
                    path="/signatures/documents/{id}/send"
                    scope="signatures:write"
                    code={
                        <>
                            {S({ method: 'POST', path: `/signatures/documents/${DOC_ID}/send` })}
                            <CodeBlock title="Resposta 200" code={json({ data: { ...doc, signers: '…' } })} />
                        </>
                    }
                >
                    <Prose>
                        <p>
                            Envia um rascunho para assinatura: os signatários recebem o e-mail com o link (na ordem sequencial, só o primeiro)
                            e o evento <C>document.sent</C> é disparado.
                        </p>
                        <p>Exige ao menos um signatário.</p>
                    </Prose>
                </Endpoint>

                <Endpoint
                    id="baixar-pdf"
                    title="Baixar PDF"
                    method="GET"
                    path="/signatures/documents/{id}/download"
                    scope="signatures:read"
                    code={S({ method: 'GET', path: `/signatures/documents/${DOC_ID}/download?type=signed`, saveAs: 'contrato-assinado.pdf' })}
                >
                    <Prose>
                        <p>Devolve o arquivo PDF (<C>application/pdf</C>), não JSON.</p>
                    </Prose>
                    <ParamTable
                        title="Parâmetros de consulta"
                        params={[
                            {
                                name: 'type',
                                type: 'string',
                                description: (
                                    <>
                                        <C>original</C> (padrão): o PDF como foi enviado. <C>signed</C>: o PDF com as assinaturas registradas até
                                        agora e a trilha de auditoria; disponível depois do envio e definitivo quando o documento é concluído.
                                    </>
                                ),
                            },
                        ]}
                    />
                </Endpoint>

                <Endpoint
                    id="excluir-documento"
                    title="Excluir rascunho"
                    method="DELETE"
                    path="/signatures/documents/{id}"
                    scope="signatures:write"
                    code={S({ method: 'DELETE', path: `/signatures/documents/${DOC_ID}` })}
                >
                    <Prose>
                        <p>
                            Exclui um documento que ainda é rascunho, junto com o arquivo. Documentos já enviados não podem ser excluídos
                            (resposta 422), porque fazem parte da trilha de auditoria.
                        </p>
                    </Prose>
                </Endpoint>

                <Section id="objeto-signatario" title="O objeto signatário">
                    <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] [&>*]:min-w-0">
                        <div className="space-y-5">
                            <ParamTable
                                title="Campos"
                                params={[
                                    { name: 'status', type: 'string', description: <><C>pending</C> não abriu, <C>viewed</C> abriu, <C>signed</C> assinou, <C>rejected</C> recusou.</> },
                                    {
                                        name: 'sign_url',
                                        type: 'string | null',
                                        description: (
                                            <>
                                                Link pessoal de assinatura. Só vem preenchido enquanto a pessoa pode assinar: documento enviado,
                                                ela ainda não assinou nem recusou e, na ordem sequencial, já é a vez dela.
                                            </>
                                        ),
                                    },
                                    { name: 'order', type: 'integer', description: 'Posição na ordem de assinatura, a partir de 1.' },
                                    { name: 'viewed_at, signed_at, rejected_at', type: 'string | null', description: 'Datas em ISO 8601.' },
                                    { name: 'rejection_reason', type: 'string | null', description: 'O motivo informado por quem recusou.' },
                                ]}
                            />
                            <Callout tone="warning">
                                <p>
                                    O <C>sign_url</C> dá acesso à assinatura daquela pessoa. Mande somente para ela e não o registre em logs
                                    públicos.
                                </p>
                            </Callout>
                        </div>
                        <div className="xl:sticky xl:top-6 xl:self-start">
                            <CodeBlock title="Signatário" code={json(doc.signers[0])} />
                        </div>
                    </div>
                </Section>

                <Endpoint
                    id="adicionar-signatario"
                    title="Adicionar signatário"
                    method="POST"
                    path="/signatures/documents/{id}/signers"
                    scope="signatures:write"
                    code={
                        <>
                            {S({
                                method: 'POST',
                                path: `/signatures/documents/${DOC_ID}/signers`,
                                json: { name: 'Regiane Santos de Oliveira', email: 'regiane@example.com', cpf: '123.456.789-09' },
                            })}
                            <CodeBlock title="Resposta 201" code={json({ data: { ...doc.signers[1], name: 'Regiane Santos de Oliveira', email: 'regiane@example.com', cpf: '123.456.789-09', order: 3 } })} />
                        </>
                    }
                >
                    <Prose>
                        <p>Adiciona um signatário ao final da ordem. Só é possível enquanto o documento é rascunho.</p>
                    </Prose>
                    <ParamTable title="Corpo" params={SIGNER_INPUT} />
                    <Callout>
                        <p>
                            As opções <C>sms</C> e <C>email_and_sms</C> são aceitas, mas ainda não enviam SMS: o código de confirmação
                            continua indo por e-mail.
                        </p>
                    </Callout>
                </Endpoint>

                <Endpoint
                    id="remover-signatario"
                    title="Remover signatário"
                    method="DELETE"
                    path="/signatures/documents/{id}/signers/{signer_id}"
                    scope="signatures:write"
                    code={S({ method: 'DELETE', path: `/signatures/documents/${DOC_ID}/signers/${SIGNER_ID}` })}
                >
                    <Prose>
                        <p>Remove um signatário de um rascunho. A ordem dos demais é refeita automaticamente.</p>
                    </Prose>
                </Endpoint>

                <Endpoint
                    id="reenviar-link"
                    title="Reenviar link"
                    method="POST"
                    path="/signatures/documents/{id}/signers/{signer_id}/remind"
                    scope="signatures:write"
                    code={
                        <>
                            {S({ method: 'POST', path: `/signatures/documents/${DOC_ID}/signers/${SIGNER_ID}/remind` })}
                            <CodeBlock title="Resposta 200" code={json({ message: 'Link reenviado para lisiane@example.com.' })} />
                        </>
                    }
                >
                    <Prose>
                        <p>
                            Envia de novo o e-mail com o link de assinatura, para quando a pessoa não encontrou o primeiro. Só funciona para
                            quem pode assinar agora (o mesmo critério do <C>sign_url</C>); nos demais casos a resposta é 422.
                        </p>
                    </Prose>
                </Endpoint>

                <Section id="objeto-emitente" title="O objeto emitente">
                    <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] [&>*]:min-w-0">
                        <div className="space-y-5">
                            <Prose>
                                <p>
                                    O emitente é a empresa que emite NFS-e pelo workspace: dados cadastrais, enquadramento tributário e o
                                    certificado digital A1 que assina as notas e autentica na Sefin Nacional. Cada workspace tem um emitente,
                                    e ele precisa estar completo, com certificado válido, antes da primeira emissão.
                                </p>
                            </Prose>
                            <ParamTable title="Campos" params={ISSUER_FIELDS} />
                        </div>
                        <div className="xl:sticky xl:top-6 xl:self-start">
                            <CodeBlock title="Emitente" code={json(ISSUER)} />
                        </div>
                    </div>
                </Section>

                <Endpoint
                    id="consultar-emitente"
                    title="Consultar emitente"
                    method="GET"
                    path="/invoices/issuer"
                    scope="invoices:read"
                    code={
                        <>
                            {S({ method: 'GET', path: '/invoices/issuer' })}
                            <CodeBlock title="Resposta 200" code={json({ data: ISSUER })} />
                        </>
                    }
                >
                    <Prose>
                        <p>Devolve o emitente do workspace. Enquanto nenhum emitente foi configurado, a resposta é 404.</p>
                    </Prose>
                </Endpoint>

                <Endpoint
                    id="atualizar-emitente"
                    title="Criar ou atualizar emitente"
                    method="PUT"
                    path="/invoices/issuer"
                    scope="invoices:write"
                    code={
                        <>
                            {S({
                                method: 'PUT',
                                path: '/invoices/issuer',
                                json: {
                                    legal_name: 'Agência Vela Comunicação Ltda',
                                    trade_name: 'Agência Vela',
                                    cnpj: '12.345.678/0001-95',
                                    municipal_registration: '123456',
                                    city_code: '4313409',
                                    city_name: 'Novo Hamburgo',
                                    state: 'RS',
                                    simples_nacional: 3,
                                    simples_regime: 1,
                                    special_regime: 0,
                                    email: 'fiscal@example.com',
                                    environment: 'restricted',
                                    dps_series: '1',
                                },
                            })}
                            <CodeBlock title="Resposta 201 (criado) ou 200 (atualizado)" code={json({ data: { ...ISSUER, certificate: null, ready_to_issue: false } })} />
                        </>
                    }
                >
                    <Prose>
                        <p>
                            Envie sempre o cadastro completo: a chamada substitui os dados do emitente. Na primeira vez cria o emitente e
                            responde 201; depois atualiza e responde 200. O certificado não é afetado.
                        </p>
                    </Prose>
                    <ParamTable
                        title="Corpo"
                        params={[
                            { name: 'legal_name', type: 'string', required: true, description: 'Razão social.' },
                            { name: 'trade_name', type: 'string', description: 'Nome fantasia.' },
                            { name: 'cnpj', type: 'string', required: true, description: 'Com ou sem máscara. Os dígitos verificadores são conferidos.' },
                            { name: 'municipal_registration', type: 'string', description: 'Inscrição municipal, até 15 caracteres.' },
                            { name: 'city_code', type: 'string', required: true, description: 'Código IBGE do município emissor, 7 dígitos.' },
                            { name: 'city_name, state', type: 'string', description: 'Nome do município e UF, só para exibição.' },
                            { name: 'simples_nacional', type: 'integer', required: true, description: <><C>1</C>, <C>2</C> ou <C>3</C>, como no objeto emitente.</> },
                            { name: 'simples_regime', type: 'integer', description: <>Obrigatório quando <C>simples_nacional</C> é <C>3</C>; ignorado nos demais casos.</> },
                            { name: 'special_regime', type: 'integer', required: true, description: <>De <C>0</C> a <C>6</C>.</> },
                            { name: 'phone, email', type: 'string', description: 'Contato fiscal da empresa.' },
                            { name: 'environment', type: 'string', required: true, description: <><C>restricted</C> ou <C>production</C>.</> },
                            { name: 'dps_series', type: 'string', required: true, description: 'Série da DPS, de 1 a 5 dígitos.' },
                        ]}
                    />
                </Endpoint>

                <Endpoint
                    id="enviar-certificado"
                    title="Enviar certificado"
                    method="POST"
                    path="/invoices/issuer/certificate"
                    scope="invoices:write"
                    code={
                        <>
                            {S({
                                method: 'POST',
                                path: '/invoices/issuer/certificate',
                                multipart: {
                                    file: 'certificado.pfx',
                                    field: 'certificate',
                                    contentType: 'application/x-pkcs12',
                                    fields: { password: 'senha-do-certificado' },
                                },
                            })}
                            <CodeBlock title="Resposta 200" code={json({ data: ISSUER })} />
                        </>
                    }
                >
                    <Prose>
                        <p>
                            Envia o e-CNPJ A1 do emitente como upload (<C>multipart/form-data</C>, campo <C>certificate</C>) ou em base64 num
                            corpo JSON (campo <C>certificate_base64</C>), sempre com a senha. Substitui o certificado anterior, se houver.
                        </p>
                        <p>
                            O certificado é aberto e conferido antes de ser guardado: a senha precisa estar certa, ele não pode estar vencido,
                            precisa ser de titular final com uso para assinatura digital e autenticação de cliente, e o CNPJ tem de ser da
                            mesma empresa do emitente, matriz ou filial. Qualquer falha devolve 422 com a explicação em <C>errors.certificate</C>{' '}
                            ou <C>errors.password</C>.
                        </p>
                    </Prose>
                    <ParamTable
                        title="Corpo"
                        params={[
                            { name: 'certificate', type: 'arquivo .pfx', required: true, description: <>Obrigatório se não houver <C>certificate_base64</C>. Até 64 KB.</> },
                            { name: 'certificate_base64', type: 'string', required: true, description: <>O .pfx em base64. Obrigatório se não houver <C>certificate</C>.</> },
                            { name: 'password', type: 'string', required: true, description: 'Senha do certificado. Fica cifrada junto do arquivo.' },
                        ]}
                    />
                    <Callout tone="warning">
                        <p>
                            Envie o certificado só por HTTPS e a partir do servidor da sua aplicação, nunca pelo navegador do usuário final.
                        </p>
                    </Callout>
                </Endpoint>

                <Endpoint
                    id="remover-certificado"
                    title="Remover certificado"
                    method="DELETE"
                    path="/invoices/issuer/certificate"
                    scope="invoices:write"
                    code={S({ method: 'DELETE', path: '/invoices/issuer/certificate' })}
                >
                    <Prose>
                        <p>Apaga o certificado e a senha guardados. O emitente continua cadastrado, mas sem poder emitir até receber outro.</p>
                    </Prose>
                </Endpoint>

                <Section id="objeto-nota" title="O objeto nota">
                    <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] [&>*]:min-w-0">
                        <div className="space-y-5">
                            <Prose>
                                <p>
                                    Uma NFS-e do padrão nacional. O Moduhub monta a DPS a partir dos dados enviados, assina com o certificado do
                                    emitente, valida contra o schema oficial e envia para a Sefin Nacional, que devolve a nota na mesma chamada.
                                </p>
                                <p>
                                    O ambiente segue o emitente: em <C>restricted</C> as notas são de teste, sem validade fiscal.
                                </p>
                            </Prose>
                            <ParamTable title="Campos" params={INVOICE_FIELDS} />
                        </div>
                        <div className="xl:sticky xl:top-6 xl:self-start">
                            <CodeBlock title="Nota" code={json(INVOICE)} />
                        </div>
                    </div>
                </Section>

                <Endpoint
                    id="emitir-nota"
                    title="Emitir nota"
                    method="POST"
                    path="/invoices"
                    scope="invoices:write"
                    code={
                        <>
                            {S({ method: 'POST', path: '/invoices', json: INVOICE_INPUT })}
                            <CodeBlock title="Resposta 201" code={json({ data: INVOICE })} />
                            <CodeBlock
                                title="Resposta 422 (rejeitada)"
                                code={json({
                                    message: 'A Sefin Nacional rejeitou a DPS.',
                                    data: {
                                        ...INVOICE,
                                        status: 'rejected',
                                        access_key: null,
                                        nfse_number: null,
                                        issued_at: null,
                                        has_xml: false,
                                        rejection: [{ code: 'E0312', message: 'Código de tributação nacional não administrado pelo município.', complement: null }],
                                    },
                                })}
                            />
                        </>
                    }
                >
                    <Prose>
                        <p>A emissão é síncrona. O status HTTP diz o que aconteceu:</p>
                        <ul>
                            <li>
                                <C>201</C>: nota emitida.
                            </li>
                            <li>
                                <C>422</C> com <C>data</C>: a Sefin rejeitou a DPS. Os motivos estão em <C>data.rejection</C>. Corrija e envie de
                                novo; uma nota rejeitada não bloqueia o mesmo <C>external_id</C>.
                            </li>
                            <li>
                                <C>422</C> com <C>errors</C>: os dados não passaram na validação (campos, emitente sem certificado, leiaute). Nada
                                foi enviado e nenhum número de DPS foi consumido.
                            </li>
                            <li>
                                <C>202</C>: a Sefin não respondeu. A nota fica <C>pending</C>; chame <a href="#sincronizar-nota">sincronizar</a>{' '}
                                em alguns minutos.
                            </li>
                            <li>
                                <C>200</C>: já existe nota emitida ou pendente com este <C>external_id</C>. Ela é devolvida e nada é reenviado.
                            </li>
                        </ul>
                    </Prose>
                    <ParamTable
                        title="Corpo"
                        params={[
                            { name: 'external_id', type: 'string', description: 'Identificador do pedido no seu sistema. Use sempre: é a proteção contra nota em duplicidade quando a sua chamada é repetida.' },
                            { name: 'competence_date', type: 'string', description: <>Data de competência, <C>AAAA-MM-DD</C>, até hoje. Padrão: hoje.</> },
                            { name: 'taker.document', type: 'string', required: true, description: 'CPF ou CNPJ do tomador, com ou sem máscara.' },
                            { name: 'taker.name', type: 'string', required: true, description: 'Nome ou razão social, até 300 caracteres.' },
                            { name: 'taker.email, taker.phone', type: 'string', description: 'Contato do tomador.' },
                            { name: 'taker.municipal_registration', type: 'string', description: 'Inscrição municipal do tomador.' },
                            {
                                name: 'taker.address',
                                type: 'object',
                                description: (
                                    <>
                                        <C>postal_code</C>, <C>street</C>, <C>number</C>, <C>complement</C>, <C>district</C> e <C>city_code</C>{' '}
                                        (IBGE). Opcional, mas se vier precisa estar completo.
                                    </>
                                ),
                            },
                            { name: 'service.national_tax_code', type: 'string', required: true, description: <>Código de tributação nacional, 6 dígitos (ex.: <C>01.07.01</C>).</> },
                            { name: 'service.municipal_tax_code', type: 'string', description: 'Código de tributação municipal, 3 dígitos, quando o município usa.' },
                            { name: 'service.nbs_code', type: 'string', description: 'Código NBS, 9 dígitos.' },
                            { name: 'service.description', type: 'string', required: true, description: 'Discriminação do serviço, até 2.000 caracteres. Quebras de linha viram espaço.' },
                            { name: 'service.city_code', type: 'string', description: 'Código IBGE do local da prestação. Padrão: município do emitente.' },
                            { name: 'values.amount', type: 'string | number', required: true, description: 'Valor do serviço em reais.' },
                            {
                                name: 'values.iss_rate',
                                type: 'number',
                                description: 'Alíquota do ISS em %. Em município conveniado, informe só quando a regra exigir (ex.: Simples com retenção); fora disso a Sefin usa a parametrizada e rejeita alíquota informada.',
                            },
                            { name: 'values.iss_withheld', type: 'boolean', description: 'ISS retido pelo tomador. Padrão: false.' },
                            {
                                name: 'values.iss_taxation',
                                type: 'integer',
                                description: (
                                    <>
                                        <C>1</C> tributável (padrão), <C>2</C> imunidade, <C>3</C> exportação, <C>4</C> não incidência.
                                    </>
                                ),
                            },
                            {
                                name: 'values.approximate_taxes',
                                type: 'object',
                                description: (
                                    <>
                                        Percentuais aproximados <C>federal</C>, <C>state</C> e <C>municipal</C> (Lei 12.741). Ignorado para MEI.
                                    </>
                                ),
                            },
                        ]}
                    />
                    <Callout tone="warning">
                        <p>
                            A rejeição mais comum é a <C>E0312</C>: o município de incidência não tem o código de serviço parametrizado no
                            Sistema Nacional. O que a prefeitura cadastra é a combinação do código de tributação nacional com o desdobramento
                            municipal (<C>service.municipal_tax_code</C>). O código municipal antigo da prefeitura (por exemplo, os 5 dígitos da
                            NFS-e Paulistana) não serve aqui. Confirme com o contador do emitente o código nacional e o desdobramento usados nas
                            notas dele.
                        </p>
                    </Callout>
                </Endpoint>

                <Endpoint
                    id="listar-notas"
                    title="Listar notas"
                    method="GET"
                    path="/invoices"
                    scope="invoices:read"
                    code={
                        <>
                            {S({ method: 'GET', path: '/invoices?status=issued&per_page=20' })}
                            <CodeBlock title="Resposta 200" code={json({ data: [INVOICE], links: { next: null }, meta: { current_page: 1, per_page: 20, total: 1 } })} />
                        </>
                    }
                >
                    <Prose>
                        <p>Lista as notas do workspace, das mais recentes para as mais antigas.</p>
                    </Prose>
                    <ParamTable
                        title="Query"
                        params={[
                            { name: 'status', type: 'string', description: <><C>pending</C>, <C>issued</C>, <C>rejected</C> ou <C>cancelled</C>.</> },
                            { name: 'external_id', type: 'string', description: 'Só as notas deste pedido.' },
                            { name: 'search', type: 'string', description: 'Nome ou CPF/CNPJ do tomador, número ou chave da nota.' },
                            { name: 'per_page', type: 'integer', description: 'De 1 a 100. Padrão: 20.' },
                        ]}
                    />
                </Endpoint>

                <Endpoint
                    id="consultar-nota"
                    title="Consultar nota"
                    method="GET"
                    path="/invoices/{id}"
                    scope="invoices:read"
                    code={
                        <>
                            {S({ method: 'GET', path: `/invoices/${INVOICE_ID}` })}
                            <CodeBlock title="Resposta 200" code={json({ data: INVOICE })} />
                        </>
                    }
                >
                    <Prose>
                        <p>Devolve uma nota pelo id do Moduhub.</p>
                    </Prose>
                </Endpoint>

                <Endpoint
                    id="sincronizar-nota"
                    title="Sincronizar nota pendente"
                    method="POST"
                    path="/invoices/{id}/sync"
                    scope="invoices:write"
                    code={S({ method: 'POST', path: `/invoices/${INVOICE_ID}/sync` })}
                >
                    <Prose>
                        <p>
                            Resolve uma nota <C>pending</C>. O Moduhub pergunta à Sefin se a DPS gerou nota. Se gerou, baixa a nota e marca como
                            emitida. Se não, reenvia a mesma DPS, com o mesmo número. Nunca há duas notas para a mesma DPS.
                        </p>
                        <p>
                            Responde com a nota atualizada. Se a Sefin continuar fora do ar, a nota segue <C>pending</C> e <C>last_error</C>{' '}
                            explica. Para notas que não estão pendentes, responde 422.
                        </p>
                    </Prose>
                </Endpoint>

                <Endpoint
                    id="cancelar-nota"
                    title="Cancelar nota"
                    method="POST"
                    path="/invoices/{id}/cancel"
                    scope="invoices:write"
                    code={
                        <>
                            {S({ method: 'POST', path: `/invoices/${INVOICE_ID}/cancel`, json: { reason_code: 1, reason: 'Valor do serviço informado errado' } })}
                            <CodeBlock
                                title="Resposta 200"
                                code={json({
                                    data: {
                                        ...INVOICE,
                                        status: 'cancelled',
                                        cancellation: { reason_code: 1, reason: 'Valor do serviço informado errado', cancelled_at: '2026-10-16T16:02:11-03:00' },
                                    },
                                })}
                            />
                        </>
                    }
                >
                    <Prose>
                        <p>
                            Registra o evento de cancelamento na Sefin Nacional, assinado com o certificado do emitente. Só notas{' '}
                            <C>issued</C> podem ser canceladas. Se a Sefin recusar (prazo vencido, nota já cancelada), a resposta é 422 com o
                            motivo em <C>errors.cancel</C> e a nota continua emitida.
                        </p>
                    </Prose>
                    <ParamTable
                        title="Corpo"
                        params={[
                            {
                                name: 'reason_code',
                                type: 'integer',
                                required: true,
                                description: (
                                    <>
                                        <C>1</C> erro na emissão, <C>2</C> serviço não prestado, <C>9</C> outros.
                                    </>
                                ),
                            },
                            { name: 'reason', type: 'string', required: true, description: 'Justificativa, de 15 a 255 caracteres.' },
                        ]}
                    />
                </Endpoint>

                <Endpoint
                    id="baixar-xml"
                    title="Baixar XML"
                    method="GET"
                    path="/invoices/{id}/xml"
                    scope="invoices:read"
                    code={S({ method: 'GET', path: `/invoices/${INVOICE_ID}/xml` })}
                >
                    <Prose>
                        <p>
                            Devolve o XML da NFS-e como a Sefin gerou (<C>application/xml</C>), com a DPS assinada dentro. É o documento fiscal que
                            vale; guarde-o. Responde 404 enquanto a nota não foi gerada.
                        </p>
                    </Prose>
                </Endpoint>

                <Endpoint
                    id="baixar-danfse"
                    title="Baixar DANFSe (PDF)"
                    method="GET"
                    path="/invoices/{id}/pdf"
                    scope="invoices:read"
                    code={S({ method: 'GET', path: `/invoices/${INVOICE_ID}/pdf` })}
                >
                    <Prose>
                        <p>
                            Devolve o DANFSe, a representação em PDF da NFS-e, no layout padrão da Nota Técnica 008/2026: página A4 única, QR
                            code para a consulta pública no portal nacional e os dados lidos do XML da nota.
                        </p>
                        <p>
                            O PDF é gerado na hora a partir do XML. Notas canceladas saem com a marca d&apos;água <C>CANCELADA</C>, e notas de
                            produção restrita com o aviso <C>NFS-e SEM VALIDADE JURÍDICA</C>. Responde 404 enquanto a nota não foi gerada.
                        </p>
                    </Prose>
                </Endpoint>

                <Section id="webhooks" title="Webhooks">
                    <Prose>
                        <p>
                            Webhooks avisam o seu sistema quando algo acontece com um documento, sem você precisar consultar a API. Cadastre a
                            URL (HTTPS) e os eventos no painel, em <strong>Integrações › Webhooks</strong>. Na criação você recebe um segredo{' '}
                            <C>whsec_…</C> para verificar que a chamada veio do Moduhub.
                        </p>
                        <p>
                            Cada evento chega como um <C>POST</C> JSON. Todo payload traz o seu <C>external_id</C>, junto com{' '}
                            <C>document_id</C> nos eventos de documento ou <C>invoice_id</C>, <C>status</C> e <C>access_key</C> nos de nota fiscal.
                        </p>
                    </Prose>
                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2 [&>*]:min-w-0">
                        <CodeBlock
                            title="Cabeçalhos"
                            code={[
                                'Content-Type: application/json',
                                'X-Moduhub-Event: document.signed',
                                'X-Moduhub-Delivery: 01k7x4b2c6e8g0j2k4m6p8r0t2',
                                'X-Moduhub-Timestamp: 1760559904',
                                'X-Moduhub-Signature: sha256=5d41402abc4b2a76b9719d911017c592…',
                            ].join('\n')}
                        />
                        <CodeBlock
                            title="Corpo"
                            code={json({
                                id: '01k7x4b2c6e8g0j2k4m6p8r0t2',
                                event: 'document.signed',
                                created_at: '2026-10-15T18:05:04-03:00',
                                data: {
                                    document_id: DOC_ID,
                                    external_id: 'CRM-0019',
                                    signer_id: SIGNER_ID,
                                    signer_email: 'lisiane@example.com',
                                    signed_at: '2026-10-15T18:05:03-03:00',
                                },
                            })}
                        />
                    </div>
                </Section>

                <Section id="eventos" title="Eventos">
                    <ul className="max-w-3xl divide-y rounded-xl border">
                        {events.map((e) => (
                            <li key={e.key} className="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-4 py-3 text-sm">
                                <code className="w-44 shrink-0 font-mono text-[0.8125rem]">{e.key}</code>
                                <span className="text-foreground/85">{e.label}</span>
                            </li>
                        ))}
                    </ul>
                    <ParamTable
                        title="Campos de data, além de document_id e external_id"
                        params={[
                            { name: 'document.sent', type: 'evento', description: <><C>sent_at</C>, <C>signers_count</C></> },
                            { name: 'document.viewed', type: 'evento', description: <><C>signer_id</C>, <C>signer_email</C></> },
                            { name: 'document.signed', type: 'evento', description: <><C>signer_id</C>, <C>signer_email</C>, <C>signed_at</C></> },
                            { name: 'document.completed', type: 'evento', description: <><C>completed_at</C>, <C>final_sha256</C> e <C>sealed_with_icp</C> (se o PDF final recebeu o selo ICP-Brasil da plataforma)</> },
                            { name: 'document.rejected', type: 'evento', description: <><C>signer_id</C>, <C>signer_email</C>, <C>reason</C></> },
                        ]}
                    />
                </Section>

                <Section id="verificar-assinatura" title="Verificar a assinatura">
                    <div className="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] [&>*]:min-w-0">
                        <Prose>
                            <p>
                                Antes de confiar no conteúdo, confirme que a chamada veio do Moduhub. Calcule um HMAC-SHA256 do texto{' '}
                                <C>{'{timestamp}.{corpo bruto}'}</C> com o segredo do endpoint e compare com o valor de{' '}
                                <C>X-Moduhub-Signature</C> (depois de <C>sha256=</C>).
                            </p>
                            <p>
                                Use o corpo exatamente como chegou, antes de qualquer parse de JSON. Compare em tempo constante e recuse chamadas
                                com timestamp de mais de 5 minutos atrás, para impedir que uma entrega antiga seja reenviada por terceiros.
                            </p>
                        </Prose>
                        <div className="space-y-4 xl:sticky xl:top-6 xl:self-start">
                            <CodeSample
                                samples={{
                                    curl: [
                                        '# Conferência manual do HMAC com openssl',
                                        'echo -n "$TIMESTAMP.$RAW_BODY" \\',
                                        '  | openssl dgst -sha256 -hmac "$MODUHUB_WEBHOOK_SECRET"',
                                    ].join('\n'),
                                    javascript: [
                                        "import { createHmac, timingSafeEqual } from 'node:crypto';",
                                        '',
                                        '// Express: app.post(\'/webhooks/moduhub\', express.raw({ type: \'application/json\' }), handler)',
                                        'export function isFromModuhub(req) {',
                                        "  const timestamp = req.header('X-Moduhub-Timestamp');",
                                        "  const received = (req.header('X-Moduhub-Signature') ?? '').replace('sha256=', '');",
                                        '  if (Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) return false;',
                                        '',
                                        '  const expected = createHmac(\'sha256\', process.env.MODUHUB_WEBHOOK_SECRET)',
                                        '    .update(`${timestamp}.${req.body}`)',
                                        "    .digest('hex');",
                                        '',
                                        '  return received.length === expected.length',
                                        '    && timingSafeEqual(Buffer.from(received), Buffer.from(expected));',
                                        '}',
                                    ].join('\n'),
                                    php: [
                                        'function isFromModuhub(Illuminate\\Http\\Request $request): bool',
                                        '{',
                                        "    $timestamp = (string) $request->header('X-Moduhub-Timestamp');",
                                        "    $received = str_replace('sha256=', '', (string) $request->header('X-Moduhub-Signature'));",
                                        '',
                                        '    if (abs(time() - (int) $timestamp) > 300) {',
                                        '        return false;',
                                        '    }',
                                        '',
                                        "    $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), env('MODUHUB_WEBHOOK_SECRET'));",
                                        '',
                                        '    return hash_equals($expected, $received);',
                                        '}',
                                    ].join('\n'),
                                }}
                            />
                        </div>
                    </div>
                </Section>

                <Section id="retentativas" title="Entregas e retentativas">
                    <Prose>
                        <p>
                            Responda com qualquer status <C>2xx</C> em até 10 segundos para confirmar o recebimento. Se a resposta demorar ou
                            vier com outro status, o Moduhub tenta de novo: são até 6 tentativas, a primeira na hora e as seguintes depois de
                            1 minuto, 5 minutos, 30 minutos, 2 horas e 6 horas.
                        </p>
                        <p>
                            Como uma entrega pode chegar mais de uma vez, use o <C>id</C> do corpo (igual ao cabeçalho{' '}
                            <C>X-Moduhub-Delivery</C>) para ignorar repetições. Faça o trabalho pesado depois de responder, numa fila.
                        </p>
                        <p>
                            O histórico de cada entrega, com o status e a resposta do seu servidor, fica no painel, na página do webhook. De lá
                            você também pode disparar uma entrega de teste.
                        </p>
                    </Prose>
                </Section>
            </DocsLayout>
        </CodeLanguageProvider>
    );
}
