<?php

/*
 * Discos dos arquivos dos clientes. Com FILE_STORAGE_DRIVER=s3 vão para o bucket AWS_BUCKET,
 * cada um sob o seu prefixo, privados e cifrados no servidor. Em dev ficam em storage/app/private.
 * Operação: docs/runbooks/armazenamento.md.
 */
$privateDisk = function (string $prefix): array {
    if (env('FILE_STORAGE_DRIVER', 'local') !== 's3') {
        return [
            'driver' => 'local',
            'root' => storage_path("app/private/{$prefix}"),
            'visibility' => 'private',
            'throw' => true,
        ];
    }

    $kmsKey = env('AWS_KMS_KEY_ID');

    return [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'root' => $prefix,
        'visibility' => 'private',
        // Sem ACL por objeto: buckets novos vêm com "Object Ownership = Bucket owner enforced" e recusam ACL.
        'retain_visibility' => false,
        'options' => $kmsKey
            ? ['ServerSideEncryption' => 'aws:kms', 'SSEKMSKeyId' => $kmsKey]
            : ['ServerSideEncryption' => 'AES256'],
        'throw' => true,
        'report' => false,
    ];
};

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // PDFs de assinatura (originais, de trabalho, finais selados) e imagens das assinaturas.
        'documents' => $privateDisk('documents'),

        // XMLs fiscais da NFS-e (DPS, nota autorizada, eventos, distribuição do ADN). Guarda legal de 5 anos.
        'fiscal' => $privateDisk('fiscal'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
