<?php

namespace App\Modules\Invoices\Services\Danfse;

use App\Modules\Invoices\Services\Nfse\DpsBuilder;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

/**
 * Conteúdo do DANFSe extraído do XML da NFS-e, já formatado para impressão (NT 008/2026, item 2.4.5).
 * A NT proíbe imprimir o que não está no XML: campo ausente vira "-" (nota 12).
 */
final class DanfseData
{
    public const EMPTY = '-';

    public const QR_BASE_URL = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';

    private const UF_BY_IBGE = [
        '11' => 'RO', '12' => 'AC', '13' => 'AM', '14' => 'RR', '15' => 'PA', '16' => 'AP', '17' => 'TO',
        '21' => 'MA', '22' => 'PI', '23' => 'CE', '24' => 'RN', '25' => 'PB', '26' => 'PE', '27' => 'AL', '28' => 'SE', '29' => 'BA',
        '31' => 'MG', '32' => 'ES', '33' => 'RJ', '35' => 'SP',
        '41' => 'PR', '42' => 'SC', '43' => 'RS',
        '50' => 'MS', '51' => 'MT', '52' => 'GO', '53' => 'DF',
    ];

    private const EMITTER = ['1' => 'Prestador', '2' => 'Tomador', '3' => 'Intermediário'];

    private const STATUS = [
        '100' => 'NFS-e Gerada',
        '101' => 'NFS-e de Substituição Gerada',
        '102' => 'NFS-e de Decisão Judicial ou Administrativa',
        '103' => 'NFS-e Avulsa',
    ];

    private const FINALITY = ['0' => 'NFS-e regular'];

