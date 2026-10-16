<?php

namespace App\Http\Requests\Member;

use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();
        return $tenant !== null && $this->user()?->can('manageMembers', $tenant);
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'role' => 'required|in:admin,member',
        ];
    }
}
