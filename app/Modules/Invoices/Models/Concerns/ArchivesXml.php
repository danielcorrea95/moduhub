<?php

namespace App\Modules\Invoices\Models\Concerns;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * XMLs fiscais ficam no disco "fiscal" (S3 em produção); a tabela guarda só o caminho.
 * O atributo continua sendo lido e gravado como antes ($invoice->nfse_xml): a leitura busca
 * no disco na primeira vez, e o conteúdo novo sobe no saving, ainda dentro da transação de quem salva.
 * Se o upload falha, o save falha junto e nenhuma linha aponta para arquivo que não existe.
 * Um rollback depois do upload deixa um arquivo órfão, o que é aceitável para um arquivo fiscal.
 *
 * Arquivo antigo nunca é apagado: substituir ou anular o atributo só troca o caminho na linha.
 */
trait ArchivesXml
{
    public const XML_DISK = 'fiscal';

    /** @var array<string, string|null> conteúdo atribuído e ainda não gravado */
    private array $pendingXml = [];

    /** @var array<string, string> conteúdo já lido do disco */
    private array $loadedXml = [];

    /** @return array<string, string> atributo => coluna com o caminho no disco fiscal */
    abstract protected function archivedXml(): array;

    public static function bootArchivesXml(): void
    {
        static::saving(fn (self $model) => $model->writePendingXml());
    }

    public function getAttribute($key)
    {
        if (is_string($key) && array_key_exists($key, $this->archivedXml())) {
            return $this->readArchivedXml($key);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (is_string($key) && array_key_exists($key, $this->archivedXml())) {
            $this->pendingXml[$key] = $value === null ? null : (string) $value;
            unset($this->loadedXml[$key]);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /** Recarga do banco (hydrate, refresh) descarta o que estava em memória. */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->pendingXml = [];
        $this->loadedXml = [];

        return parent::setRawAttributes($attributes, $sync);
    }

    public function hasArchivedXml(string $key): bool
    {
        if (array_key_exists($key, $this->pendingXml)) {
            return $this->pendingXml[$key] !== null;
        }

        return ($this->attributes[$this->archivedXml()[$key]] ?? null) !== null;
    }

    protected function readArchivedXml(string $key): ?string
    {
        if (array_key_exists($key, $this->pendingXml)) {
            return $this->pendingXml[$key];
        }

        if (array_key_exists($key, $this->loadedXml)) {
            return $this->loadedXml[$key];
        }

        $path = $this->attributes[$this->archivedXml()[$key]] ?? null;
        if ($path === null) {
            return null;
        }

        return $this->loadedXml[$key] = Storage::disk(self::XML_DISK)->get($path);
    }

    protected function writePendingXml(): void
    {
        foreach ($this->pendingXml as $key => $xml) {
            $column = $this->archivedXml()[$key];

            if ($xml === null) {
                $this->attributes[$column] = null;

                continue;
            }

            $path = $this->archivedXmlPath($key);
            Storage::disk(self::XML_DISK)->put($path, $xml);

            $this->attributes[$column] = $path;
            $this->loadedXml[$key] = $xml;
        }

        $this->pendingXml = [];
    }

    /** {tenant}/{tabela}/{ano}/{mês}/{ulid}-{atributo}.xml: prefixo por workspace para exportar ou expurgar um cliente inteiro. */
    protected function archivedXmlPath(string $key): string
    {
        // O saving roda antes do creating do BelongsToTenant, então o tenant do contexto entra aqui também.
        $tenantId = $this->attributes['tenant_id'] ?? app(CurrentTenant::class)->id() ?? '_no_tenant';

        return sprintf(
            '%s/%s/%s/%s-%s.xml',
            $tenantId,
            $this->getTable(),
            now()->format('Y/m'),
            strtolower((string) Str::ulid()),
            str_replace('_', '-', $key),
        );
    }
}
