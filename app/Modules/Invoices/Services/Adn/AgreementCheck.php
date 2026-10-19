<?php

namespace App\Modules\Invoices\Services\Adn;

/**
 * Resposta de GET {adn}/parametrizacao/{município}/convenio. Validado contra o ADN real em 19/10/2026:
 * 200 com os indicadores, 404 "ainda não está ativo no Sistema Nacional" para convênio inativo e
 * 404 "Convênio para o município não encontrado" para município sem convênio.
 */
final readonly class AgreementCheck
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const NOT_FOUND = 'not_found';

    /** Rede, timeout ou erro do ADN: não diz nada sobre o município. */
    public const UNKNOWN = 'unknown';

    public function __construct(
        public string $status,
        public ?bool $nationalEnvironment = null,
        /** Município permite os emissores públicos nacionais: é o que habilita emitir pela Sefin Nacional. */
        public ?bool $nationalEmitter = null,
        public ?bool $rfbStandardIssuance = null,
        public ?bool $nationalAssessment = null,
        public ?bool $creditUse = null,
        public ?string $message = null,
    ) {}

    public static function fromResponse(int $status, ?array $body): self
    {
        $message = is_string($body['mensagem'] ?? null) ? trim($body['mensagem']) : null;
        $params = $body['parametrosConvenio'] ?? null;

        if ($status === 200 && is_array($params)) {
            $flag = fn (string $key) => array_key_exists($key, $params) && $params[$key] !== null ? (bool) $params[$key] : null;

            return new self(
                status: self::ACTIVE,
                nationalEnvironment: $flag('aderenteAmbienteNacional'),
                nationalEmitter: $flag('aderenteEmissorNacional'),
                rfbStandardIssuance: $flag('situacaoEmissaoPadraoContribuintesRFB'),
                nationalAssessment: $flag('aderenteMAN'),
                // O ADN escreve "Aproveitameto", sem o n.
                creditUse: $flag('permiteAproveitametoDeCreditos') ?? $flag('permiteAproveitamentoDeCreditos'),
                message: $message,
            );
        }

        if ($status === 404) {
            $inactive = $message !== null && str_contains(mb_strtolower($message), 'não está ativo');

            return new self($inactive ? self::INACTIVE : self::NOT_FOUND, message: $message);
        }

        return self::unknown($message ?? "HTTP {$status}");
    }

    public static function unknown(?string $message = null): self
    {
        return new self(self::UNKNOWN, message: $message);
    }

    public function isKnown(): bool
    {
        return $this->status !== self::UNKNOWN;
    }
}
