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

    /*
    | Cadeia de confiança da ICP-Brasil, baixada do repositório do ITI (acraiz.icpbrasil.gov.br).
    | O certificado da plataforma enviado pelo admin só entra se fechar até uma destas raízes.
    | Como atualizar: docs/runbooks/certificados.md, "Raízes da ICP-Brasil".
    */
    'icp' => [
        'roots_path' => env('SIGNATURES_ICP_ROOTS_PATH', resource_path('certificates/icp-brasil/roots.pem')),
        'intermediates_path' => env('SIGNATURES_ICP_INTERMEDIATES_PATH', resource_path('certificates/icp-brasil/intermediates.pem')),
    ],

    'seal' => [
        'certificate_path' => env('SIGNATURES_PLATFORM_CERT_PATH', storage_path('app/private/certificates/moduhub.pfx')),
        'certificate_password' => env('SIGNATURES_PLATFORM_CERT_PASSWORD'),
        // CNPJ da Moduhub. O certificado enviado pelo admin (/admin/certificado) precisa ser deste CNPJ
        // (matriz ou filial): quem invadir uma conta de admin não consegue pôr outro no selo.
        'platform_cnpj' => env('SIGNATURES_PLATFORM_CNPJ'),
        // Quem é avisado quando o certificado da plataforma está vencendo ou não abre. Separados por vírgula.
        'alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SIGNATURES_PLATFORM_CERT_ALERT_EMAILS', ''))))),
    ],

];
