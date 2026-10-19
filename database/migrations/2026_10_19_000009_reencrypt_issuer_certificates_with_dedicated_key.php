<?php

use App\Platform\Security\CertificateEncrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * O .pfx e a senha dos emitentes saem da APP_KEY e passam para a CERTIFICATES_ENCRYPTION_KEY.
 * Só trocar o cast deixaria os registros existentes ilegíveis, então cada um é recifrado aqui.
 * Idempotente: o que já abre com a chave nova fica como está.
 */
return new class extends Migration
{
    private const COLUMNS = ['certificate_pfx', 'certificate_password'];

    public function up(): void
    {
        $certificates = app(CertificateEncrypter::class);

        $this->recrypt(
            alreadyDone: fn (string $payload) => $this->opens(fn () => $certificates->decrypt($payload)),
            decrypt: fn (string $payload) => Crypt::decryptString($payload),
            encrypt: fn (string $plain) => $certificates->encrypt($plain),
        );
    }

    public function down(): void
    {
        $certificates = app(CertificateEncrypter::class);

        $this->recrypt(
            alreadyDone: fn (string $payload) => $this->opens(fn () => Crypt::decryptString($payload)),
            decrypt: fn (string $payload) => $certificates->decrypt($payload),
            encrypt: fn (string $plain) => Crypt::encryptString($plain),
        );
    }

    private function recrypt(Closure $alreadyDone, Closure $decrypt, Closure $encrypt): void
    {
        DB::transaction(function () use ($alreadyDone, $decrypt, $encrypt) {
            // Query direta, sem o model: não há tenant na migração, e o cast do model já usa a chave nova.
            $rows = DB::table('invoice_issuers')
                ->whereNotNull('certificate_pfx')
                ->orWhereNotNull('certificate_password')
                ->lockForUpdate()
                ->get(['id', ...self::COLUMNS]);

            foreach ($rows as $row) {
                $changes = [];

                foreach (self::COLUMNS as $column) {
                    $payload = $row->{$column};

                    if ($payload !== null && ! $alreadyDone($payload)) {
                        $changes[$column] = $encrypt($decrypt($payload));
                    }
                }

                if ($changes !== []) {
                    DB::table('invoice_issuers')->where('id', $row->id)->update($changes);
                }
            }
        });
    }

    private function opens(Closure $decrypt): bool
    {
        try {
            $decrypt();

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
