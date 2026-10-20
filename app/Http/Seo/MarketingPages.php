<?php

namespace App\Http\Seo;

use App\Models\Module;

/**
 * Conteúdo das páginas públicas que buscadores e IAs leem: títulos, descrições, perguntas frequentes
 * e o JSON-LD. A página e o JSON-LD saem daqui, então as perguntas do FAQPage são sempre as mesmas
 * que aparecem na tela. Preços vêm do catálogo cadastrado no admin.
 */
final class MarketingPages
{
    /** Página própria de cada produto: endereço, título e chamada. */
    public const PRODUCTS = [
        Module::SIGNATURES => [
            'path' => '/assinatura-eletronica',
            'name' => 'Assinatura eletrônica',
            'title' => 'Assinatura eletrônica de contratos com trilha de auditoria',
            'headline' => 'Assinatura eletrônica de contratos, pelo celular e com prova de quem assinou',
            'description' => 'Envie contratos em PDF para assinar pelo celular, sem certificado digital. Trilha de auditoria, hash SHA-256, selo ICP-Brasil no documento final e verificação pública. Teste grátis.',
        ],
        Module::INVOICES => [
            'path' => '/emissor-nfse',
            'name' => 'Emissor de NFS-e',
            'title' => 'Emissor de NFS-e pelo padrão nacional',
            'headline' => 'Emissor de NFS-e pelo padrão nacional, do painel ou pela API',
            'description' => 'Emita, cancele e substitua NFS-e pela Sefin Nacional com o seu certificado A1. Receba as notas tomadas, envie o DANFSe ao cliente e integre pelo ERP. Teste grátis.',
        ],
        Module::MAILING => [
            'path' => '/disparo-de-email',
            'name' => 'Disparo de e-mails',
            'title' => 'Disparo de e-mails por SMTP e API com o seu domínio',
            'headline' => 'Disparo de e-mails por SMTP e API, com o domínio da sua empresa no remetente',
            'description' => 'Envie e-mails transacionais do seu sistema por SMTP ou API com domínio próprio verificado (DKIM e SPF). Relatório de entrega, abertura e clique e lista de bloqueio automática. 3.000 e-mails grátis por mês.',
        ],
    ];

    private const UNITS = [
        Module::SIGNATURES => ['documento', 'documentos'],
        Module::INVOICES => ['nota', 'notas'],
        Module::MAILING => ['e-mail', 'e-mails'],
    ];

    /** @param  list<array<string, mixed>>  $products  catálogo de PlanCatalogService::products() */
    public static function home(array $products): Seo
    {
        return new Seo(
            title: 'Assinatura eletrônica, emissor de NFS-e e disparo de e-mails',
            description: 'Assine contratos pelo celular, emita NFS-e pelo padrão nacional e envie e-mails com o domínio da sua empresa, num painel só. Contrate só o que usar. Teste grátis por 14 dias.',
            path: '/',
            jsonLd: [
                self::organization(),
                self::website(),
                ...array_map(fn (array $product) => self::software($product), $products),
                self::faqPage(self::faq('home')),
            ],
        );
    }

