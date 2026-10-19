<?php

namespace App\Modules\Signatures\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * Cópias locais e temporárias dos arquivos do disco de documentos.
 * FPDI, TCPDF e openssl só trabalham com caminho de arquivo, e no S3 não há caminho:
 * baixa, processa aqui e devolve com upload. cleanup() apaga tudo o que foi criado.
 */
class LocalScratch
{
    /** @var array<int, string> */
    private array $files = [];

    public function download(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if ($stream === null) {
            throw new RuntimeException("Arquivo não encontrado no disco: {$path}");
        }

        $local = $this->create();
        $target = fopen($local, 'wb');

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($target);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $local;
    }

    public function upload(string $local, Filesystem $disk, string $path): void
    {
        $stream = fopen($local, 'rb');

        try {
            $disk->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** Caminho de um arquivo vazio, criado com 0600, que some no cleanup(). */
    public function create(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mdhb_doc_');
        if ($path === false) {
            throw new RuntimeException('Não foi possível criar um arquivo temporário.');
        }

        return $this->files[] = $path;
    }

    public function cleanup(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        $this->files = [];
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
