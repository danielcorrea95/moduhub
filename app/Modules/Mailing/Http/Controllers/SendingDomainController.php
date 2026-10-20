<?php

namespace App\Modules\Mailing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mailing\Contracts\SendingDomainRepositoryContract;
use App\Modules\Mailing\Contracts\SmtpCredentialRepositoryContract;
use App\Modules\Mailing\Exceptions\TestEmailFailed;
use App\Modules\Mailing\Http\Requests\SenderRequest;
use App\Modules\Mailing\Http\Requests\SendTestEmailRequest;
use App\Modules\Mailing\Http\Requests\StoreSendingDomainRequest;
use App\Modules\Mailing\Models\SendingDomain;
use App\Modules\Mailing\Models\SmtpCredential;
use App\Modules\Mailing\Services\Quota\EmailQuotaService;
use App\Modules\Mailing\Services\SendingDomainService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SendingDomainController extends Controller
{
    public function __construct(
        private SendingDomainRepositoryContract $domains,
        private SendingDomainService $service,
        private SmtpCredentialRepositoryContract $smtpCredentials,
        private EmailQuotaService $quota,
    ) {}

    public function show(Request $request, CurrentTenant $current): Response
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);
        $this->authorize('update', $tenant);

        $domain = $this->domains->forTenant($tenant);
        $relay = config('mailing.relay');

        return Inertia::render('Mailing/SendingDomain', [
            'tenantName' => $tenant->name,
            'platformSender' => [
                'name' => config('mail.from.name'),
                'address' => config('mail.from.address'),
            ],
            'domain' => $domain ? [
                'domain' => $domain->domain,
                'status' => $domain->status,
                'from_name' => $domain->from_name,
                'from_local_part' => $domain->from_local_part,
                'reply_to' => $domain->reply_to,
                'verified_at' => $domain->verified_at?->toIso8601String(),
                'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
                'records' => $this->service->dnsRecords($domain),
            ] : null,
            'usage' => $this->quota->usage($tenant)->toArray(),
            'smtp' => [
                'enabled' => $relay['enabled'],
                'host' => $relay['host'],
                'ports' => $relay['ports'],
                'max_credentials' => SmtpCredential::MAX_PER_TENANT,
                'credentials' => $this->smtpCredentials->forTenant($tenant)->map(fn (SmtpCredential $c) => [
                    'id' => $c->getKey(),
                    'name' => $c->name,
                    'username' => $c->username,
                    'last_four' => $c->last_four,
                    'created_by' => $c->creator?->name,
                    'created_at' => $c->created_at?->toIso8601String(),
                    'last_used_at' => $c->last_used_at?->toIso8601String(),
                ])->values(),
                'reveal' => $request->session()->pull('smtp_credential'),
            ],
        ]);
    }

    public function store(StoreSendingDomainRequest $request, CurrentTenant $current): RedirectResponse
    {
        $this->service->register($current->get(), $request->validated());
        $request->session()->flash('success', 'Domínio adicionado. Agora publique os registros abaixo no DNS.');

        return redirect()->route('sending-domain.show');
    }

    public function update(SenderRequest $request, CurrentTenant $current): RedirectResponse
    {
        $this->service->updateSender($this->currentDomain($current), $request->validated());
        $request->session()->flash('success', 'Remetente atualizado.');

        return back();
    }

    public function verify(Request $request, CurrentTenant $current): RedirectResponse
    {
        $this->authorize('update', $current->get());
        $domain = $this->service->checkVerification($this->currentDomain($current));

        $request->session()->flash(...match ($domain->status) {
            SendingDomain::STATUS_VERIFIED => ['success', 'Domínio verificado. Os e-mails do workspace já saem com o seu remetente.'],
            SendingDomain::STATUS_FAILED => ['error', 'A verificação falhou. Confira os registros no DNS.'],
            default => ['success', 'Ainda não encontramos todos os registros. O DNS pode levar algumas horas para propagar.'],
        });

        return back();
    }

    public function test(SendTestEmailRequest $request, CurrentTenant $current): RedirectResponse
    {
        $to = $request->validated('to');

        try {
            $from = $this->service->sendTest($current->get(), $to);
            $request->session()->flash('success', "E-mail de teste enviado para {$to}, com o remetente {$from}.");
        } catch (TestEmailFailed $e) {
            $request->session()->flash('error', $e->getMessage());
        }

        return back();
    }

    public function destroy(Request $request, CurrentTenant $current): RedirectResponse
    {
        $this->authorize('update', $current->get());
        $this->service->remove($this->currentDomain($current));
        $request->session()->flash('success', 'Domínio removido. Os e-mails voltam a sair pelo remetente da Moduhub.');

        return redirect()->route('sending-domain.show');
    }

    private function currentDomain(CurrentTenant $current): SendingDomain
    {
        $tenant = $current->get();
        abort_if($tenant === null, 404);

        return $this->domains->forTenant($tenant) ?? abort(404);
    }
}
