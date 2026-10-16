<?php

namespace App\Modules\Signatures\Services;

use Smalot\PdfParser\Parser;

class PdfInspector
{
    public function pageCount(string $absolutePath): int
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($absolutePath);
            return count($pdf->getPages());
        } catch (\Throwable) {
            return 0;
        }
    }
}
