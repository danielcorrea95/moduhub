<?php

namespace App\Http\Requests\Webhook;

use App\Models\WebhookEndpoint;
use App\Platform\Contracts\TenantRepositoryContract;
use App\Platform\EventCatalog;
use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $endpoint = $this->route('endpoint');
        return $endpoint instanceof WebhookEndpoint
            && $this->user()?->can('update', $endpoint);
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
