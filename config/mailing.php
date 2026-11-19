<?php

return [

    /*
     * Cota do disparo (SMTP e API do cliente), contada por mensagem. Workspace sem o módulo
     * `mailing` no plano fica na cota gratuita. O dia vira à meia-noite de Brasília.
     */
    'quota' => [
        'timezone' => 'America/Sao_Paulo',
        'free' => [
            'daily' => (int) env('MAIL_FREE_DAILY_QUOTA', 100),
            'monthly' => (int) env('MAIL_FREE_MONTHLY_QUOTA', 3000),
        ],
    ],

    /*
     * Abertura e clique vêm dos eventos Open e Click do configuration set (pixel e links
     * reescritos pelo SES). Ligue só depois de marcar os dois eventos no destino SNS da AWS;
     * desligado, o painel esconde as taxas em vez de mostrar 0%.
     */
    'track_engagement' => (bool) env('MAIL_TRACK_ENGAGEMENT', false),

    /*
     * Relay SMTP que os clientes usam a partir dos sistemas deles. Ele confere a credencial do
     * workspace, aplica as regras e repassa ao SES. Enquanto `enabled` for falso, o painel mostra
     * os dados e os exemplos, mas avisa que o servidor ainda está em implantação.
     */
    'relay' => [
        'enabled' => (bool) env('MAIL_RELAY_ENABLED', false),
        'host' => env('MAIL_RELAY_HOST', 'relay.moduhub.com.br'),

        // Segredo que o servidor SMTP (smtp-relay/) manda no header X-Relay-Secret. Vazio, os
        // endpoints internos recusam tudo.
        'secret' => env('MAIL_RELAY_SECRET'),

        // O SES aceita até 50 destinatários e 40 MB por mensagem; o JSON do relay leva o MIME em
        // base64 (+33%), e o nginx e o PHP precisam aceitar o corpo (docs/runbooks/email.md).
        'max_recipients' => 50,
        'max_bytes' => (int) env('MAIL_RELAY_MAX_BYTES', 10 * 1024 * 1024),

        // Senha errada repetida a partir do mesmo IP: bloqueia por alguns minutos.
        'auth_attempts' => 10,
        'auth_decay_seconds' => 300,
        'ports' => [
            ['port' => 587, 'encryption' => 'tls', 'label' => 'STARTTLS (recomendado)'],
            ['port' => 465, 'encryption' => 'ssl', 'label' => 'SSL/TLS'],
        ],
    ],

];
