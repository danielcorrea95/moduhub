<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Os XMLs fiscais saem do banco para o disco "fiscal" (S3 em produção); fica só o caminho.
 * Com FILE_STORAGE_DRIVER=s3 já configurado eles vão direto para o bucket. Senão vão para
 * storage/app/private/fiscal e sobem depois com `artisan storage:push-to-s3`.
 * O caminho é determinístico, então rodar de novo depois de uma falha no meio reescreve o mesmo arquivo.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> tabela => [coluna antiga => coluna nova] */
    private const COLUMNS = [
        'invoices' => [
            'dps_xml' => 'dps_xml_path',
            'nfse_xml' => 'nfse_xml_path',
            'cancellation_xml' => 'cancellation_xml_path',
        ],
        'received_invoices' => ['nfse_xml' => 'nfse_xml_path'],
        'nfse_documents' => ['xml' => 'xml_path'],
    ];

    public function up(): void
    {
        $disk = Storage::disk('fiscal');

        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $old => $new) {
                    if (! Schema::hasColumn($table, $new)) {
                        $blueprint->string($new)->nullable()->after($old);
                    }
                }
            });

            DB::table($table)
                ->select(['id', 'tenant_id', ...array_keys($columns)])
                ->lazyById(100)
                ->each(function (object $row) use ($disk, $table, $columns) {
                    $paths = [];
                    foreach ($columns as $old => $new) {
                        if ($row->{$old} === null) {
                            continue;
                        }
                        $path = sprintf('%s/%s/migrated/%s-%s.xml', $row->tenant_id, $table, strtolower($row->id), str_replace('_', '-', $old));
                        $disk->put($path, $row->{$old});
                        $paths[$new] = $path;
                    }

                    if ($paths !== []) {
                        DB::table($table)->where('id', $row->id)->update($paths);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->dropColumn(array_keys($columns));
            });
        }
    }

    public function down(): void
    {
        $disk = Storage::disk('fiscal');

        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $old => $new) {
                    $blueprint->longText($old)->nullable()->after($new);
                }
            });

            DB::table($table)
                ->select(['id', ...array_values($columns)])
                ->lazyById(100)
                ->each(function (object $row) use ($disk, $table, $columns) {
                    $contents = [];
                    foreach ($columns as $old => $new) {
                        if ($row->{$new} !== null) {
                            $contents[$old] = $disk->get($row->{$new});
                        }
                    }

                    if ($contents !== []) {
                        DB::table($table)->where('id', $row->id)->update($contents);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->dropColumn(array_values($columns));
            });
        }
    }
};
