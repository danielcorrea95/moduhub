<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A assinatura com certificado do próprio signatário saiu do produto. Quem ainda não assinou
 * em documento aberto passa a confirmar por e-mail; senão ficaria sem como assinar.
 * Assinaturas ICP já concluídas ficam como estão.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_signers')
            ->where('auth_method', 'icp')
            ->whereNotIn('status', ['signed', 'rejected'])
            ->update(['auth_method' => 'email', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Sem volta: não há como saber quais signatários eram ICP.
    }
};
