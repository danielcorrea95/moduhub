<?php

namespace Tests\Support;

/**
 * Gera certificados PKCS#12 autoassinados para os testes, imitando o perfil de um e-CNPJ A1
 * (CNPJ no CN, uso de chave para assinatura e não repúdio, autenticação de cliente TLS).
 */
trait CreatesPkcs12
{
    protected const PKCS12_PASSWORD = 'segredo-teste';

    /**
     * @param  'leaf'|'ca'  $profile  `leaf` é um certificado de titular; `ca` simula um certificado de AC.
     * @param  int  $chainLength  quantos certificados de AC anexar ao .pfx, como faz um A1 real com a cadeia até a raiz
     * @return string conteúdo binário do .pfx
     */
    protected function makePkcs12(string $commonName, string $profile = 'leaf', string $password = self::PKCS12_PASSWORD, int $chainLength = 0): string
    {
        $config = tempnam(sys_get_temp_dir(), 'mdhb_openssl_');
        file_put_contents($config, <<<'CNF'
            [ req ]
            distinguished_name = req_dn
            default_md = sha256
            [ req_dn ]
            [ v3_leaf ]
            basicConstraints = CA:FALSE
            keyUsage = critical, digitalSignature, nonRepudiation, keyEncipherment
            extendedKeyUsage = clientAuth, emailProtection
            [ v3_ca ]
            basicConstraints = critical, CA:TRUE
            keyUsage = critical, digitalSignature, nonRepudiation, keyCertSign, cRLSign
            CNF);

        try {
            $options = ['config' => $config, 'digest_alg' => 'sha256'];
            $key = openssl_pkey_new($options + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = openssl_csr_new(['CN' => $commonName, 'O' => 'ICP-Brasil', 'C' => 'BR'], $key, $options);
            $cert = openssl_csr_sign($csr, null, $key, 365, $options + ['x509_extensions' => $profile === 'ca' ? 'v3_ca' : 'v3_leaf'], random_int(1, PHP_INT_MAX));

            $this->assertNotFalse($cert, 'Falha ao gerar certificado de teste: '.openssl_error_string());

            $chain = [];
            for ($i = 1; $i <= $chainLength; $i++) {
                $caKey = openssl_pkey_new($options + ['private_key_bits' => 4096, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
                $caCsr = openssl_csr_new(['CN' => "AC Teste {$i}", 'O' => 'ICP-Brasil', 'C' => 'BR'], $caKey, $options);
                $chain[] = openssl_csr_sign($caCsr, null, $caKey, 365, $options + ['x509_extensions' => 'v3_ca'], random_int(1, PHP_INT_MAX));
            }

            openssl_pkcs12_export($cert, $pfx, $key, $password, ['extracerts' => $chain]);

            return $pfx;
        } finally {
            @unlink($config);
        }
    }
}
