<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        // Credencial própria do SES; sem ela, cai na mesma do S3.
        'key' => env('SES_KEY', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('SES_SECRET', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        // Único tópico aceito em /webhooks/ses. Vazio = webhook recusa tudo.
        'sns_topic_arn' => env('SES_SNS_TOPIC_ARN'),
        // Domínios de envio dos workspaces: ses (API do SES) ou fake (dev e testes, verifica na 1ª consulta).
        'identity_driver' => env('SES_IDENTITY_DRIVER', env('MAIL_MAILER') === 'ses-v2' ? 'ses' : 'fake'),
    ],

    'asaas' => [
        // asaas (API) ou fake (dev e testes).
        'driver' => env('ASAAS_DRIVER', 'fake'),
        'api_key' => env('ASAAS_API_KEY'),
        'base_url' => env('ASAAS_BASE_URL', 'https://api-sandbox.asaas.com/v3'),
        // Conferido no header asaas-access-token de /webhooks/asaas. Vazio = webhook recusa tudo.
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
