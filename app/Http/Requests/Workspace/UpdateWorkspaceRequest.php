<?php

namespace App\Http\Requests\Workspace;

use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();
        return $tenant !== null && $this->user()?->can('update', $tenant);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:32',
            'branding.primary_color' => 'nullable|string|max:9',
            'branding.logo_url' => 'nullable|url|max:2048',
            'branding.support_email' => 'nullable|email|max:255',
        ];
    }
}
