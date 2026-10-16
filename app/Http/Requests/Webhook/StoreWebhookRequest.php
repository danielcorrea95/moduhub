<?php

namespace App\Http\Requests\Webhook;

use App\Platform\Contracts\TenantRepositoryContract;
use App\Platform\EventCatalog;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookRequest extends FormRequest
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
        $allowed = array_merge(['*'], EventCatalog::keysForModules($modules));

        return [
            'url' => 'required|url:https|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'required|string|in:' . implode(',', $allowed),
            'is_active' => 'boolean',
        ];
    }
}
