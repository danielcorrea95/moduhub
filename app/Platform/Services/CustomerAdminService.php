<?php

namespace App\Platform\Services;

use App\Mail\CustomerWelcomeMail;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Billing\Services\ComplimentaryService;
use App\Platform\Contracts\TenantServiceContract;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Clientes cadastrados pelo admin da plataforma: workspace já em cortesia, sem passar pela página
 * de vendas nem pelo Asaas. O dono que ainda não tem conta recebe um link para criar a senha.
 *
 * O admin navega com o próprio workspace na sessão; tudo aqui roda fora dele (`outside`) para
 * entrar no workspace do cliente.
 */
class CustomerAdminService
{
    public function __construct(
        protected TenantServiceContract $tenants,
        protected ComplimentaryService $complimentary,
        protected CurrentTenant $currentTenant,
    ) {}

    /**
     * @param  array{workspace_name: string, tax_id?: string|null, owner_name: string, owner_email: string, plan_ids: list<int>}  $data
     * @return array{tenant: Tenant, owner_created: bool}
     */
    public function registerComplimentary(array $data): array
    {
        [$tenant, $owner, $created] = $this->currentTenant->outside(fn () => DB::transaction(function () use ($data) {
            $owner = User::query()->where('email', $data['owner_email'])->first();
            $created = $owner === null;

            if ($created) {
                // Senha que ninguém conhece: o dono define a dele pelo link do e-mail.
                $owner = User::create([
                    'name' => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'password' => Hash::make(Str::random(40)),
                ]);
                // A equipe da Moduhub conferiu o e-mail ao cadastrar.
                $owner->forceFill(['email_verified_at' => now()])->save();
            }

            $tenant = $this->tenants->create($owner, [
                'name' => $data['workspace_name'],
                'tax_id' => $data['tax_id'] ?? null,
            ]);
            $this->complimentary->grant($tenant, $data['plan_ids']);

            return [$tenant, $owner, $created];
        }));

        // Fora da transação: e-mail enviado não volta atrás se algo acima falhar.
        Mail::to($owner->email)->send(new CustomerWelcomeMail(
            $tenant,
            $owner,
            $created ? route('password.reset', ['token' => Password::createToken($owner), 'email' => $owner->email]) : null,
        ));

        return ['tenant' => $tenant, 'owner_created' => $created];
    }

    /** @param  list<int>  $planIds */
    public function grantComplimentary(Tenant $tenant, array $planIds): TenantSubscription
    {
        return $this->currentTenant->outside(fn () => $this->complimentary->grant($tenant, $planIds));
    }

    public function endComplimentary(Tenant $tenant): TenantSubscription
    {
        return $this->currentTenant->outside(fn () => $this->complimentary->end($tenant));
    }
}
