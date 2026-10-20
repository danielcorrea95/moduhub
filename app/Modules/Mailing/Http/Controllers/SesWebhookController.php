<?php

namespace App\Modules\Mailing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Mailing\Http\Requests\SesNotificationRequest;
use App\Modules\Mailing\Services\SesNotificationService;
use Illuminate\Http\Response;

class SesWebhookController extends Controller
{
    public function __invoke(SesNotificationRequest $request, SesNotificationService $notifications): Response
    {
        $notifications->handle($request->snsMessage());

        return response()->noContent();
    }
}
