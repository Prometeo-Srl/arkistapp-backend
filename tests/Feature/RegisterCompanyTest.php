<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserType;
use App\Enums\WorkspaceKind;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterCompanyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Edil Costruzioni',
            'vat_number' => '03503579512',
            'legal_address' => 'via del Celso, 12',
            'postal_code' => '00042',
            'city' => 'Roma',
            'province' => 'RM',
            'email' => 'edilcostruzioni@gmail.com',
            'password' => 'Edcostruzioni2025',
            'password_confirmation' => 'Edcostruzioni2025',
            'device_name' => 'test-device',
        ], $overrides);
    }

    public function test_it_creates_the_user_the_business_workspace_and_an_admin_membership(): void
    {
        $response = $this->postJson('/api/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'email'], 'company_id']);

        $user = User::where('email', 'edilcostruzioni@gmail.com')->sole();
        $this->assertNull($user->name, 'Registration never asks for a personal name.');
        $this->assertSame(UserType::CompanyUser, $user->type);
        $this->assertFalse($user->must_change_password, 'The password was chosen by the user.');
        $this->assertTrue(Hash::check('Edcostruzioni2025', $user->password));

        $company = Company::sole();
        $this->assertSame(WorkspaceKind::Business, $company->kind);
        $this->assertSame($user->getKey(), $company->owner_user_id);
        $this->assertNull($company->created_by_operator_id, 'Self-registration, not operator-created.');
        $this->assertSame(
            ['03503579512', 'via del Celso, 12', '00042', 'Roma', 'RM'],
            [$company->vat_number, $company->legal_address, $company->postal_code, $company->city, $company->province],
        );
        $this->assertSame($company->getKey(), $response->json('company_id'));

        $membership = $company->memberships()->sole();
        $this->assertSame($user->getKey(), $membership->user_id);
        $this->assertTrue($membership->is_admin, 'Whoever registers the company administers it.');
        $this->assertSame(MembershipStatus::Active, $membership->status);
    }

    public function test_the_returned_token_authenticates_the_new_user(): void
    {
        $token = $this->postJson('/api/auth/register', $this->payload())->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'edilcostruzioni@gmail.com');
    }

    public function test_it_rejects_a_second_registration_of_the_same_vat_number(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'someone.else@example.com',
        ]))->assertUnprocessable()->assertJsonValidationErrors('vat_number');

        $this->assertSame(1, Company::count());
    }

    public function test_it_lowercases_nothing_but_uppercases_the_province(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['province' => 'rm']))->assertCreated();

        $this->assertSame('RM', Company::sole()->province);
    }

    /**
     * The helper text under both password fields: "minimo 8 caratteri, con una
     * maiuscola e un numero".
     */
    public function test_it_enforces_the_password_policy_shown_in_the_design(): void
    {
        $cases = [
            'Short1' => 'shorter than 8 characters',
            'nouppercase1' => 'no uppercase letter',
            'NoDigitsHere' => 'no digit',
        ];

        foreach ($cases as $password => $why) {
            $response = $this->postJson('/api/auth/register', $this->payload([
                'password' => $password,
                'password_confirmation' => $password,
            ]));

            $this->assertSame(422, $response->status(), "accepted a password with {$why}");
            $this->assertArrayHasKey('password', $response->json('errors'), "accepted a password with {$why}");
        }
    }

    public function test_it_requires_the_password_confirmation_to_match(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'password_confirmation' => 'Somethingelse2025',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_it_rejects_a_vat_number_that_is_not_eleven_digits(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['vat_number' => '035035795']))
            ->assertUnprocessable()->assertJsonValidationErrors('vat_number');
    }

    public function test_every_field_of_both_steps_is_required(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'company_name', 'vat_number', 'legal_address', 'postal_code',
                'city', 'province', 'email', 'password', 'device_name',
            ]);
    }

    public function test_it_writes_nothing_when_the_company_is_invalid(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['postal_code' => 'nope']))
            ->assertUnprocessable();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Company::count());
    }
}
