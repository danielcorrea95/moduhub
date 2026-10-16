<?php

namespace App\Modules\Invoices\Services\Nfse;

use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\Issuer;
use DOMDocument;
use DOMElement;
use Illuminate\Validation\ValidationException;

/**
 * Monta a DPS (Declaração de Prestação de Serviço) no leiaute nacional v1.01 a partir da nota e do emitente.
 * A ordem dos elementos segue o XSD; as regras de presença seguem o Anexo I (RN da DPS).
 */
class DpsBuilder
{
    public const NS = 'http://www.sped.fazenda.gov.br/nfse';

    private const TP_INSC_CPF = '1';
    private const TP_INSC_CNPJ = '2';

    /** Prestador emitindo a própria DPS. */
    private const TP_EMIT_PROVIDER = '1';

    /** tpRetISSQN: 1 não retido, 2 retido pelo tomador. */
    private const ISS_NOT_WITHHELD = '1';
    private const ISS_WITHHELD_BY_TAKER = '2';

    public static function dpsId(Issuer $issuer, string $series, int $number): string
    {
        self::assertNumericCnpj($issuer->cnpj);

        return 'DPS'
            .$issuer->city_code
            .self::TP_INSC_CNPJ
            .str_pad($issuer->cnpj, 14, '0', STR_PAD_LEFT)
            .str_pad($series, 5, '0', STR_PAD_LEFT)
            .str_pad((string) $number, 15, '0', STR_PAD_LEFT);
    }

    public function build(Invoice $invoice, Issuer $issuer): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        $root = $doc->createElementNS(self::NS, 'DPS');
        $root->setAttribute('versao', (string) config('invoices.layout_version'));
        $doc->appendChild($root);

        $inf = $this->el($root, 'infDPS');
        $inf->setAttribute('Id', $invoice->dps_id);

        $this->el($inf, 'tpAmb', $issuer->isProduction() ? '1' : '2');
        // O leiaute exige o fuso explícito (-03:00); "Z" não passa no pattern TSDateTimeUTC.
        $this->el($inf, 'dhEmi', $invoice->emitted_at->copy()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i:sP'));
        $this->el($inf, 'verAplic', (string) config('invoices.app_version'));
        $this->el($inf, 'serie', $invoice->dps_series);
        $this->el($inf, 'nDPS', (string) $invoice->dps_number);
        $this->el($inf, 'dCompet', $invoice->competence_date->format('Y-m-d'));
        $this->el($inf, 'tpEmit', self::TP_EMIT_PROVIDER);
        $this->el($inf, 'cLocEmi', $issuer->city_code);

        $this->provider($inf, $issuer);
        $this->taker($inf, $invoice->taker);
        $this->service($inf, $invoice->service, $issuer);
        $this->values($inf, $invoice, $issuer);

        return $doc;
    }

    private function provider(DOMElement $inf, Issuer $issuer): void
    {
        $prest = $this->el($inf, 'prest');
        $this->el($prest, 'CNPJ', $issuer->cnpj);
        $this->optional($prest, 'IM', $issuer->municipal_registration);
        // Com tpEmit=1 o Anexo I proíbe xNome e end do prestador: a Sefin usa o cadastro nacional (CNC).
        $this->optional($prest, 'fone', $issuer->phone);
        $this->optional($prest, 'email', NfseText::clean($issuer->email));

        $regTrib = $this->el($prest, 'regTrib');
        $this->el($regTrib, 'opSimpNac', (string) $issuer->simples_nacional);
        if ($issuer->simples_nacional === Issuer::SIMPLES_ME_EPP && $issuer->simples_regime !== null) {
            $this->el($regTrib, 'regApTribSN', (string) $issuer->simples_regime);
        }
        $this->el($regTrib, 'regEspTrib', (string) $issuer->special_regime);
    }

