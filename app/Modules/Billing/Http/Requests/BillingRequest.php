<?php

namespace App\Modules\Billing\Http\Requests;

use App\Models\TenantSubscription;
use App\Modules\Billing\Services\Gateway\CardData;
use App\Modules\Invoices\Rules\ValidTaxDocument;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base dos formulários de cobrança: só o dono do workspace, dados de cobrança do titular e, no
 * cartão, o cartão. Os campos do cartão nunca voltam para a sessão (dontFlash em bootstrap/app.php).
 */
abstract class BillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null && $this->user()->can('manageBilling', $tenant);
    }

    protected function prepareForValidation(): void
    {
        $digits = fn (string $key) => $this->has($key) ? preg_replace('/\D/', '', (string) $this->input($key)) : null;

        $this->merge(array_filter([
            'tax_id' => $digits('tax_id'),
            'phone' => $digits('phone'),
            'postal_code' => $digits('postal_code'),
            'card_number' => $digits('card_number'),
            'card_cvv' => $digits('card_cvv'),
            'card_expiry_month' => $this->filled('card_expiry_month') ? str_pad($digits('card_expiry_month'), 2, '0', STR_PAD_LEFT) : null,
            // "29" vira "2029".
            'card_expiry_year' => $this->filled('card_expiry_year') && strlen((string) $digits('card_expiry_year')) === 2
                ? '20'.$digits('card_expiry_year')
                : $digits('card_expiry_year'),
        ], fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    protected function accountRules(): array
    {
        return [
            'billing_type' => ['required', Rule::in([TenantSubscription::BILLING_PIX, TenantSubscription::BILLING_CREDIT_CARD])],
            'name' => ['required', 'string', 'max:120'],
            'tax_id' => ['required', 'string', new ValidTaxDocument],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['required', 'digits_between:10,11'],
            'postal_code' => ['required', 'digits:8'],
            'address_number' => ['required', 'string', 'max:20'],
            'address_complement' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    protected function cardRules(): array
    {
        $required = 'required_if:billing_type,'.TenantSubscription::BILLING_CREDIT_CARD;

        return [
            'card_holder_name' => [$required, 'nullable', 'string', 'max:100'],
            'card_number' => [$required, 'nullable', 'digits_between:13,19', $this->luhn(...)],
            'card_expiry_month' => [$required, 'nullable', Rule::in(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'])],
            'card_expiry_year' => [$required, 'nullable', 'digits:4', 'integer', 'min:'.now()->year],
            'card_cvv' => [$required, 'nullable', 'digits_between:3,4'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tax_id' => 'CPF ou CNPJ',
            'phone' => 'celular',
            'postal_code' => 'CEP',
            'address_number' => 'número',
            'card_holder_name' => 'nome impresso no cartão',
            'card_number' => 'número do cartão',
            'card_expiry_month' => 'mês de validade',
            'card_expiry_year' => 'ano de validade',
            'card_cvv' => 'código de segurança',
        ];
    }

    public function messages(): array
    {
        return [
            'card_*.required_if' => 'Informe o :attribute.',
        ];
    }

    /** @return array<string, string|null> */
    public function accountData(): array
    {
        return $this->safe()->only(['name', 'tax_id', 'email', 'phone', 'postal_code', 'address_number', 'address_complement']);
    }

    public function billingType(): string
    {
        return (string) $this->validated('billing_type');
    }

    public function card(): ?CardData
    {
        if ($this->billingType() !== TenantSubscription::BILLING_CREDIT_CARD) {
            return null;
        }

        return new CardData(
            holderName: (string) $this->validated('card_holder_name'),
            number: (string) $this->validated('card_number'),
            expiryMonth: (string) $this->validated('card_expiry_month'),
            expiryYear: (string) $this->validated('card_expiry_year'),
            cvv: (string) $this->validated('card_cvv'),
        );
    }

    private function luhn(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = strrev((string) $value);
        $sum = 0;
        for ($i = 0, $n = strlen($digits); $i < $n; $i++) {
            $d = (int) $digits[$i];
            if ($i % 2 === 1) {
                $d *= 2;
                $d = $d > 9 ? $d - 9 : $d;
            }
            $sum += $d;
        }

        if ($sum % 10 !== 0) {
            $fail('Confira o número do cartão.');
        }
    }
}
