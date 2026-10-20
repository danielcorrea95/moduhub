<?php

namespace App\Modules\Mailing\Http\Requests;

use App\Modules\Mailing\Rules\OwnSendingDomain;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSendingDomainRequest extends SenderRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Aceita o que o cliente colar: "https://www.empresa.com.br/", "@empresa.com.br".
        $domain = Str::lower(trim((string) $this->input('domain')));
        $domain = preg_replace('#^[a-z]+://#', '', $domain);
        $domain = Str::before($domain, '/');
        $domain = rtrim(ltrim($domain, '@'), '.');

        $this->merge(['domain' => Str::startsWith($domain, 'www.') ? Str::after($domain, 'www.') : $domain]);
    }

    public function rules(): array
    {
        return parent::rules() + [
            'domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                new OwnSendingDomain,
                Rule::unique('sending_domains', 'domain'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $tenant = app(CurrentTenant::class)->get();

                if ($tenant && DB::table('sending_domains')->where('tenant_id', $tenant->getKey())->exists()) {
                    $validator->errors()->add('domain', 'Este workspace já tem um domínio de envio. Remova o atual para cadastrar outro.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['domain' => 'domínio'];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'domain.regex' => 'Informe só o domínio, como empresa.com.br.',
            'domain.unique' => 'Este domínio já está cadastrado em outro workspace.',
        ];
    }
}
