<?php

namespace App\Modules\Mailing\Http\Requests;

use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Nome, endereço e "responder para" do remetente. Base do cadastro do domínio.
 */
class SenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null && $this->user()?->can('update', $tenant);
    }

    protected function prepareForValidation(): void
    {
        $local = Str::lower(trim((string) $this->input('from_local_part')));

        $this->merge([
            'from_name' => trim((string) $this->input('from_name')),
            // Quem digita o e-mail inteiro quer dizer só a parte antes do @.
            'from_local_part' => Str::contains($local, '@') ? Str::before($local, '@') : $local,
            'reply_to' => trim((string) $this->input('reply_to')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'from_name' => ['required', 'string', 'max:100'],
            'from_local_part' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?$/'],
            'reply_to' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'from_name' => 'nome do remetente',
            'from_local_part' => 'endereço do remetente',
            'reply_to' => 'responder para',
        ];
    }

    public function messages(): array
    {
        return [
            'from_local_part.regex' => 'Use só letras, números, ponto, hífen ou sublinhado, como "contato" ou "nao-responda".',
        ];
    }
}
