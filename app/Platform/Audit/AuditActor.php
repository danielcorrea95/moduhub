<?php

namespace App\Platform\Audit;

/**
 * Quem disparou uma ação auditada: um usuário do painel, uma chave de API ou o próprio sistema
 * (job, comando agendado).
 */
final readonly class AuditActor
{
    public const TYPE_USER = 'user';

    public const TYPE_API_KEY = 'api_key';

    public const TYPE_SYSTEM = 'system';

    public function __construct(
        public string $type,
        public ?string $id,
        public ?string $label,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    /** @return array{actor_type: string, actor_id: ?string, actor_label: ?string, ip_address: ?string, user_agent: ?string} */
    public function toAttributes(): array
    {
        return [
            'actor_type' => $this->type,
            'actor_id' => $this->id,
            'actor_label' => $this->label,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent !== null ? mb_substr($this->userAgent, 0, 255) : null,
        ];
    }
}
