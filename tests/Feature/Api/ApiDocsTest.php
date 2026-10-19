<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_are_public_and_list_only_events_the_system_fires(): void
    {
        $this->get(route('docs.api'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Docs/Api')
                ->where('baseUrl', url('/api/v1'))
                ->where('events', fn ($events) => collect($events)->pluck('key')->sort()->values()->all() === [
                    'document.completed', 'document.rejected', 'document.sent', 'document.signed', 'document.viewed',
                    'invoice.cancellation_denied', 'invoice.cancelled', 'invoice.certificate_expiring', 'invoice.issued', 'invoice.received', 'invoice.rejected', 'invoice.replaced', 'invoice.taker_rejected',
                ]));
    }
}
