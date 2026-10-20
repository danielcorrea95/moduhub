<?php

namespace App\Platform\Dashboard;

/** Algo que o cliente precisa resolver, com o link de onde resolver. */
final readonly class DashboardAlert
{
    public const DANGER = 'danger';

    public const WARNING = 'warning';

    public function __construct(
        public string $tone,
        public string $title,
        public ?string $description = null,
        public ?string $href = null,
        public ?string $action = null,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'tone' => $this->tone,
            'title' => $this->title,
            'description' => $this->description,
            'href' => $this->href,
            'action' => $this->action,
        ];
    }
}
