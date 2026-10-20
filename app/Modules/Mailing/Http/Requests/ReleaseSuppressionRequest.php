<?php

namespace App\Modules\Mailing\Http\Requests;

use App\Modules\Mailing\Models\EmailSuppression;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReleaseSuppressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null && $this->user()?->can('update', $tenant);
    }

    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var EmailSuppression $suppression */
                $suppression = $this->route('suppression');

                if (! $suppression->isRemovable()) {
                    $validator->errors()->add('suppression', 'Quem marcou como spam só volta a receber se pedir. Fale com o suporte da Moduhub.');
                }
            },
        ];
    }
}
