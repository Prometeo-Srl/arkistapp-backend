<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\WorkspaceKind;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceFor(User $owner, bool $withSecondMember = false): Company
    {
        $company = Company::create([
            'name' => 'Edil Costruzioni',
            'kind' => WorkspaceKind::Business,
            'owner_user_id' => $owner->getKey(),
        ]);

        CompanyMembership::create([
            'company_id' => $company->getKey(),
            'user_id' => $owner->getKey(),
            'status' => MembershipStatus::Active,
            'is_admin' => true,
        ]);

        if ($withSecondMember) {
            CompanyMembership::create([
                'company_id' => $company->getKey(),
                'user_id' => User::factory()->create()->getKey(),
                'status' => MembershipStatus::Active,
                'is_admin' => false,
            ]);
        }

        return $company;
    }

    public function test_it_soft_deletes_the_account_archives_memberships_and_frees_the_email(): void
    {
        $user = User::factory()->create(['email' => 'lavoratore@gmail.com']);
        $this->workspaceFor($user);
        $user->createToken('other-device');
        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/me')->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);
        $this->assertDatabaseMissing('users', ['email' => 'lavoratore@gmail.com']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('company_memberships', [
            'user_id' => $user->getKey(),
            'status' => MembershipStatus::Archived->value,
        ]);

        // The address is free again: nothing stops a fresh registration on it.
        $this->assertTrue(User::withTrashed()->where('email', 'lavoratore@gmail.com')->doesntExist());
    }

    public function test_it_refuses_while_the_account_owns_a_workspace_other_people_still_work_in(): void
    {
        $user = User::factory()->create();
        $this->workspaceFor($user, withSecondMember: true);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/me')
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');

        $this->assertNotSoftDeleted('users', ['id' => $user->getKey()]);
    }

    public function test_it_requires_authentication(): void
    {
        $this->deleteJson('/api/auth/me')->assertUnauthorized();
    }
}
