<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EndComplimentaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin-panel') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
