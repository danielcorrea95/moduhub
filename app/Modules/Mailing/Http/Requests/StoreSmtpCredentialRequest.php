<?php

namespace App\Modules\Mailing\Http\Requests;

use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use App\Modules\Mailing\Contracts\SmtpCredentialRepositoryContract;
use App\Modules\Mailing\Models\SmtpCredential;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSmtpCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null && $this->user()?->can('update', $tenant);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $tenant = app(CurrentTenant::class)->get();

                // Sem domínio verificado o relay não teria de onde enviar.
                if (! app(SendingDomainRepositoryContract::class)->forTenant($tenant)?->isVerified()) {
                    $validator->errors()->add('name', 'Verifique o domínio de envio antes de criar credenciais SMTP.');

                    return;
                }

                if (app(SmtpCredentialRepositoryContract::class)->countForTenant($tenant) >= SmtpCredential::MAX_PER_TENANT) {
                    $validator->errors()->add('name', 'Limite de '.SmtpCredential::MAX_PER_TENANT.' credenciais atingido. Revogue uma que não usa mais.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome'];
    }
}
