<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Http\Requests\ChangePaymentMethodRequest;
use App\Modules\Billing\Http\Requests\ChangePlanRequest;
use App\Modules\Billing\Http\Requests\ManageBillingRequest;
use App\Modules\Billing\Http\Requests\SubscribeRequest;
use App\Modules\Billing\Models\BillingPayment;
use App\Modules\Billing\Services\BillingOverviewService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected BillingOverviewService $overview,
        protected CurrentTenant $current,
    ) {}

    public function show(Request $request): Response
    {
        $tenant = $this->current->get();
        abort_if($tenant === null, 404);
        $this->authorize('manageBilling', $tenant);

        return Inertia::render('Billing/Show', $this->overview->forTenant($tenant, $request->user(), array_filter(explode(',', $request->string('plans')->toString()))));
    }

    public function subscribe(SubscribeRequest $request): RedirectResponse
    {
        $subscription = $this->subscriptions->subscribe(
            $this->current->get(),
            $request->validated('plan_ids'),
            $request->billingType(),
            $request->accountData(),
            $request->card(),
            $request->ip(),
        );

        return back()->with('success', $subscription->isTrial()
            ? 'Assinatura feita. A primeira mensalidade vence no fim do teste grátis.'
            : 'Assinatura feita. O acesso é liberado assim que o pagamento for confirmado.');
    }

    public function changePlan(ChangePlanRequest $request): RedirectResponse
    {
        $this->subscriptions->changePlans($this->current->get(), $request->validated('plan_ids'));

        return back()->with('success', 'Planos alterados. O novo valor vale a partir da próxima mensalidade.');
    }

    public function changePaymentMethod(ChangePaymentMethodRequest $request): RedirectResponse
    {
        $this->subscriptions->changePaymentMethod(
            $this->current->get(),
            $request->billingType(),
            $request->accountData(),
            $request->card(),
            $request->ip(),
        );

        return back()->with('success', 'Forma de pagamento atualizada.');
    }

    public function cancel(ManageBillingRequest $request): RedirectResponse
    {
        $this->subscriptions->cancel($this->current->get());

        return back()->with('success', 'Assinatura cancelada. O workspace segue liberado até o fim do período pago.');
    }

    public function pix(ManageBillingRequest $request, BillingPayment $payment): JsonResponse
    {
        return response()->json($this->overview->pix($payment));
    }
}
