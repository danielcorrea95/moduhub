<?php

namespace App\Modules\Mailing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mailing\Http\Requests\StoreSmtpCredentialRequest;
use App\Modules\Mailing\Models\SmtpCredential;
use App\Modules\Mailing\Services\SmtpCredentialService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SmtpCredentialController extends Controller
{
    public function __construct(
        private SmtpCredentialService $service,
    ) {}

    public function store(StoreSmtpCredentialRequest $request, CurrentTenant $current): RedirectResponse
    {
        $result = $this->service->issue($current->get(), $request->user(), $request->validated('name'));

        $request->session()->flash('smtp_credential', [
            'id' => $result['credential']->getKey(),
            'name' => $result['credential']->name,
            'username' => $result['credential']->username,
            'password' => $result['password'],
        ]);
        $request->session()->flash('success', 'Credencial criada. Copie a senha agora: ela não será mostrada de novo.');

        return redirect()->route('sending-domain.show', ['aba' => 'smtp']);
    }

    public function destroy(Request $request, CurrentTenant $current, SmtpCredential $credential): RedirectResponse
    {
        $this->authorize('update', $current->get());
        $this->service->revoke($credential);
        $request->session()->flash('success', "Credencial \"{$credential->name}\" revogada. Sistemas que usavam ela deixam de enviar.");

        return redirect()->route('sending-domain.show', ['aba' => 'smtp']);
    }
}
