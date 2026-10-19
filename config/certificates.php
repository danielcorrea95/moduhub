<?php

return [

    /*
    | Chave própria para o .pfx e a senha dos certificados guardados no banco, separada da APP_KEY:
    | a APP_KEY também assina sessões e cookies e circula mais, e vazar ela não pode abrir os
    | certificados dos clientes. Mesmo formato da APP_KEY (base64:...), e precisa ser diferente dela.
    |
    | Para trocar a chave, mova a atual para CERTIFICATES_ENCRYPTION_PREVIOUS_KEYS (separadas por
    | vírgula): o que foi cifrado com ela continua legível, e cada certificado salvo de novo sai com a nova.
    */
    'key' => env('CERTIFICATES_ENCRYPTION_KEY'),

    'previous_keys' => array_values(array_filter(explode(',', (string) env('CERTIFICATES_ENCRYPTION_PREVIOUS_KEYS', '')))),

    'cipher' => 'AES-256-CBC',

];
