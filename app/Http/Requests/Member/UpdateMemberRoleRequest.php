<?php

namespace App\Http\Requests\Member;

use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();
        return $tenant !== null && $this->user()?->can('manageMembers', $tenant);
    }

    public function rules(): array
    {
        return [
            'role' => 'required|in:admin,member',
        ];
    }
}
