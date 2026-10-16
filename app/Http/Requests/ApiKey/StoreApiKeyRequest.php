<?php

namespace App\Http\Requests\ApiKey;

use App\Platform\Contracts\TenantRepositoryContract;
use App\Platform\ScopeCatalog;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();
        return $tenant !== null && $this->user()?->can('manageApi', $tenant);
    }

    public function rules(): array
    {
        $tenant = app(CurrentTenant::class)->get();
        $modules = app(TenantRepositoryContract::class)->activeModuleKeys($tenant);
        $allowed = array_keys(ScopeCatalog::forModules($modules));

        return [
            'name' => 'required|string|max:255',
            'environment' => 'required|in:live,test',
            'scopes' => 'required|array|min:1',
            'scopes.*' => 'required|string|in:' . implode(',', $allowed),
            'expires_at' => 'nullable|date|after:now',
        ];
    }
}
