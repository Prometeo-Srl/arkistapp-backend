<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\WorkspaceKind;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * "modifica dati personali" (prototype 080): the anagrafica rows go through
 * PATCH /companies/{company}, the credentials through PATCH /auth/me.
 */
class UpdatePersonalDataTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $attributes = []): array
    {
        $user = User::factory()->create([
            'email' => 'edilcostruzioni@gmail.com',
            'password' => 'Password1',
            ...$attributes,
        ]);

        $company = Company::create([
            'name' => 'Edil Costruzioni',
            'kind' => WorkspaceKind::Business,
            'owner_user_id' => $user->getKey(),
            'vat_number' => '03503579500',
            'legal_address' => 'Via del Celso 12',
            'postal_code' => '00042',
            'city' => 'Roma',
            'province' => 'RM',
        ]);

        CompanyMembership::create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'status' => MembershipStatus::Active,
            'is_admin' => true,
        ]);

        // A real token, not Sanctum::actingAs: the password change spares the
        // caller's own token, which a TransientToken cannot exercise.
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $company, $token];
    }

    public function test_it_composes_the_address_line_the_screen_shows(): void
    {
        [, $company, $token] = $this->actor();

        $this->withToken($token)
            ->getJson("/api/companies/{$company->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.full_address', 'Via del Celso 12, Roma (RM) 00042')
            ->assertJsonPath('data.city', 'Roma')
            ->assertJsonPath('data.province', 'RM')
            ->assertJsonPath('data.postal_code', '00042');
    }

    public function test_the_address_line_is_null_without_a_street(): void
    {
        [, $company, $token] = $this->actor();
        $company->update(['legal_address' => null]);

        $this->withToken($token)
            ->getJson("/api/companies/{$company->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.full_address', null);
    }

    public function test_it_updates_the_anagrafica_rows(): void
    {
        [, $company, $token] = $this->actor();

        $this->withToken($token)
            ->patchJson("/api/companies/{$company->getKey()}", [
                'name' => 'Edil Costruzioni Srl',
                'vat_number' => '12345678901',
                'legal_address' => 'Via Roma 1',
                'postal_code' => '20100',
                'city' => 'Milano',
                'province' => 'mi',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Edil Costruzioni Srl')
            // Normalised on the way in, exactly as registration does it.
            ->assertJsonPath('data.province', 'MI')
            ->assertJsonPath('data.full_address', 'Via Roma 1, Milano (MI) 20100');
    }

    public function test_a_vat_number_another_company_already_holds_is_rejected(): void
    {
        [, $company, $token] = $this->actor();
        Company::create([
            'name' => 'Altra Azienda',
            'kind' => WorkspaceKind::Business,
            'vat_number' => '99999999999',
        ]);

        // Without the unique rule the partial index answers with a 500.
        $this->withToken($token)
            ->patchJson("/api/companies/{$company->getKey()}", ['vat_number' => '99999999999'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vat_number');
    }

    public function test_resubmitting_its_own_vat_number_is_not_a_collision(): void
    {
        [, $company, $token] = $this->actor();

        $this->withToken($token)
            ->patchJson("/api/companies/{$company->getKey()}", ['vat_number' => '03503579500'])
            ->assertOk();
    }

    /**
     * The address only moves through POST /auth/me/email + /verify
     * (ChangeEmailTest): this endpoint must not be a second, unverified way in,
     * even with the current password alongside it.
     */
    public function test_it_ignores_an_email_sent_to_the_profile_endpoint(): void
    {
        [$user, , $token] = $this->actor();

        $this->withToken($token)
            ->patchJson('/api/auth/me', [
                'email' => 'nuova@gmail.com',
                'current_password' => 'Password1',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'edilcostruzioni@gmail.com');

        $this->assertSame('edilcostruzioni@gmail.com', $user->fresh()->email);
    }

    public function test_it_refuses_a_credential_change_without_the_current_password(): void
    {
        [$user, , $token] = $this->actor();

        $this->withToken($token)
            ->patchJson('/api/auth/me', [
                'password' => 'Nuova1234',
                'password_confirmation' => 'Nuova1234',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('Password1', $user->fresh()->password));
    }

    public function test_it_refuses_a_credential_change_with_the_wrong_current_password(): void
    {
        [$user, , $token] = $this->actor();

        $this->withToken($token)
            ->patchJson('/api/auth/me', [
                'password' => 'Nuova1234',
                'password_confirmation' => 'Nuova1234',
                'current_password' => 'Sbagliata1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('Password1', $user->fresh()->password));
    }

    public function test_a_weak_password_is_rejected(): void
    {
        [$user, , $token] = $this->actor();

        $this->withToken($token)
            ->patchJson('/api/auth/me', [
                'password' => 'tuttominuscolo1',
                'password_confirmation' => 'tuttominuscolo1',
                'current_password' => 'Password1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('Password1', $user->fresh()->password));
    }

    public function test_a_password_change_revokes_the_other_devices_but_not_this_one(): void
    {
        [$user, , $token] = $this->actor();
        $otherDevice = $user->createToken('altro telefono');

        $this->withToken($token)
            ->patchJson('/api/auth/me', [
                'password' => 'Nuova1234',
                'password_confirmation' => 'Nuova1234',
                'current_password' => 'Password1',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('Nuova1234', $user->fresh()->password));
        $this->assertNull($user->tokens()->find($otherDevice->accessToken->getKey()));
        // Still signed in on the phone that made the change.
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
    }

    public function test_the_endpoint_is_behind_authentication(): void
    {
        $this->patchJson('/api/auth/me', ['name' => 'Mario'])->assertStatus(401);
    }
}
