<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sobe para o S3 o que ficou em storage/app/private antes de FILE_STORAGE_DRIVER=s3.
 * Os caminhos no banco são relativos ao disco, então nada muda nas tabelas: o mesmo
 * "documents/<tenant>/2026/10/src_x.pdf" passa a ser lido do bucket.
 * Idempotente: arquivo que já está no bucket com o mesmo tamanho é pulado.
 */
class PushFilesToS3 extends Command
{
    protected $signature = 'storage:push-to-s3
        {--disk=* : Discos a enviar (padrão: documents e fiscal)}
        {--dry-run : Só lista o que seria enviado}';

    protected $description = 'Envia para o S3 os arquivos locais dos discos de documentos e fiscal';

    public function handle(): int
    {
        $disks = $this->option('disk') ?: ['documents', 'fiscal'];
        $failed = false;

        foreach ($disks as $name) {
            if (config("filesystems.disks.{$name}.driver") !== 's3') {
                $this->error("O disco {$name} não está no S3. Configure FILE_STORAGE_DRIVER=s3 antes.");

                return self::FAILURE;
            }

            $local = Storage::build(['driver' => 'local', 'root' => storage_path("app/private/{$name}"), 'throw' => true]);
            $remote = Storage::disk($name);
            $sent = $skipped = 0;

            foreach ($local->allFiles() as $path) {
                if ($remote->exists($path) && $remote->size($path) === $local->size($path)) {
                    $skipped++;

                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("  {$name}/{$path}");
                    $sent++;

                    continue;
                }

                try {
                    $stream = $local->readStream($path);
                    $remote->writeStream($path, $stream);
                    $sent++;
                } catch (\Throwable $e) {
                    $failed = true;
                    $this->error("  {$name}/{$path}: {$e->getMessage()}");
                } finally {
                    if (isset($stream) && is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            $verb = $this->option('dry-run') ? 'a enviar' : 'enviados';
            $this->info("{$name}: {$sent} {$verb}, {$skipped} já estavam no bucket.");
        }

        // Os arquivos locais ficam: apagar é decisão de quem opera, depois de conferir o bucket.
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
