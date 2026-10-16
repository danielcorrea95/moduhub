<?php

namespace App\Modules\Invoices\Services\Nfse;

use DOMDocument;
use Illuminate\Validation\ValidationException;

/**
 * Valida o XML contra os schemas oficiais em resources/nfse/schemas antes de enviar,
 * para que erros de leiaute apareçam aqui com a mensagem do libxml e não como rejeição da Sefin.
 */
class XmlSchemaValidator
{
    public function assertValid(DOMDocument $doc, string $schema): void
    {
        $path = resource_path('nfse/schemas/'.config('invoices.layout_version').'/'.$schema);

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $valid = $doc->schemaValidate($path);
            $errors = array_map(
                fn (\LibXMLError $e) => trim($e->message),
                libxml_get_errors(),
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'dps' => array_values(array_unique(array_map(
                    fn (string $m) => 'Leiaute da NFS-e: '.$m,
                    $errors ?: ['XML fora do schema oficial.'],
                ))),
            ]);
        }
    }
}
