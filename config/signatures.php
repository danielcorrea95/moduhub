<?php

return [

    /*
    | Certificado ICP-Brasil (e-CNPJ A1) da plataforma. Todo documento concluído recebe uma assinatura
    | digital PKCS#7 com ele, que sela a integridade do PDF final. Os signatários assinam eletronicamente;
    | o certificado é da Moduhub, não deles.
    |
    | Sem senha configurada o selo fica desligado e os documentos são concluídos sem ele.
    */
    /*
    | Fuso dos horários impressos no relatório de assinaturas. O banco guarda tudo em UTC.
    */
    'report_timezone' => env('SIGNATURES_REPORT_TIMEZONE', 'America/Sao_Paulo'),

    'seal' => [
        'certificate_path' => env('SIGNATURES_PLATFORM_CERT_PATH', storage_path('app/private/certificates/moduhub.pfx')),
        'certificate_password' => env('SIGNATURES_PLATFORM_CERT_PASSWORD'),
        // Quem é avisado quando o certificado da plataforma está vencendo ou não abre. Separados por vírgula.
        'alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIGNATURES_PLATFORM_CERT_ALERT_EMAILS', ''))))),
    ],

];