    /** @param  array<string, mixed>  $taker */
    private function taker(DOMElement $inf, array $taker): void
    {
        $toma = $this->el($inf, 'toma');
        $document = (string) ($taker['document'] ?? '');
        $this->el($toma, strlen($document) === 11 ? 'CPF' : 'CNPJ', $document);
        $this->optional($toma, 'IM', $taker['municipal_registration'] ?? null);
        $this->el($toma, 'xNome', NfseText::clean($taker['name'] ?? null) ?? '');

        $address = $taker['address'] ?? null;
        if (is_array($address) && ! empty($address['postal_code'])) {
            $end = $this->el($toma, 'end');
            $endNac = $this->el($end, 'endNac');
            $this->el($endNac, 'cMun', (string) $address['city_code']);
            $this->el($endNac, 'CEP', (string) $address['postal_code']);
            $this->el($end, 'xLgr', NfseText::clean($address['street'] ?? null) ?? '');
            $this->el($end, 'nro', NfseText::clean($address['number'] ?? null) ?? 'S/N');
            $this->optional($end, 'xCpl', NfseText::clean($address['complement'] ?? null));
            $this->el($end, 'xBairro', NfseText::clean($address['district'] ?? null) ?? '');
        }

        $this->optional($toma, 'fone', $taker['phone'] ?? null);
        $this->optional($toma, 'email', NfseText::clean($taker['email'] ?? null));
    }

    /** @param  array<string, mixed>  $service */
    private function service(DOMElement $inf, array $service, Issuer $issuer): void
    {
        $serv = $this->el($inf, 'serv');
        $loc = $this->el($serv, 'locPrest');
        $this->el($loc, 'cLocPrestacao', (string) ($service['city_code'] ?? $issuer->city_code));

        $cServ = $this->el($serv, 'cServ');
        $this->el($cServ, 'cTribNac', (string) $service['national_tax_code']);
        $this->optional($cServ, 'cTribMun', $service['municipal_tax_code'] ?? null);
        $this->el($cServ, 'xDescServ', NfseText::clean($service['description'] ?? null) ?? '');
        $this->optional($cServ, 'cNBS', $service['nbs_code'] ?? null);
    }

    private function values(DOMElement $inf, Invoice $invoice, Issuer $issuer): void
    {
        $valores = $this->el($inf, 'valores');
        $vServPrest = $this->el($valores, 'vServPrest');
        $this->el($vServPrest, 'vServ', NfseText::money($invoice->amount));

        $trib = $this->el($valores, 'trib');
        $tribMun = $this->el($trib, 'tribMun');
        $this->el($tribMun, 'tribISSQN', (string) $invoice->iss_taxation);
        $this->el($tribMun, 'tpRetISSQN', $invoice->iss_withheld ? self::ISS_WITHHELD_BY_TAKER : self::ISS_NOT_WITHHELD);
        // A obrigatoriedade de pAliq depende do convênio do município e do regime (Anexo I); quem decide é o chamador.
        if ($invoice->iss_rate !== null) {
            $this->el($tribMun, 'pAliq', NfseText::money($invoice->iss_rate));
        }

        $totTrib = $this->el($trib, 'totTrib');
        if ($issuer->simples_nacional === Issuer::SIMPLES_MEI) {
            // MEI pode declarar que não informa os tributos aproximados (Decreto 8.264/2014).
            $this->el($totTrib, 'indTotTrib', '0');
        } else {
            // Não optante e ME/EPP não podem usar indTotTrib: vão os percentuais, zerados quando não informados.
            $taxes = $invoice->approximate_taxes ?? [];
            $pTotTrib = $this->el($totTrib, 'pTotTrib');
            $this->el($pTotTrib, 'pTotTribFed', NfseText::money($taxes['federal'] ?? 0));
            $this->el($pTotTrib, 'pTotTribEst', NfseText::money($taxes['state'] ?? 0));
            $this->el($pTotTrib, 'pTotTribMun', NfseText::money($taxes['municipal'] ?? 0));
        }
    }

    private function el(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $parent->ownerDocument->createElementNS(self::NS, $name);
        if ($value !== null) {
            $element->appendChild($parent->ownerDocument->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }

    private function optional(DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->el($parent, $name, $value);
        }
    }

    private static function assertNumericCnpj(string $cnpj): void
    {
        // O leiaute v1.01 ainda só aceita CNPJ numérico no Id da DPS e no grupo do prestador.
        if (! ctype_digit($cnpj)) {
            throw ValidationException::withMessages([
                'issuer' => 'O leiaute atual da NFS-e Nacional ainda não aceita CNPJ alfanumérico.',
            ]);
        }
    }
}
