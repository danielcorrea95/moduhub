<?php

namespace App\Platform;

use App\Models\Module;

class ScopeCatalog
{
    public const WILDCARD = '*';

    public static function all(): array
    {
        return [
            self::WILDCARD => 'Acesso total (todos os módulos)',
            Module::SIGNATURES . ':*' => 'Assinaturas — leitura e escrita',
            Module::SIGNATURES . ':read' => 'Assinaturas — somente leitura',
            Module::SIGNATURES . ':write' => 'Assinaturas — somente escrita',
            Module::INVOICES . ':*' => 'Notas fiscais — leitura e escrita',
            Module::INVOICES . ':read' => 'Notas fiscais — somente leitura',
            Module::INVOICES . ':write' => 'Notas fiscais — somente escrita',
        ];
    }

    public static function forModules(array $moduleKeys): array
    {
        $all = self::all();
        $result = [self::WILDCARD => $all[self::WILDCARD]];

        foreach ($moduleKeys as $key) {
            foreach ([$key . ':*', $key . ':read', $key . ':write'] as $scope) {
                if (isset($all[$scope])) {
                    $result[$scope] = $all[$scope];
                }
            }
        }

        return $result;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
