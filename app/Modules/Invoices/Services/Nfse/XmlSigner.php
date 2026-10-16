<?php

namespace App\Modules\Invoices\Services\Nfse;

use DOMElement;
use RuntimeException;

/**
 * Assinatura XMLDSig envelopada no padrão da NFS-e Nacional: o elemento com atributo Id
 * (infDPS, infPedReg) é referenciado e a Signature entra como irmã dele, dentro da raiz.
 */
class XmlSigner
{
    public const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';

    private const C14N = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

    private const ALGORITHMS = [
        'sha1' => [
            'digest' => 'http://www.w3.org/2000/09/xmldsig#sha1',
            'signature' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
            'openssl' => OPENSSL_ALGO_SHA1,
        ],
        'sha256' => [
            'digest' => 'http://www.w3.org/2001/04/xmlenc#sha256',
            'signature' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
            'openssl' => OPENSSL_ALGO_SHA256,
        ],
    ];

    public function sign(DOMElement $element, IssuerCredentials $credentials, ?string $digest = null): void
    {
        $digest ??= (string) config('invoices.signature_digest', 'sha1');
        $algorithm = self::ALGORITHMS[$digest] ?? throw new RuntimeException("Algoritmo de assinatura não suportado: {$digest}");

        $id = $element->getAttribute('Id');
        $doc = $element->ownerDocument;
        $parent = $element->parentNode;

        if ($id === '' || $doc === null || ! $parent instanceof DOMElement) {
            throw new RuntimeException('O elemento a assinar precisa de atributo Id e de um elemento pai.');
        }

        $digestValue = base64_encode(hash($digest, $element->C14N(false, false), true));

        $signature = $doc->createElementNS(self::DS_NS, 'Signature');
        $parent->appendChild($signature);

        $signedInfo = $this->child($signature, 'SignedInfo');
        $this->child($signedInfo, 'CanonicalizationMethod')->setAttribute('Algorithm', self::C14N);
        $this->child($signedInfo, 'SignatureMethod')->setAttribute('Algorithm', $algorithm['signature']);

        $reference = $this->child($signedInfo, 'Reference');
        $reference->setAttribute('URI', '#'.$id);
        $transforms = $this->child($reference, 'Transforms');
        $this->child($transforms, 'Transform')->setAttribute('Algorithm', self::DS_NS.'enveloped-signature');
        $this->child($transforms, 'Transform')->setAttribute('Algorithm', self::C14N);
        $this->child($reference, 'DigestMethod')->setAttribute('Algorithm', $algorithm['digest']);
        $this->child($reference, 'DigestValue', $digestValue);

        // SignedInfo é canonicalizado já no lugar, para herdar o namespace xmldsig declarado na Signature.
        $signatureValue = '';
        if (! openssl_sign($signedInfo->C14N(false, false), $signatureValue, $credentials->privateKeyPem, $algorithm['openssl'])) {
            throw new RuntimeException('Falha ao assinar o XML: '.openssl_error_string());
        }

        $this->child($signature, 'SignatureValue', base64_encode($signatureValue));
        $x509Data = $this->child($this->child($signature, 'KeyInfo'), 'X509Data');
        $this->child($x509Data, 'X509Certificate', $credentials->certificateBase64());
    }

    private function child(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $parent->ownerDocument->createElementNS(self::DS_NS, $name);
        if ($value !== null) {
            $element->appendChild($parent->ownerDocument->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
