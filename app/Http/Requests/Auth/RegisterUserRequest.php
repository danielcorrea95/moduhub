<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'workspace_name' => 'nullable|string|max:255',
            'invitation_token' => 'nullable|string',
            // Carrinho montado na página de vendas: o teste grátis já começa nesses planos.
            'plans' => ['nullable', 'array', 'max:10'],
            'plans.*' => ['string', Rule::exists('plans', 'key')->where('is_active', true)->where('is_public', true)],
        ];
    }
}
