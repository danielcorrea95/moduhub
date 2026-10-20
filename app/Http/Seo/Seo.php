<?php

namespace App\Http\Seo;

/**
 * Meta tags de uma página pública. Vão no HTML pelo Blade (resources/views/app.blade.php), então
 * buscadores e robôs de IA leem mesmo sem executar JavaScript e mesmo com o SSR fora do ar.
 */
final readonly class Seo
{
    public const SITE_NAME = 'Moduhub';

    /**
     * @param  string  $title  sem o " - Moduhub", que o Blade e o Inertia acrescentam
     * @param  list<array<string, mixed>>  $jsonLd  blocos schema.org
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $path,
        public array $jsonLd = [],
        public string $image = '/og/moduhub.png',
        public bool $index = true,
    ) {}

    public function fullTitle(): string
    {
        return "{$this->title} - ".self::SITE_NAME;
    }

    public function canonical(): string
    {
        return url($this->path);
    }

    public function imageUrl(): string
    {
        return url($this->image);
    }

    /** JSON-LD pronto para <script>, com @graph quando há mais de um bloco. */
    public function jsonLdScript(): ?string
    {
        if ($this->jsonLd === []) {
            return null;
        }

        $data = count($this->jsonLd) === 1
            ? ['@context' => 'https://schema.org', ...$this->jsonLd[0]]
            : ['@context' => 'https://schema.org', '@graph' => $this->jsonLd];

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
