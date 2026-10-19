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

    /** tpRetISSQN: 1 não retido, 2 retido pelo tomador, 3 retido pelo intermediário. */
    private const ISS_NOT_WITHHELD = '1';

    private const ISS_WITHHELD_BY_TAKER = '2';

    private const ISS_WITHHELD_BY_INTERMEDIARY = '3';

    private const FIN_NFSE_REGULAR = '0';

    private const IND_DEST_TAKER = '0';

    private const IND_DEST_OTHER = '1';

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

        if ($invoice->isReplacement()) {
            $this->substitution($inf, $invoice);
        }

        $this->provider($inf, $issuer);
        $this->person($inf, 'toma', $invoice->taker);
        if (! empty($invoice->intermediary)) {
            $this->person($inf, 'interm', $invoice->intermediary);
        }
        $this->service($inf, $invoice->service, $issuer);
        $this->values($inf, $invoice, $issuer);

        if (! empty($invoice->ibs_cbs)) {
            $this->ibsCbs($inf, $invoice->ibs_cbs);
        }

        return $doc;
    }

    private function substitution(DOMElement $inf, Invoice $invoice): void
    {
        $subst = $this->el($inf, 'subst');
        $this->el($subst, 'chSubstda', (string) $invoice->replaces->access_key);
        $this->el($subst, 'cMotivo', (string) $invoice->substitution_reason_code);
        $this->optional($subst, 'xMotivo', NfseText::clean($invoice->substitution_reason));
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

    /**
     * Tomador ou intermediário (TCInfoPessoa). Estrangeiro vai com NIF (ou cNaoNIF) e endereço endExt.
     *
     * @param  array<string, mixed>  $person
     */
    private function person(DOMElement $inf, string $tag, array $person): void
    {
        $node = $this->el($inf, $tag);
        $document = (string) ($person['document'] ?? '');
        match (true) {
            $document !== '' => $this->el($node, strlen($document) === 11 ? 'CPF' : 'CNPJ', $document),
            ! empty($person['nif']) => $this->el($node, 'NIF', NfseText::clean($person['nif'])),
            default => $this->el($node, 'cNaoNIF', (string) $person['nif_absence_reason']),
        };
        $this->optional($node, 'IM', $person['municipal_registration'] ?? null);
        $this->el($node, 'xNome', NfseText::clean($person['name'] ?? null) ?? '');

        $address = $person['address'] ?? null;
        if (is_array($address) && ! empty($address['postal_code'])) {
            $end = $this->el($node, 'end');
            $country = $address['country'] ?? null;
            if ($country !== null && $country !== 'BR') {
                $endExt = $this->el($end, 'endExt');
                $this->el($endExt, 'cPais', $country);
                $this->el($endExt, 'cEndPost', NfseText::clean((string) $address['postal_code']) ?? '');
                $this->el($endExt, 'xCidade', NfseText::clean($address['city'] ?? null) ?? '');
                $this->el($endExt, 'xEstProvReg', NfseText::clean($address['state'] ?? null) ?? '');
            } else {
                $endNac = $this->el($end, 'endNac');
                $this->el($endNac, 'cMun', (string) $address['city_code']);
                $this->el($endNac, 'CEP', (string) $address['postal_code']);
            }
            $this->el($end, 'xLgr', NfseText::clean($address['street'] ?? null) ?? '');
            $this->el($end, 'nro', NfseText::clean($address['number'] ?? null) ?? 'S/N');
            $this->optional($end, 'xCpl', NfseText::clean($address['complement'] ?? null));
            $this->el($end, 'xBairro', NfseText::clean($address['district'] ?? null) ?? '');
        }

        $this->optional($node, 'fone', $person['phone'] ?? null);
        $this->optional($node, 'email', NfseText::clean($person['email'] ?? null));
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

        $trade = $service['foreign_trade'] ?? null;
        if (is_array($trade)) {
            // Os códigos "desconhecido" (0/00/9) são para nota de importação; na exportação o padrão é "nenhum/não".
            $comExt = $this->el($serv, 'comExt');
            $this->el($comExt, 'mdPrestacao', (string) $trade['provision_mode']);
            $this->el($comExt, 'vincPrest', (string) ($trade['relationship'] ?? '0'));
            $this->el($comExt, 'tpMoeda', (string) $trade['currency']);
            $this->el($comExt, 'vServMoeda', NfseText::money($trade['foreign_amount']));
            $this->el($comExt, 'mecAFComexP', (string) ($trade['provider_support'] ?? '01'));
            $this->el($comExt, 'mecAFComexT', (string) ($trade['taker_support'] ?? '01'));
            $this->el($comExt, 'movTempBens', (string) ($trade['temporary_goods'] ?? '1'));
            $this->el($comExt, 'mdic', ! empty($trade['send_to_mdic']) ? '1' : '0');
        }

        if (! empty($service['construction'])) {
            $work = $service['construction'];
            $obra = $this->el($serv, 'obra');
            $this->optional($obra, 'inscImobFisc', NfseText::clean($work['registration'] ?? null));
            match (true) {
                ! empty($work['work_code']) => $this->el($obra, 'cObra', NfseText::clean($work['work_code']) ?? ''),
                ! empty($work['cib']) => $this->el($obra, 'cCIB', strtoupper($work['cib'])),
                default => $this->simpleAddress($obra, 'end', $work['address']),
            };
        }

        if (! empty($service['event'])) {
            $event = $service['event'];
            $atv = $this->el($serv, 'atvEvento');
            $this->el($atv, 'xNome', NfseText::clean($event['name']) ?? '');
            $this->el($atv, 'dtIni', $event['starts_on']);
            $this->el($atv, 'dtFim', $event['ends_on']);
            ! empty($event['event_id'])
                ? $this->el($atv, 'idAtvEvt', NfseText::clean($event['event_id']) ?? '')
                : $this->simpleAddress($atv, 'end', $event['address']);
        }

        if (! empty($service['additional_info'])) {
            $info = $service['additional_info'];
            $compl = $this->el($serv, 'infoCompl');
            $this->optional($compl, 'idDocTec', NfseText::clean($info['technical_document'] ?? null));
            $this->optional($compl, 'docRef', NfseText::clean($info['reference'] ?? null));
            $this->optional($compl, 'xPed', NfseText::clean($info['order'] ?? null));
            if (! empty($info['order_items'])) {
                $items = $this->el($compl, 'gItemPed');
                foreach ($info['order_items'] as $item) {
                    $this->el($items, 'xItemPed', NfseText::clean($item) ?? '');
                }
            }
            $this->optional($compl, 'xInfComp', NfseText::clean($info['notes'] ?? null));
        }
    }

    /**
     * Endereço de obra, evento ou imóvel (TCEnderObraEvento / TCEnderecoSimples): só CEP no Brasil,
     * código postal + cidade + estado no exterior.
     *
     * @param  array<string, mixed>  $address
     */
    private function simpleAddress(DOMElement $parent, string $tag, array $address): DOMElement
    {
        $end = $this->el($parent, $tag);
        $country = strtoupper((string) ($address['country'] ?? ''));
        if ($country !== '' && $country !== 'BR') {
            $ext = $this->el($end, 'endExt');
            $this->el($ext, 'cEndPost', NfseText::clean((string) $address['postal_code']) ?? '');
            $this->el($ext, 'xCidade', NfseText::clean($address['city'] ?? null) ?? '');
            $this->el($ext, 'xEstProvReg', NfseText::clean($address['state'] ?? null) ?? '');
        } else {
            $this->el($end, 'CEP', (string) $address['postal_code']);
        }
        $this->el($end, 'xLgr', NfseText::clean($address['street'] ?? null) ?? '');
        $this->el($end, 'nro', NfseText::clean($address['number'] ?? null) ?? 'S/N');
        $this->optional($end, 'xCpl', NfseText::clean($address['complement'] ?? null));
        $this->el($end, 'xBairro', NfseText::clean($address['district'] ?? null) ?? '');

        return $end;
    }

    private function values(DOMElement $inf, Invoice $invoice, Issuer $issuer): void
    {
        $valores = $this->el($inf, 'valores');
        $vServPrest = $this->el($valores, 'vServPrest');
        $this->optionalMoney($vServPrest, 'vReceb', $invoice->intermediary_received);
        $this->el($vServPrest, 'vServ', NfseText::money($invoice->amount));

        if ($invoice->unconditional_discount !== null || $invoice->conditional_discount !== null) {
            $desc = $this->el($valores, 'vDescCondIncond');
            $this->optionalMoney($desc, 'vDescIncond', $invoice->unconditional_discount);
            $this->optionalMoney($desc, 'vDescCond', $invoice->conditional_discount);
        }

        if ($invoice->deduction_percent !== null || $invoice->deduction_amount !== null || ! empty($invoice->deduction_documents)) {
            $dedRed = $this->el($valores, 'vDedRed');
            match (true) {
                $invoice->deduction_percent !== null => $this->el($dedRed, 'pDR', NfseText::money($invoice->deduction_percent)),
                $invoice->deduction_amount !== null => $this->el($dedRed, 'vDR', NfseText::money($invoice->deduction_amount)),
                default => $this->deductionDocuments($dedRed, $invoice->deduction_documents),
            };
        }

        $trib = $this->el($valores, 'trib');
        $tribMun = $this->el($trib, 'tribMun');
        $this->el($tribMun, 'tribISSQN', (string) $invoice->iss_taxation);
        if ($invoice->iss_taxation === Invoice::ISS_EXPORT) {
            $this->el($tribMun, 'cPaisResult', (string) ($invoice->service['result_country'] ?? ''));
        }
        if ($invoice->iss_taxation === Invoice::ISS_IMMUNE && $invoice->immunity_type !== null) {
            $this->el($tribMun, 'tpImunidade', (string) $invoice->immunity_type);
        }
        if (! empty($invoice->iss_suspension)) {
            $susp = $this->el($tribMun, 'exigSusp');
            $this->el($susp, 'tpSusp', (string) $invoice->iss_suspension['type']);
            // O leiaute quer o número do processo com exatamente 30 dígitos.
            $this->el($susp, 'nProcesso', str_pad((string) $invoice->iss_suspension['process_number'], 30, '0', STR_PAD_LEFT));
        }
        if (! empty($invoice->municipal_benefit)) {
            $bm = $this->el($tribMun, 'BM');
            $this->el($bm, 'nBM', (string) $invoice->municipal_benefit['number']);
            $this->optionalMoney($bm, 'vRedBCBM', $invoice->municipal_benefit['reduction_amount'] ?? null);
            if (! isset($invoice->municipal_benefit['reduction_amount'])) {
                $this->optionalMoney($bm, 'pRedBCBM', $invoice->municipal_benefit['reduction_percent'] ?? null);
            }
        }
        $this->el($tribMun, 'tpRetISSQN', match (true) {
            ! $invoice->iss_withheld => self::ISS_NOT_WITHHELD,
            $invoice->iss_withholder === Invoice::WITHHOLDER_INTERMEDIARY => self::ISS_WITHHELD_BY_INTERMEDIARY,
            default => self::ISS_WITHHELD_BY_TAKER,
        });
        // A obrigatoriedade de pAliq depende do convênio do município e do regime (Anexo I); quem decide é o chamador.
        if ($invoice->iss_rate !== null) {
            $this->el($tribMun, 'pAliq', NfseText::money($invoice->iss_rate));
        }

        if (! empty($invoice->federal_taxes)) {
            $this->federalTaxes($trib, $invoice->federal_taxes);
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

    /**
     * Destinatário do IBS/CBS (TCRTCInfoDest): como a pessoa do leiaute, sem inscrição municipal e com nome de até 150.
     *
     * @param  array<string, mixed>  $person
     */
    private function recipient(DOMElement $group, array $person): void
    {
        $person['municipal_registration'] = null;
        $person['name'] = mb_substr((string) ($person['name'] ?? ''), 0, 150);
        $this->person($group, 'dest', $person);
    }

    /** @param  list<array<string, mixed>>  $items */
    private function reimbursements(DOMElement $valores, array $items): void
    {
        $group = $this->el($valores, 'gReeRepRes');

        foreach ($items as $item) {
            $doc = $this->el($group, 'documentos');
            match ($item['kind']) {
                'dfe' => (function () use ($doc, $item) {
                    $dfe = $this->el($doc, 'dFeNacional');
                    $this->el($dfe, 'tipoChaveDFe', (string) $item['dfe_type']);
                    $this->optional($dfe, 'xTipoChaveDFe', NfseText::clean($item['dfe_type_description'] ?? null));
                    $this->el($dfe, 'chaveDFe', (string) $item['access_key']);
                })(),
                'fiscal_document' => (function () use ($doc, $item) {
                    $other = $this->el($doc, 'docFiscalOutro');
                    $this->el($other, 'cMunDocFiscal', (string) $item['city_code']);
                    $this->el($other, 'nDocFiscal', NfseText::clean($item['number']) ?? '');
                    $this->el($other, 'xDocFiscal', NfseText::clean($item['description']) ?? '');
                })(),
                default => (function () use ($doc, $item) {
                    $other = $this->el($doc, 'docOutro');
                    $this->el($other, 'nDoc', NfseText::clean($item['number']) ?? '');
                    $this->el($other, 'xDoc', NfseText::clean($item['description']) ?? '');
                })(),
            };

            if (! empty($item['supplier'])) {
                $supplier = $item['supplier'];
                $fornec = $this->el($doc, 'fornec');
                $document = (string) ($supplier['document'] ?? '');
                match (true) {
                    $document !== '' => $this->el($fornec, strlen($document) === 11 ? 'CPF' : 'CNPJ', $document),
                    ! empty($supplier['nif']) => $this->el($fornec, 'NIF', NfseText::clean($supplier['nif']) ?? ''),
                    default => $this->el($fornec, 'cNaoNIF', (string) ($supplier['nif_absence_reason'] ?? '0')),
                };
                $this->el($fornec, 'xNome', mb_substr(NfseText::clean($supplier['name'] ?? null) ?? '', 0, 150));
            }

            $this->el($doc, 'dtEmiDoc', $item['issued_on']);
            $this->el($doc, 'dtCompDoc', $item['competence_on']);
            $this->el($doc, 'tpReeRepRes', (string) $item['type']);
            $this->optional($doc, 'xTpReeRepRes', NfseText::clean($item['type_description'] ?? null));
            $this->el($doc, 'vlrReeRepRes', NfseText::money($item['amount']));
        }
    }

    /** @param  array<string, mixed>  $taxes  já resolvido pelo InvoiceService */
    private function federalTaxes(DOMElement $trib, array $taxes): void
    {
        $tribFed = $this->el($trib, 'tribFed');

        if (! empty($taxes['pis_cofins'])) {
            $pc = $taxes['pis_cofins'];
            $node = $this->el($tribFed, 'piscofins');
            $this->el($node, 'CST', (string) $pc['cst']);
            $this->optionalMoney($node, 'vBCPisCofins', $pc['base'] ?? null);
            $this->optionalMoney($node, 'pAliqPis', $pc['pis_rate'] ?? null);
            $this->optionalMoney($node, 'pAliqCofins', $pc['cofins_rate'] ?? null);
            $this->optionalMoney($node, 'vPis', $pc['pis_amount'] ?? null);
            $this->optionalMoney($node, 'vCofins', $pc['cofins_amount'] ?? null);
            $this->optional($node, 'tpRetPisCofins', isset($pc['withholding']) ? (string) $pc['withholding'] : null);
        }

        $this->optionalMoney($tribFed, 'vRetCP', $taxes['inss']['amount'] ?? null);
        $this->optionalMoney($tribFed, 'vRetIRRF', $taxes['irrf']['amount'] ?? null);
        $this->optionalMoney($tribFed, 'vRetCSLL', $taxes['csll']['amount'] ?? null);
    }

    /** @param  list<array<string, mixed>>  $documents */
    private function deductionDocuments(DOMElement $dedRed, array $documents): void
    {
        $list = $this->el($dedRed, 'documentos');

        foreach ($documents as $document) {
            $doc = $this->el($list, 'docDedRed');

            match ($document['kind']) {
                Invoice::DEDUCTION_DOC_NFSE => $this->el($doc, 'chNFSe', $document['access_key']),
                Invoice::DEDUCTION_DOC_NFE => $this->el($doc, 'chNFe', $document['access_key']),
                Invoice::DEDUCTION_DOC_MUNICIPAL_NFSE => $this->municipalNfse($doc, $document),
                Invoice::DEDUCTION_DOC_PAPER => $this->paperInvoice($doc, $document),
                Invoice::DEDUCTION_DOC_FISCAL => $this->el($doc, 'nDocFisc', NfseText::clean($document['number']) ?? ''),
                default => $this->el($doc, 'nDoc', NfseText::clean($document['number']) ?? ''),
            };

            $this->el($doc, 'tpDedRed', (string) $document['reason']);
            $this->optional($doc, 'xDescOutDed', NfseText::clean($document['reason_description'] ?? null));
            $this->el($doc, 'dtEmiDoc', $document['issued_on']);
            $this->el($doc, 'vDedutivelRedutivel', NfseText::money($document['deductible_amount']));
            $this->el($doc, 'vDeducaoReducao', NfseText::money($document['amount']));

            if (! empty($document['supplier'])) {
                $this->person($doc, 'fornec', $document['supplier']);
            }
        }
    }

    /** NFS-e do padrão anterior: o leiaute quer o número com exatamente 15 dígitos. */
    private function municipalNfse(DOMElement $doc, array $document): DOMElement
    {
        $group = $this->el($doc, 'NFSeMun');
        $this->el($group, 'cMunNFSeMun', $document['city_code']);
        $this->el($group, 'nNFSeMun', str_pad($document['number'], 15, '0', STR_PAD_LEFT));
        $this->el($group, 'cVerifNFSeMun', $document['verification_code']);

        return $group;
    }

    /** NF/NFS em papel: número com 7 dígitos e modelo com 15, completados com zeros como o XSD exige. */
    private function paperInvoice(DOMElement $doc, array $document): DOMElement
    {
        $group = $this->el($doc, 'NFNFS');
        $this->el($group, 'nNFS', str_pad($document['number'], 7, '0', STR_PAD_LEFT));
        $this->el($group, 'modNFS', str_pad($document['model'], 15, '0', STR_PAD_LEFT));
        $this->el($group, 'serieNFS', $document['series']);

        return $group;
    }

    /**
     * Grupo declaratório do IBS/CBS (LC 214/2025). A DPS só classifica a operação; base, alíquotas
     * e valores são calculados pela Sefin e voltam na NFS-e.
     *
     * @param  array{cst: string, class_code: string, operation_code: string, final_consumer?: bool|null}  $codes
     */
    private function ibsCbs(DOMElement $inf, array $codes): void
    {
        $group = $this->el($inf, 'IBSCBS');
        $this->el($group, 'finNFSe', self::FIN_NFSE_REGULAR);
        if (isset($codes['final_consumer'])) {
            $this->el($group, 'indFinal', $codes['final_consumer'] ? '1' : '0');
        }
        $this->el($group, 'cIndOp', $codes['operation_code']);
        $this->optional($group, 'tpOper', isset($codes['operation_type']) ? (string) $codes['operation_type'] : null);
        if (! empty($codes['referenced_invoices'])) {
            $refs = $this->el($group, 'gRefNFSe');
            foreach ($codes['referenced_invoices'] as $key) {
                $this->el($refs, 'refNFSe', (string) $key);
            }
        }
        $this->optional($group, 'tpEnteGov', isset($codes['government_entity']) ? (string) $codes['government_entity'] : null);

        // O destinatário é o próprio tomador, salvo quando a nota informa outro (grupo dest).
        $recipient = $codes['recipient'] ?? null;
        $this->el($group, 'indDest', empty($recipient) ? self::IND_DEST_TAKER : self::IND_DEST_OTHER);
        if (! empty($recipient)) {
            $this->recipient($group, $recipient);
        }

        if (! empty($codes['property'])) {
            $property = $codes['property'];
            $imovel = $this->el($group, 'imovel');
            $this->optional($imovel, 'inscImobFisc', NfseText::clean($property['registration'] ?? null));
            ! empty($property['cib'])
                ? $this->el($imovel, 'cCIB', strtoupper($property['cib']))
                : $this->simpleAddress($imovel, 'end', $property['address']);
        }

        $valores = $this->el($group, 'valores');
        if (! empty($codes['reimbursements'])) {
            $this->reimbursements($valores, $codes['reimbursements']);
        }
        $trib = $this->el($valores, 'trib');
        $gIBSCBS = $this->el($trib, 'gIBSCBS');
        $this->el($gIBSCBS, 'CST', $codes['cst']);
        $this->el($gIBSCBS, 'cClassTrib', $codes['class_code']);
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

    private function optionalMoney(DOMElement $parent, string $name, string|float|null $value): void
    {
        if ($value !== null) {
            $this->el($parent, $name, NfseText::money($value));
        }
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
