<?php

namespace Tests\Feature\Signatures;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Signatures\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Route model binding precisa enxergar o tenant atual antes de buscar o documento. */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_user_cannot_open_document_from_another_workspace(): void
    {
        [$user] = $this->makeTenantWithUser('Acme');
        [, $otherTenant, $otherUser] = $this->makeTenantWithUser('Outra');
        $foreign = $this->makeDocument($otherTenant, $otherUser);

        $this->actingAs($user)->get(route('signatures.show', $foreign))->assertNotFound();
        $this->actingAs($user)->get(route('signatures.download', $foreign))->assertNotFound();
    }

    /** @return array{0: User, 1: Tenant, 2: User} */
    private function makeTenantWithUser(string $name): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4)]);
        $tenant->users()->attach($user, ['role' => 'owner']);
        $module = Module::firstOrCreate(['key' => 'signatures'], ['name' => 'Assinatura de Documentos']);
        $tenant->modules()->attach($module, ['source' => 'manual', 'is_active' => true, 'activated_at' => now()]);

        return [$user, $tenant, $user];
    }

    private function makeDocument(Tenant $tenant, User $creator): Document
    {
        return Document::forceCreate([
            'tenant_id' => $tenant->getKey(),
            'creator_id' => $creator->getKey(),
            'name' => 'Contrato alheio',
            'status' => Document::STATUS_DRAFT,
            'file_path' => 'documents/x.pdf',
            'file_hash' => str_repeat('a', 64),
            'file_size' => 10,
            'page_count' => 1,
        ]);
    }
}
