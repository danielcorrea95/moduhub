<?php

namespace App\Platform;

use App\Models\Module;

class EventCatalog
{
    public static function all(): array
    {
        return [
            Module::SIGNATURES => [
                'document.sent' => 'Documento enviado para assinatura',
                'document.viewed' => 'Signatário visualizou o documento',
                'document.signed' => 'Um signatário assinou',
                'document.completed' => 'Todos os signatários assinaram',
                'document.rejected' => 'Signatário rejeitou',
            ],
            Module::INVOICES => [
                'invoice.issued' => 'NFS-e emitida com sucesso',
                'invoice.rejected' => 'NFS-e rejeitada pela prefeitura',
                'invoice.cancelled' => 'NFS-e cancelada',
                'invoice.replaced' => 'NFS-e substituída por outra',
                'invoice.taker_rejected' => 'Tomador ou intermediário rejeitou a NFS-e',
                'invoice.cancellation_denied' => 'Análise fiscal negou o cancelamento',
                'invoice.received' => 'NFS-e de terceiro recebida (como tomador, intermediário ou prestador)',
                'invoice.certificate_expiring' => 'Certificado do emitente vence em 30, 15, 7 ou 1 dia, ou venceu',
            ],
        ];
    }

    public static function forModules(array $moduleKeys): array
    {
        $all = self::all();
        $result = [];

        foreach ($moduleKeys as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }

    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::all() as $moduleKey => $events) {
            foreach (array_keys($events) as $event) {
                $keys[] = $event;
            }
        }

        return $keys;
    }

    public static function keysForModules(array $moduleKeys): array
    {
        $keys = [];
        foreach (self::forModules($moduleKeys) as $events) {
            foreach (array_keys($events) as $event) {
                $keys[] = $event;
            }
        }

        return $keys;
    }
}
