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
    | O manual de integração da NFS-e Nacional especifica RSA-SHA1 na assinatura da DPS e dos eventos.
    | Fica configurável para trocar por sha256 sem mexer em código se a Sefin passar a exigir.
    */
    'signature_digest' => env('INVOICES_SIGNATURE_DIGEST', 'sha1'),

    'app_version' => 'Moduhub 1.0',

    'layout_version' => '1.01',

];
