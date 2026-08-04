<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\PrometeoContact;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(?User $user = null): User
    {
        return tap($user ?? User::factory()->create(), fn (User $u) => Sanctum::actingAs($u));
    }

    /**
     * @return array{0: Company, 1: User, 2: User}
     */
    private function companyWithAdminAndWorker(): array
    {
        $admin = $this->actingAsUser();
        $company = Company::create(['name' => 'Acme Srl', 'owner_user_id' => $admin->id]);
        CompanyMembership::factory()->for($company)->for($admin)->admin()->create();
        $worker = $this->actingAsUser();
        CompanyMembership::factory()->for($company)->for($worker)->create();

        return [$company, $admin, $worker];
    }

    public function test_worker_opens_a_thread_with_its_first_message(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();

        $this->postJson("/api/companies/{$company->id}/support-threads", [
            'channel' => 'chat',
            'body' => 'Ho un problema con il portale',
        ])->assertCreated()
            ->assertJsonPath('data.channel', 'chat')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonCount(1, 'data.messages');

        $thread = SupportThread::firstOrFail();
        $this->assertNotNull($thread->last_message_at);
        $this->assertDatabaseHas('support_messages', [
            'support_thread_id' => $thread->id,
            'sender_id' => $worker->id,
            'body' => 'Ho un problema con il portale',
        ]);
    }

    public function test_reply_updates_last_message_at(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();
        $thread = SupportThread::factory()->for($company)->create([
            'opened_by_id' => $worker->id,
            'last_message_at' => now()->subDay(),
        ]);

        $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'Risposta del lavoratore'])
            ->assertCreated()->assertJsonPath('data.body', 'Risposta del lavoratore');

        $this->assertTrue($thread->fresh()->last_message_at->gt(now()->subMinute()));
    }

    public function test_read_receipt_marks_only_messages_from_others(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();
        $colleague = $this->actingAsUser();
        CompanyMembership::factory()->for($company)->for($colleague)->create();

        $thread = SupportThread::factory()->for($company)->create(['opened_by_id' => $worker->id]);
        $mine = SupportMessage::factory()->for($thread)->create(['sender_id' => $worker->id]);
        $theirs = SupportMessage::factory()->for($thread)->create(['sender_id' => $colleague->id]);

        Sanctum::actingAs($worker);
        $this->postJson("/api/threads/{$thread->id}/read")->assertNoContent();

        $this->assertNull($mine->fresh()->read_at);
        $this->assertNotNull($theirs->fresh()->read_at);
    }

    public function test_member_closes_a_thread(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();
        $thread = SupportThread::factory()->for($company)->create(['opened_by_id' => $worker->id]);

        $this->patchJson("/api/threads/{$thread->id}")->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertNotNull($thread->fresh()->closed_at);
    }

    public function test_operator_sees_open_threads_across_every_company(): void
    {
        [$company] = $this->companyWithAdminAndWorker();
        [$otherCompany] = $this->companyWithAdminAndWorker();

        SupportThread::factory()->for($company)->create();
        SupportThread::factory()->for($otherCompany)->create();
        SupportThread::factory()->for($company)->closed()->create();

        $this->actingAsUser(User::factory()->operator()->create());

        $this->getJson('/api/operator/support-threads')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Operators also reach any single thread through the before() override.
        $thread = SupportThread::where('status', 'open')->firstOrFail();
        $this->getJson("/api/threads/{$thread->id}")->assertOk();
    }

    public function test_non_member_cannot_access_threads(): void
    {
        [$company, , $worker] = $this->companyWithAdminAndWorker();
        $thread = SupportThread::factory()->for($company)->create(['opened_by_id' => $worker->id]);

        $this->actingAsUser();

        $this->getJson("/api/companies/{$company->id}/support-threads")->assertForbidden();
        $this->getJson("/api/threads/{$thread->id}")->assertForbidden();
        $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'Intrusione'])->assertForbidden();
    }

    public function test_prometeo_contacts_catalog_lists_only_visible_staff(): void
    {
        $visible = PrometeoContact::factory()->create(['position' => 1]);
        PrometeoContact::factory()->hidden()->create();

        $this->actingAsUser();

        $this->getJson('/api/prometeo-contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.display_name', $visible->display_name);
    }
}