    /** @param  array<string, mixed>|null  $product  o produto no catálogo, se tiver planos à venda */
    public static function product(string $key, ?array $product): Seo
    {
        $page = self::PRODUCTS[$key];

        return new Seo(
            title: $page['title'],
            description: $page['description'],
            path: $page['path'],
            jsonLd: array_values(array_filter([
                self::organization(),
                $product !== null ? self::software($product) : null,
                self::faqPage(self::faq($key)),
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Moduhub', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $page['name'], 'item' => url($page['path'])],
                    ],
                ],
            ])),
        );
    }

    public static function apiDocs(): Seo
    {
        return new Seo(
            title: 'Documentação da API',
            description: 'API REST da Moduhub para assinatura eletrônica de documentos e emissão de NFS-e: autenticação por chave, endpoints, exemplos em várias linguagens e webhooks.',
            path: '/docs/api',
            jsonLd: [[
                '@type' => 'TechArticle',
                'headline' => 'Documentação da API da Moduhub',
                'inLanguage' => 'pt-BR',
                'url' => url('/docs/api'),
                'publisher' => ['@type' => 'Organization', 'name' => Seo::SITE_NAME, 'url' => url('/')],
            ]],
        );
    }

    /**
     * Perguntas frequentes de cada página ('home' ou a chave do produto).
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function faq(string $page): array
    {
        $daily = number_format((int) config('mailing.quota.free.daily'), 0, ',', '.');
        $monthly = number_format((int) config('mailing.quota.free.monthly'), 0, ',', '.');

        return match ($page) {
            'home' => [
                ['question' => 'Preciso contratar os três produtos?', 'answer' => 'Não. Você escolhe um plano só dos produtos que quiser usar (assinatura eletrônica, NFS-e ou disparo de e-mails) e paga tudo numa mensalidade só. Dá para incluir ou tirar um produto depois, pelo painel.'],
                ['question' => 'Como funciona o teste grátis?', 'answer' => 'São 14 dias grátis, sem cartão de crédito. Se você não escolher um plano, o teste libera o plano de entrada de cada produto. A cobrança só começa se você assinar.'],
                ['question' => 'Quais são as formas de pagamento?', 'answer' => 'Pix ou cartão de crédito. No Pix, a cobrança chega todo mês por e-mail. No cartão, a mensalidade é cobrada automaticamente. Os pagamentos são processados pelo Asaas.'],
                ['question' => 'O que acontece se eu passar da cota do plano?', 'answer' => 'Nos planos com excedente, você continua usando e paga só as unidades a mais no fechamento do mês, sem trocar de plano. Nos planos sem excedente, o uso para até o próximo ciclo ou até você trocar de plano.'],
                ['question' => 'Posso cancelar quando quiser?', 'answer' => 'Pode, pelo próprio painel e sem multa. O acesso continua até o fim do período já pago.'],
            ],
            Module::SIGNATURES => [
                ['question' => 'A assinatura eletrônica tem validade jurídica?', 'answer' => 'Sim. A Medida Provisória 2.200-2/2001 (art. 10, § 2º) e a Lei 14.063/2020 reconhecem assinaturas eletrônicas feitas fora da ICP-Brasil quando as partes aceitam o meio. O Moduhub registra quem assinou, quando e de onde, e sela o documento final com certificado ICP-Brasil para provar que ele não foi alterado.'],
                ['question' => 'Quem assina precisa criar conta ou ter certificado digital?', 'answer' => 'Não. O signatário recebe um link por e-mail, confirma com um código e assina desenhando ou digitando o nome, pelo celular ou pelo computador, sem instalar nada.'],
                ['question' => 'Como eu provo quem assinou o documento?', 'answer' => 'Cada documento concluído leva uma trilha de auditoria com quem abriu, o código confirmado, o IP e o horário de cada assinatura, o hash SHA-256 do original e do final, o selo ICP-Brasil da plataforma e um código de verificação pública.'],
                ['question' => 'Dá para mandar documentos direto do meu sistema?', 'answer' => 'Dá. A API cria o documento, adiciona os signatários e envia numa chamada, e avisa o seu sistema de cada assinatura por webhook.'],
            ],
            Module::INVOICES => [
                ['question' => 'Meu município emite NFS-e pelo padrão nacional?', 'answer' => 'O Moduhub emite pela Sefin Nacional, o emissor do padrão nacional da NFS-e. O painel consulta os convênios e mostra se o seu município já aderiu antes de você emitir.'],
                ['question' => 'Preciso de certificado digital?', 'answer' => 'Sim, o certificado A1 (e-CNPJ em arquivo .pfx) da empresa emissora. Ele fica guardado cifrado, cada uso é registrado e o Moduhub avisa antes do vencimento.'],
                ['question' => 'Recebo as notas emitidas contra o meu CNPJ?', 'answer' => 'Sim. As notas tomadas chegam sozinhas pelo Ambiente de Dados Nacional, com XML e PDF, para o financeiro conferir.'],
                ['question' => 'Posso cancelar ou substituir uma nota?', 'answer' => 'Pode, pelo painel ou pela API, informando o motivo exigido pelo leiaute. A substituição emite a nota corrigida e cancela a original.'],
            ],
            Module::MAILING => [
                ['question' => 'Preciso ter domínio próprio?', 'answer' => 'Sim. Você confirma o domínio da empresa uma vez, com registros DKIM e SPF no DNS, e os e-mails saem assinados por ele. Endereços de provedores gratuitos, como Gmail, não podem ser usados como remetente.'],
                ['question' => 'Quantos e-mails posso enviar de graça?', 'answer' => "{$daily} e-mails por dia e {$monthly} por mês sem custo, em qualquer conta. Para mais, escolha um plano de e-mail."],
                ['question' => 'Funciona com o meu sistema?', 'answer' => 'Funciona com qualquer linguagem que envie e-mail por SMTP (porta 587 com STARTTLS) ou faça chamadas HTTP para a API. Basta trocar o servidor de e-mail do sistema pelo da Moduhub.'],
                ['question' => 'O que acontece com e-mails que voltam?', 'answer' => 'Endereços que não existem ou que marcaram a mensagem como spam entram numa lista de bloqueio e deixam de receber, para proteger a reputação do seu domínio.'],
            ],
            default => [],
        };
    }

    /**
     * Fora do índice: o painel, links de assinatura e de convite (com token) e a verificação de cada
     * documento, que mostra nomes de quem assinou.
     */
    public static function robotsTxt(): string
    {
        $disallow = ['/dashboard', '/workspace', '/workspaces', '/signatures', '/invoices', '/admin', '/profile', '/sign/', '/invitations/', '/v/', '/api/', '/webhooks/'];

        return "User-agent: *\n"
            .implode('', array_map(fn ($p) => "Disallow: {$p}\n", $disallow))
            ."\nSitemap: ".url('/sitemap.xml')."\n";
    }

    /**
     * Resumo em Markdown para assistentes de IA (llmstxt.org): o que a Moduhub faz, os produtos, os
     * preços em vigor e onde está a documentação.
     *
     * @param  list<array<string, mixed>>  $products
     */
    public static function llmsTxt(array $products): string
    {
        $money = fn (int $cents) => 'R$ '.number_format($cents / 100, 2, ',', '.');
        $lines = [
            '# Moduhub',
            '',
            '> Plataforma brasileira para empresas de serviço: assinatura eletrônica de contratos, emissão de NFS-e pelo padrão nacional e disparo de e-mails com domínio próprio. Cada produto é contratado separadamente e cobrado numa mensalidade só, por Pix ou cartão. Teste grátis de 14 dias, sem cartão.',
            '',
            '## Produtos',
            '',
        ];

        foreach (self::PRODUCTS as $page) {
            $lines[] = "- [{$page['name']}](".url($page['path'])."): {$page['description']}";
        }

        $lines = [...$lines, '', '## Planos e preços (por mês)', ''];
        foreach ($products as $product) {
            $lines[] = '### '.(self::PRODUCTS[$product['key']]['name'] ?? $product['name']);
            foreach ($product['plans'] as $plan) {
                [$one, $many] = self::UNITS[$product['key']] ?? ['unidade', 'unidades'];
                $quota = $plan['included_quota'] === null ? "{$many} sem limite" : number_format($plan['included_quota'], 0, ',', '.')." {$many} por mês";
                $overage = $plan['overage_cents'] === null ? '' : ', excedente de '.$money($plan['overage_cents'])." por {$one}";
                $lines[] = "- {$plan['name']}: {$money($plan['price_cents'])} ({$quota}{$overage})";
            }
            $lines[] = '';
        }

        $lines = [
            ...$lines,
            '## Perguntas frequentes',
            '',
            ...array_map(fn (array $f) => "- **{$f['question']}** {$f['answer']}", self::faq('home')),
            '',
            '## Documentação',
            '',
            '- [Documentação da API](' . url('/docs/api') . '): API REST com autenticação por chave (Bearer), assinatura de documentos, emissão de NFS-e e webhooks.',
            '- [Criar conta](' . url('/register') . '): teste grátis de 14 dias.',
        ];

        return implode("\n", $lines)."\n";
    }

    /** @return array<string, mixed> */
    private static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => Seo::SITE_NAME,
            'url' => url('/'),
            'logo' => url('/apple-touch-icon.png'),
        ];
    }

    /** @return array<string, mixed> */
    private static function website(): array
    {
        return [
            '@type' => 'WebSite',
            'name' => Seo::SITE_NAME,
            'url' => url('/'),
            'inLanguage' => 'pt-BR',
            'publisher' => ['@id' => url('/').'#organization'],
        ];
    }

    /**
     * Um produto como aplicativo web, com a faixa de preço dos planos à venda.
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private static function software(array $product): array
    {
        $page = self::PRODUCTS[$product['key']] ?? null;
        $prices = array_map(fn (array $plan) => $plan['price_cents'] / 100, $product['plans']);

        return array_filter([
            '@type' => 'SoftwareApplication',
            'name' => 'Moduhub '.($page['name'] ?? $product['name']),
            'description' => $page['description'] ?? $product['description'],
            'url' => url($page['path'] ?? '/'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => 'pt-BR',
            'publisher' => ['@id' => url('/').'#organization'],
            'offers' => $prices === [] ? null : [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'BRL',
                'lowPrice' => min($prices),
                'highPrice' => max($prices),
                'offerCount' => count($prices),
            ],
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faq
     * @return array<string, mixed>
     */
    private static function faqPage(array $faq): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $faq),
        ];
    }
}