    private const SIMPLES = [
        '1' => 'Não Optante',
        '2' => 'Optante - Microempreendedor Individual (MEI)',
        '3' => 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)',
    ];

    private const SIMPLES_REGIME = [
        '1' => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
        '2' => 'Regime de apuração dos tributos federais pelo SN e o ISSQN pela NFS-e conforme respectiva legislação municipal do tributo',
        '3' => 'Regime de apuração dos tributos federais e municipal pela NFS-e conforme respectivas legislações federal e municipal de cada tributo',
    ];

    private const ISS_TAXATION = ['1' => 'Operação Tributável', '2' => 'Imunidade', '3' => 'Exportação de Serviço', '4' => 'Não Incidência'];

    private const SPECIAL_REGIME = [
        '0' => 'Nenhum', '1' => 'Ato Cooperado (Cooperativa)', '2' => 'Estimativa', '3' => 'Microempresa Municipal',
        '4' => 'Notário ou Registrador', '5' => 'Profissional Autônomo', '6' => 'Sociedade de Profissionais', '9' => 'Outros',
    ];

    private const IMMUNITY = [
        '0' => 'Imunidade (tipo não informado)',
        '1' => 'Patrimônio, renda ou serviços, uns dos outros',
        '2' => 'Templos de qualquer culto',
        '3' => 'Patrimônio, renda ou serviços dos partidos políticos, sindicatos e instituições de educação e assistência social',
        '4' => 'Livros, jornais, periódicos e o papel destinado a sua impressão',
        '5' => 'Fonogramas e videofonogramas musicais produzidos no Brasil',
    ];

    private const SUSPENSION = ['1' => 'Exigibilidade Suspensa por Decisão Judicial', '2' => 'Exigibilidade Suspensa por Processo Administrativo'];

    private const BENEFIT = ['1' => 'Isenção', '2' => 'Redução da BC em %', '3' => 'Redução da BC em R$', '4' => 'Alíquota Diferenciada'];

    private const ISS_WITHHOLDING = ['1' => 'Não Retido', '2' => 'Retido pelo Tomador', '3' => 'Retido pelo Intermediário'];

    private const PIS_COFINS_WITHHOLDING = [
        '0' => 'PIS/COFINS/CSLL Não Retidos',
        '1' => 'PIS/COFINS Retido',
        '2' => 'PIS/COFINS Não Retido',
        '3' => 'PIS/COFINS/CSLL Retidos',
        '4' => 'PIS/COFINS Retidos, CSLL Não Retido',
        '5' => 'PIS Retido, COFINS/CSLL Não Retido',
        '6' => 'COFINS Retido, PIS/CSLL Não Retido',
        '7' => 'PIS Não Retido, COFINS/CSLL Retidos',
        '8' => 'PIS/COFINS Não Retidos, CSLL Retido',
        '9' => 'COFINS Não Retido, PIS/CSLL Retidos',
    ];

    /** @param  array<string, mixed>  $fields */
    private function __construct(private array $fields) {}

    public static function fromNfseXml(string $xml, bool $cancelled = false, bool $replaced = false): self
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new InvalidArgumentException('XML da NFS-e inválido.');
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('n', DpsBuilder::NS);

        if ($xpath->query('//n:infNFSe')->length === 0) {
            throw new InvalidArgumentException('O XML não contém uma NFS-e (infNFSe).');
        }

        return new self((new self([]))->extract($xpath, $cancelled, $replaced));
    }

    public function __get(string $name): mixed
    {
        return $this->fields[$name] ?? null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->fields;
    }

    /** @return array<string, mixed> */
    private function extract(DOMXPath $xp, bool $cancelled, bool $replaced): array
    {
        $v = fn (string $path): ?string => $this->value($xp, '//n:infNFSe/'.$path);
        $dps = fn (string $path): ?string => $v('n:DPS/n:infDPS/'.$path);

        $accessKey = preg_replace('/^NFS/', '', (string) $xp->evaluate('string(//n:infNFSe/@Id)'));
        $restricted = $dps('n:tpAmb') === '2';
        $issuerCityCode = $dps('n:cLocEmi') ?? $v('n:emit/n:enderNac/n:cMun');
        $cityNames = array_filter([
            $issuerCityCode => $v('n:xLocEmi'),
            (string) $dps('n:serv/n:locPrest/n:cLocPrestacao') => $v('n:xLocPrestacao'),
            (string) $v('n:cLocIncid') => $v('n:xLocIncid'),
        ]);

        return [
            'restricted' => $restricted,
            'cancelled' => $cancelled,
            'replaced' => $replaced,
            'header_city' => $v('n:xLocEmi') ? 'Município: '.$v('n:xLocEmi').' / '.($v('n:emit/n:enderNac/n:UF') ?? $this->uf($issuerCityCode)) : self::EMPTY,
            'header_generator' => match ($v('n:ambGer')) {
                '1' => 'Ambiente Gerador: Prefeitura',
                '2' => 'Ambiente Gerador: Sefin Nacional NFS-e',
                default => self::EMPTY,
            },
            'header_environment' => $restricted ? 'Tipo de Ambiente: Produção Restrita' : 'Tipo de Ambiente: Produção',

            'access_key' => $accessKey !== '' ? $accessKey : self::EMPTY,
            'qr_url' => self::QR_BASE_URL.$accessKey,
            'number' => $v('n:nNFSe') ?? self::EMPTY,
            'competence' => $this->date($dps('n:dCompet'), 'd/m/Y'),
            'issued_at' => $this->date($v('n:dhProc'), 'd/m/Y H:i:s'),
            'dps_number' => $dps('n:nDPS') ?? self::EMPTY,
            'dps_series' => $dps('n:serie') ?? self::EMPTY,
            'dps_emitted_at' => $this->date($dps('n:dhEmi'), 'd/m/Y H:i:s'),
            'emitter' => self::EMITTER[$dps('n:tpEmit')] ?? self::EMPTY,
            'status' => $this->limit(self::STATUS[$v('n:cStat')] ?? ($v('n:cStat') ?? self::EMPTY), 40),
            'finality' => self::FINALITY[$dps('n:IBSCBS/n:finNFSe') ?? ''] ?? self::EMPTY,

            'provider' => $this->provider($xp, $dps, $cityNames),
            'taker' => $this->party($xp, '//n:infNFSe/n:DPS/n:infDPS/n:toma', $cityNames),
            'intermediary' => $this->party($xp, '//n:infNFSe/n:DPS/n:infDPS/n:interm', $cityNames),

            'service' => [
                'code' => $this->taxCode($dps('n:serv/n:cServ/n:cTribNac'), $dps('n:serv/n:cServ/n:cTribMun')),
                'nbs' => $this->nbs($dps('n:serv/n:cServ/n:cNBS')),
                'location' => $this->place($v('n:xLocPrestacao'), $dps('n:serv/n:locPrest/n:cLocPrestacao'), $dps('n:serv/n:locPrest/n:cPaisPrestacao')),
                'code_description' => $this->limit($v('n:xTribMun') ?? $v('n:xTribNac') ?? self::EMPTY, 170),
                'description' => $this->limit($dps('n:serv/n:cServ/n:xDescServ') ?? self::EMPTY, 1300),
            ],

            'iss' => [
                'subject' => $dps('n:valores/n:trib/n:tribMun/n:tribISSQN') !== null,
                'taxation' => self::ISS_TAXATION[$dps('n:valores/n:trib/n:tribMun/n:tribISSQN')] ?? self::EMPTY,
                'incidence' => $this->place($v('n:xLocIncid'), $v('n:cLocIncid'), $dps('n:valores/n:trib/n:tribMun/n:cPaisResult')),
                'special_regime' => self::SPECIAL_REGIME[$dps('n:prest/n:regTrib/n:regEspTrib')] ?? self::EMPTY,
                'immunity' => $this->limit(self::IMMUNITY[$dps('n:valores/n:trib/n:tribMun/n:tpImunidade')] ?? self::EMPTY, 40),
                'suspension' => $this->limit(self::SUSPENSION[$dps('n:valores/n:trib/n:tribMun/n:exigSusp/n:tpSusp')] ?? self::EMPTY, 40),
                'suspension_process' => $dps('n:valores/n:trib/n:tribMun/n:exigSusp/n:nProcesso') ?? self::EMPTY,
                'benefit' => self::BENEFIT[$v('n:valores/n:tpBM') ?? ''] ?? self::EMPTY,
                'benefit_amount' => $this->money($v('n:valores/n:vCalcBM') ?? $dps('n:valores/n:trib/n:tribMun/n:BM/n:vRedBCBM')),
                'deductions' => $this->money($dps('n:valores/n:vDedRed/n:vDR') ?? $v('n:valores/n:vCalcDR')),
                'unconditional_discount' => $this->money($dps('n:valores/n:vDescCondIncond/n:vDescIncond')),
                'base' => $this->money($v('n:valores/n:vBC')),
                'rate' => $this->percent($v('n:valores/n:pAliqAplic')),
                'withholding' => self::ISS_WITHHOLDING[$dps('n:valores/n:trib/n:tribMun/n:tpRetISSQN')] ?? self::EMPTY,
                'amount' => $this->money($v('n:valores/n:vISSQN')),
            ],

            'federal' => $this->federal($dps),
            'ibscbs' => $this->ibscbs($xp),

            'totals' => [
                'service' => $this->money($dps('n:valores/n:vServPrest/n:vServ')),
                'unconditional_discount' => $this->money($dps('n:valores/n:vDescCondIncond/n:vDescIncond')),
                'conditional_discount' => $this->money($dps('n:valores/n:vDescCondIncond/n:vDescCond')),
                'withholdings' => $this->money($v('n:valores/n:vTotalRet')),
                'net' => $this->money($v('n:valores/n:vLiq')),
                'ibscbs' => $this->sumMoney($v('n:IBSCBS/n:totCIBS/n:gIBS/n:vIBSTot'), $v('n:IBSCBS/n:totCIBS/n:gCBS/n:vCBS')),
                'net_with_ibscbs' => $this->money($v('n:IBSCBS/n:totCIBS/n:vTotNF')),
            ],

            'complementary' => $this->complementary($v, $dps),

            'stub_key' => ($v('n:nNFSe') ?? self::EMPTY).' / '.$accessKey,
        ];
    }

    /** @param  array<string, string>  $cityNames */
    private function provider(DOMXPath $xp, callable $dps, array $cityNames): array
    {
        // Com tpEmit=1 a DPS não traz nome nem endereço do prestador; a Sefin devolve esses dados no grupo emit.
        $party = $this->party($xp, '//n:infNFSe/n:DPS/n:infDPS/n:prest', $cityNames) ?? [];
        $emit = $this->party($xp, '//n:infNFSe/n:emit', $cityNames, 'n:enderNac') ?? [];

        $merged = [];
        foreach (['document', 'municipal_registration', 'phone', 'name', 'city', 'ibge_cep', 'address', 'email'] as $key) {
            $own = $party[$key] ?? self::EMPTY;
            $merged[$key] = $own !== self::EMPTY ? $own : ($emit[$key] ?? self::EMPTY);
        }

        $merged['simples'] = $this->limit(self::SIMPLES[$dps('n:prest/n:regTrib/n:opSimpNac')] ?? self::EMPTY, 40);
        $merged['simples_regime'] = $this->limit(self::SIMPLES_REGIME[$dps('n:prest/n:regTrib/n:regApTribSN')] ?? self::EMPTY, 80);

        return $merged;
    }

    /**
     * Prestador, tomador e intermediário têm a mesma estrutura no leiaute; o emit usa enderNac plano.
     *
     * @param  array<string, string>  $cityNames
     * @return array<string, string>|null
     */
    private function party(DOMXPath $xp, string $base, array $cityNames, ?string $flatAddress = null): ?array
    {
        if ($xp->query($base)->length === 0) {
            return null;
        }

        $v = fn (string $path): ?string => $this->value($xp, $base.'/'.$path);
        $address = $flatAddress ?? 'n:end';
        $nac = $flatAddress ?? 'n:end/n:endNac';

        $cityCode = $v($nac.'/n:cMun');
        $postal = $v($nac.'/n:CEP');
        $foreignCity = $v('n:end/n:endExt/n:xCidade');
        $uf = $v($nac.'/n:UF') ?? $this->uf($cityCode);

        $city = match (true) {
            $foreignCity !== null => $foreignCity,
            $cityCode !== null && isset($cityNames[$cityCode]) => $cityNames[$cityCode].' / '.$uf,
            // O XML só traz o código IBGE; sem tabela de municípios, o nome fica em branco e a UF sai do código.
            $cityCode !== null => self::EMPTY.' / '.($uf ?? self::EMPTY),
            default => self::EMPTY,
        };

        $street = array_filter([
            $v($address.'/n:xLgr'), $v($address.'/n:nro'), $v($address.'/n:xCpl'), $v($address.'/n:xBairro'),
        ]);

        return [
            'document' => $this->document($v('n:CNPJ'), $v('n:CPF'), $v('n:NIF')),
            'municipal_registration' => $v('n:IM') ?? self::EMPTY,
            'phone' => $this->phone($v('n:fone')),
            'name' => $this->limit($v('n:xNome') ?? self::EMPTY, 80),
            'city' => $this->limit($city, 37),
            'ibge_cep' => $cityCode !== null || $postal !== null
                ? trim(($cityCode ?? '').' / '.$this->postalCode($postal ?? $v('n:end/n:endExt/n:cEndPost')), ' /')
                : self::EMPTY,
            'address' => $street === [] ? self::EMPTY : $this->limit(implode(', ', $street), 80),
            'email' => $this->limit($v('n:email') ?? self::EMPTY, 80),
        ];
    }

    private function federal(callable $dps): array
    {
        $pis = $dps('n:valores/n:trib/n:tribFed/n:piscofins/n:vPis');
        $cofins = $dps('n:valores/n:trib/n:tribFed/n:piscofins/n:vCofins');
        $csll = $dps('n:valores/n:trib/n:tribFed/n:vRetCSLL');
        $withheld = $dps('n:valores/n:trib/n:tribFed/n:piscofins/n:tpRetPisCofins');

        return [
            'irrf' => $this->money($dps('n:valores/n:trib/n:tribFed/n:vRetIRRF')),
            'social_security' => $this->money($dps('n:valores/n:trib/n:tribFed/n:vRetCP')),
            // tpRetPisCofins = 1: PIS e COFINS foram retidos e somam nas contribuições retidas, não no débito próprio.
            'social_contributions' => $withheld === '1' ? $this->sumMoney($csll, $pis, $cofins) : $this->money($csll),
            'pis' => $withheld === '1' ? $this->money('0') : $this->money($pis),
            'cofins' => $withheld === '1' ? $this->money('0') : $this->money($cofins),
            'withholding_description' => self::PIS_COFINS_WITHHOLDING[$withheld ?? ''] ?? self::EMPTY,
        ];
    }

    private function ibscbs(DOMXPath $xp): array
    {
        $v = fn (string $path): ?string => $this->value($xp, '//n:infNFSe/'.$path);
        $dps = fn (string $path): ?string => $v('n:DPS/n:infDPS/n:IBSCBS/'.$path);

        $cst = $dps('n:valores/n:trib/n:gIBSCBS/n:CST');
        $class = $dps('n:valores/n:trib/n:gIBSCBS/n:cClassTrib');
        $incidence = array_filter([$dps('n:cIndOp'), $v('n:IBSCBS/n:cLocalidadeIncid'), $v('n:IBSCBS/n:xLocalidadeIncid')]);
        if ($v('n:IBSCBS/n:cLocalidadeIncid') !== null) {
            $incidence[] = $this->uf($v('n:IBSCBS/n:cLocalidadeIncid'));
        }

        $rates = fn (?string ...$values) => array_filter($values, fn ($x) => $x !== null) === []
            ? self::EMPTY
            : implode(' / ', array_map(fn ($x) => $x === null ? self::EMPTY : $this->percent($x), $values));

        return [
            'cst_class' => $cst !== null || $class !== null ? ($cst ?? self::EMPTY).' / '.($class ?? self::EMPTY) : self::EMPTY,
            'incidence' => $incidence === [] ? self::EMPTY : $this->limit(implode(' / ', array_filter($incidence)), 56),
            'exclusions' => $this->sumMoney(
                $v('n:DPS/n:infDPS/n:valores/n:vDescCondIncond/n:vDescIncond'),
                $v('n:IBSCBS/n:valores/n:vCalcReeRepRes'),
                $v('n:IBSCBS') !== null ? $v('n:valores/n:vISSQN') : null,
                $v('n:IBSCBS') !== null ? $v('n:DPS/n:infDPS/n:valores/n:trib/n:tribFed/n:piscofins/n:vPis') : null,
                $v('n:IBSCBS') !== null ? $v('n:DPS/n:infDPS/n:valores/n:trib/n:tribFed/n:piscofins/n:vCofins') : null,
            ),
            'base' => $this->money($v('n:IBSCBS/n:valores/n:vBC')),
            'rate_reductions' => $rates($v('n:IBSCBS/n:valores/n:uf/n:pRedAliqUF'), $v('n:IBSCBS/n:valores/n:mun/n:pRedAliqMun'), $v('n:IBSCBS/n:valores/n:fed/n:pRedAliqCBS')),
            'ibs_rates' => $rates($v('n:IBSCBS/n:valores/n:uf/n:pIBSUF'), $v('n:IBSCBS/n:valores/n:mun/n:pIBSMun')),
            'ibs_mun_effective' => $this->percent($v('n:IBSCBS/n:valores/n:mun/n:pAliqEfetMun')),
            'ibs_mun_amount' => $this->money($v('n:IBSCBS/n:totCIBS/n:gIBS/n:gIBSMunTot/n:vIBSMun')),
            'ibs_uf_effective' => $this->percent($v('n:IBSCBS/n:valores/n:uf/n:pAliqEfetUF')),
            'ibs_uf_amount' => $this->money($v('n:IBSCBS/n:totCIBS/n:gIBS/n:gIBSUFTot/n:vIBSUF')),
            'ibs_total' => $this->money($v('n:IBSCBS/n:totCIBS/n:gIBS/n:vIBSTot')),
            'cbs_rate' => $this->percent($v('n:IBSCBS/n:valores/n:fed/n:pCBS')),
            'cbs_effective' => $this->percent($v('n:IBSCBS/n:valores/n:fed/n:pAliqEfetCBS')),
            'cbs_total' => $this->money($v('n:IBSCBS/n:totCIBS/n:gCBS/n:vCBS')),
        ];
    }

    private function complementary(callable $v, callable $dps): string
    {
        $parts = array_filter([
            $this->labelled('Inf. Cont.:', $dps('n:serv/n:infoCompl/n:xInfComp')),
            $this->labelled('NFS-e Subst.:', $dps('n:subst/n:chSubstda')),
            $this->labelled('Doc. Ref.:', $dps('n:serv/n:infoCompl/n:docRef')),
            $this->labelled('Cod. Obra:', $dps('n:serv/n:obra/n:cObra')),
            $this->labelled('Insc. Imob.:', $dps('n:IBSCBS/n:imovel/n:inscImobFisc') ?? $dps('n:serv/n:obra/n:inscImobFisc')),
            $this->labelled('Cod. Evt.:', $dps('n:serv/n:atvEvento/n:idAtvEvt')),
            $this->labelled('Doc. Tec.:', $dps('n:serv/n:infoCompl/n:idDocTec')),
            $this->labelled('Núm. Ped.:', $dps('n:serv/n:infoCompl/n:xPed')),
            $this->labelled('Item Ped.:', $dps('n:serv/n:infoCompl/n:gItemPed/n:xItemPed')),
            $this->labelled('Inf. A. T. Mun.:', $v('n:xOutInf')),
        ]);

        $text = $this->limit(implode(' | ', $parts), 1997);

        // A linha dos tributos aproximados é obrigatória e fica fora do limite de caracteres (nota 10).
        return ($text !== '' ? $text.' | ' : '').$this->approximateTaxes($dps);
    }

    private function approximateTaxes(callable $dps): string
    {
        $prefix = 'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: ';
        $base = 'n:valores/n:trib/n:totTrib/';

        if ($dps($base.'n:vTotTrib/n:vTotTribFed') !== null) {
            return $prefix.sprintf(
                'Federais: %s ; Estaduais: %s ; Municipais: %s',
                $this->money($dps($base.'n:vTotTrib/n:vTotTribFed')),
                $this->money($dps($base.'n:vTotTrib/n:vTotTribEst')),
                $this->money($dps($base.'n:vTotTrib/n:vTotTribMun')),
            );
        }

        if ($dps($base.'n:pTotTrib/n:pTotTribFed') !== null) {
            return $prefix.sprintf(
                'Federais: %s ; Estaduais: %s ; Municipais: %s',
                $this->percent($dps($base.'n:pTotTrib/n:pTotTribFed')),
                $this->percent($dps($base.'n:pTotTrib/n:pTotTribEst')),
                $this->percent($dps($base.'n:pTotTrib/n:pTotTribMun')),
            );
        }

        if ($dps($base.'n:pTotTribSN') !== null) {
            return $prefix.'Simples Nacional: '.$this->percent($dps($base.'n:pTotTribSN'));
        }

        return $prefix.'Não informados (Decreto nº 8.264/2014)';
    }

    private function value(DOMXPath $xp, string $path): ?string
    {
        $node = $xp->query($path)->item(0);
        $text = $node === null ? '' : trim($node->textContent);

        return $text === '' ? null : $text;
    }

    private function labelled(string $label, ?string $value): ?string
    {
        return $value === null ? null : $label.' '.$value;
    }

    private function uf(?string $ibgeCode): ?string
    {
        return $ibgeCode === null ? null : (self::UF_BY_IBGE[substr($ibgeCode, 0, 2)] ?? null);
    }

    private function place(?string $name, ?string $code, ?string $country): string
    {
        if ($name === null && $code === null && $country === null) {
            return self::EMPTY;
        }

        return $this->limit(implode(' / ', array_filter([$name ?? $code, $this->uf($code), $country])), 42);
    }

    private function taxCode(?string $national, ?string $municipal): string
    {
        if ($national === null) {
            return self::EMPTY;
        }

        $formatted = preg_replace('/^(\d{2})(\d{2})(\d{2})$/', '$1.$2.$3', $national);

        return $formatted.' / '.($municipal ?? self::EMPTY);
    }

    private function nbs(?string $code): string
    {
        return $code === null ? self::EMPTY : (preg_replace('/^(\d)(\d{4})(\d{2})(\d{2})$/', '$1.$2.$3.$4', $code) ?? $code);
    }

    private function document(?string $cnpj, ?string $cpf, ?string $nif): string
    {
        return match (true) {
            $cnpj !== null => preg_replace('/^(\w{2})(\w{3})(\w{3})(\w{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj) ?? $cnpj,
            $cpf !== null => preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cpf) ?? $cpf,
            $nif !== null => $nif,
            default => self::EMPTY,
        };
    }

    private function postalCode(?string $cep): string
    {
        if ($cep === null) {
            return self::EMPTY;
        }

        // O XML às vezes traz o CEP sem o zero à esquerda (ex.: 1311927 em São Paulo).
        $digits = ctype_digit($cep) && strlen($cep) < 8 ? str_pad($cep, 8, '0', STR_PAD_LEFT) : $cep;

        return preg_replace('/^(\d{2})(\d{3})(\d{3})$/', '$1.$2-$3', $digits) ?? $digits;
    }

    private function phone(?string $phone): string
    {
        if ($phone === null) {
            return self::EMPTY;
        }

        return match (strlen($phone)) {
            11 => preg_replace('/^(\d{2})(\d{5})(\d{4})$/', '($1) $2-$3', $phone),
            10 => preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '($1) $2-$3', $phone),
            default => $phone,
        } ?? $phone;
    }

    private function date(?string $value, string $format): string
    {
        if ($value === null) {
            return self::EMPTY;
        }

        // Mantém o fuso do próprio XML: a NFS-e é impressa com o horário em que foi gerada.
        return CarbonImmutable::parse($value)->format($format);
    }

    private function money(?string $value): string
    {
        return $value === null ? self::EMPTY : 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    private function sumMoney(?string ...$values): string
    {
        $present = array_filter($values, fn ($x) => $x !== null);

        return $present === [] ? self::EMPTY : $this->money((string) array_sum(array_map('floatval', $present)));
    }

    private function percent(?string $value): string
    {
        return $value === null ? self::EMPTY : number_format((float) $value, 2, ',', '.').'%';
    }

    private function limit(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 3)).'...' : $text;
    }
}
