<?php

namespace App\Modules\Mailing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mailing\Contracts\EmailDeliveryRepositoryContract;
use App\Modules\Mailing\Contracts\EmailSuppressionRepositoryContract;
use App\Modules\Mailing\Enums\MailboxProvider;
use App\Modules\Mailing\Http\Requests\EmailLogRequest;
use App\Modules\Mailing\Http\Requests\ReleaseSuppressionRequest;
use App\Modules\Mailing\Models\EmailDelivery;
use App\Modules\Mailing\Models\EmailDeliveryEvent;
use App\Modules\Mailing\Models\EmailSuppression;
use App\Modules\Mailing\Services\Report\EmailReportService;
use App\Modules\Mailing\Services\Report\ReportPeriod;
use App\Modules\Mailing\Services\Suppression\EmailSuppressionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function __construct(
        private EmailDeliveryRepositoryContract $deliveries,
        private EmailSuppressionRepositoryContract $suppressions,
    ) {}

    public function overview(EmailLogRequest $request, EmailReportService $reports): Response
    {
        $period = $request->period();

        return Inertia::render('Mailing/Emails/Overview', [
            'period' => $this->period($period),
            'report' => $reports->overview($period),
            'tracking' => $this->tracking(),
        ]);
    }

    public function messages(EmailLogRequest $request): Response
    {
        $period = $request->period();
        $selectedId = $request->validated('mensagem');
        $selected = $selectedId ? $this->deliveries->findWithEvents($selectedId) : null;

        return Inertia::render('Mailing/Emails/Messages', [
            'period' => $this->period($period),
            'filters' => $request->filters(),
            'providers' => array_map(fn (MailboxProvider $p) => ['key' => $p->value, 'label' => $p->label()], MailboxProvider::cases()),
            'messages' => $this->deliveries
                ->paginateForListing($request->filters() + ['from' => $period->from, 'to' => $period->to])
                ->through(fn (EmailDelivery $d) => $this->row($d)),
            'selected' => $selected === null ? null : $this->row($selected) + [
                'message_id' => $selected->message_id,
                'bounce_type' => $selected->bounce_type,
                'detail' => $selected->detail,
                'events' => $selected->events->map(fn (EmailDeliveryEvent $e) => [
                    'id' => $e->getKey(),
                    'type' => $e->type,
                    'occurred_at' => $e->occurred_at->toIso8601String(),
                    'detail' => $e->detail,
                ])->values(),
            ],
            'tracking' => $this->tracking(),
            'suppressed' => $this->suppressions->count(),
        ]);
    }

    public function suppressions(EmailLogRequest $request): Response
    {
        return Inertia::render('Mailing/Emails/Suppressions', [
            'search' => $request->search(),
            'suppressed' => $this->suppressions->count(),
            'suppressions' => $this->suppressions
                ->paginateForListing($request->search())
                ->through(fn (EmailSuppression $s) => [
                    'id' => $s->getKey(),
                    'email' => $s->email,
                    'reason' => $s->reason,
                    'detail' => $s->detail,
                    'removable' => $s->isRemovable(),
                    'delivery_id' => $s->email_delivery_id,
                    'updated_at' => $s->updated_at?->toIso8601String(),
                ]),
        ]);
    }

    public function release(ReleaseSuppressionRequest $request, EmailSuppression $suppression, EmailSuppressionService $service): RedirectResponse
    {
        $service->release($suppression);
        $request->session()->flash('success', "{$suppression->email} saiu da lista de bloqueio e pode voltar a receber.");

        return redirect()->route('emails.suppressions');
    }

    /** @return array<string, mixed> */
    private function row(EmailDelivery $d): array
    {
        return [
            'id' => $d->getKey(),
            'recipient' => $d->recipient,
            'from_address' => $d->from_address,
            'subject' => $d->subject,
            'origin' => $d->originLabel(),
            'provider' => (MailboxProvider::tryFrom((string) $d->provider) ?? MailboxProvider::Other)->label(),
            'status' => $d->status,
            'sent_at' => $d->sent_at->toIso8601String(),
            'delivered_at' => $d->delivered_at?->toIso8601String(),
            'opened_at' => $d->opened_at?->toIso8601String(),
            'open_count' => $d->open_count,
            'clicked_at' => $d->clicked_at?->toIso8601String(),
            'click_count' => $d->click_count,
        ];
    }

    /** @return array{days: int, options: list<int>, from: string, to: string} */
    private function period(ReportPeriod $period): array
    {
        return [
            'days' => $period->days,
            'options' => ReportPeriod::OPTIONS,
            'from' => $period->from->toIso8601String(),
            'to' => $period->to->toIso8601String(),
        ];
    }

    /** Abertura e clique só existem se o configuration set publicar os eventos Open e Click. */
    private function tracking(): bool
    {
        return (bool) config('mailing.track_engagement');
    }
}
