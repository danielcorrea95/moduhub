<?php

return [

    /*
    | `http` fala com a Sefin Nacional usando o certificado do emitente (mTLS).
    | `fake` simula as respostas em memória, para testes e desenvolvimento sem e-CNPJ.
    */
    'sefin_driver' => env('INVOICES_SEFIN_DRIVER', 'http'),

    'sefin_urls' => [
        'restricted' => env('INVOICES_SEFIN_URL_RESTRICTED', 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional'),
        'production' => env('INVOICES_SEFIN_URL_PRODUCTION', 'https://sefin.nfse.gov.br/SefinNacional'),
    ],

    'timeout' => (int) env('INVOICES_SEFIN_TIMEOUT', 30),

    /*
    | ADN (Ambiente de Dados Nacional): parâmetros que cada município cadastra no Sistema Nacional,
    | como as alíquotas por código de serviço. A consulta é só uma checagem prévia: se o ADN demora
    | ou falha, a emissão segue e a Sefin decide.
    */
    'adn_urls' => [
        'restricted' => env('INVOICES_ADN_URL_RESTRICTED', 'https://adn.producaorestrita.nfse.gov.br'),
        'production' => env('INVOICES_ADN_URL_PRODUCTION', 'https://adn.nfse.gov.br'),
    ],

    'adn_timeout' => (int) env('INVOICES_ADN_TIMEOUT', 8),

    /*
    | O manual de integração da NFS-e Nacional especifica RSA-SHA1 na assinatura da DPS e dos eventos.
    | Fica configurável para trocar por sha256 sem mexer em código se a Sefin passar a exigir.
    */
    'signature_digest' => env('INVOICES_SIGNATURE_DIGEST', 'sha1'),

    'app_version' => 'Moduhub 1.0',

    'layout_version' => '1.01',

    /*
    | Pasta de resources/nfse/schemas usada por ambiente. A produção restrita adotou a NT 009
    | (pacote de 20/10/2026) mantendo versao="1.01", enquanto a produção segue no pacote de 09/02/2026.
    | Quando a NT 009 entrar em produção, basta apontar a produção para 1.01-nt009.
    */
    'layouts' => [
        'restricted' => env('INVOICES_LAYOUT_RESTRICTED', '1.01-nt009'),
        'production' => env('INVOICES_LAYOUT_PRODUCTION', '1.01'),
    ],

];
