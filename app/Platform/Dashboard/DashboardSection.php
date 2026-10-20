<?php

namespace App\Platform\Dashboard;

/**
 * O que um módulo mostra na tela inicial: os dados do card, o que ele cobra além do plano no ciclo
 * (excedente) e o que precisa de atenção.
 */
final readonly class DashboardSection
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{label: string, cents: int}>  $charges
     * @param  list<DashboardAlert>  $alerts
     */
    public function __construct(
        public string $key,
        public array $data,
        public array $charges = [],
        public array $alerts = [],
    ) {}
}
