<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Enums\UserType;
use App\Enums\WorkspaceKind;
use App\Models\Company;
use App\Models\OrgRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One employer with a populated org chart, so the app's "Organigramma" tab
 * (prototype 250) has something to list. The cast is the prototype's own.
 *
 * Not part of DatabaseSeeder: it is demo data. Run it on purpose:
 *   sail artisan db:seed --class=DemoCompanySeeder
 */
class DemoCompanySeeder extends Seeder
{
    /** Shared by every account below, demo data only. */
    private const PASSWORD = 'password';

    public function run(): void
    {
        // The chart references roles by code, so they have to exist first.
        $this->call(OrgRoleSeeder::class);

        $employer = $this->user('demo@prometeo.test', 'Datore', 'Demo');

        $company = Company::firstOrCreate(
            ['name' => 'Prometeo Demo Srl'],
            [
                'kind' => WorkspaceKind::Business,
                'owner_user_id' => $employer->getKey(),
                'vat_number' => '01234567890',
                'legal_address' => 'Via Demo 1',
                'postal_code' => '90100',
                'city' => 'Palermo',
                'province' => 'PA',
                'size_band' => 'piccola',
                'status' => 'active',
            ],
        );

        // The employer's own membership has to be Active: GET /companies lists
        // only active memberships, and that is how the app finds the workspace.
        $this->member($company, $employer, ['datore_lavoro'], MembershipStatus::Active, isAdmin: true);

        $cast = [
            ['Fiorenza', 'Loretti', ['lavoratore'], MembershipStatus::Active],
            ['Francesco', 'Saporito', ['lavoratore'], MembershipStatus::Active],
            ['Francesca', 'Marzullo', ['lavoratore'], MembershipStatus::Active],
            ['Giulia', 'Rossellini', ['medico_competente'], MembershipStatus::Active],
            ['Pietro', 'Golia', ['dirigente'], MembershipStatus::Active],
            ['Roberto', 'Lo Piccolo', ['lavoratore', 'rls'], MembershipStatus::Active],
            ['Simone', 'De Francesco', ['lavoratore'], MembershipStatus::Active],
            ['Valeria', 'Rossellini', ['lavoratore'], MembershipStatus::Active],
            // Appointed but never signed in: on the chart, no access yet.
            ['Marta', 'Bruno', ['rspp'], MembershipStatus::Invited],
            ['Antonio', 'Greco', ['aspp'], MembershipStatus::Invited],
            // Left the company: only the "archiviati" filter reaches them.
            ['Marco', 'Bianchi', ['lavoratore'], MembershipStatus::Archived],
        ];

        foreach ($cast as [$name, $surname, $roleCodes, $status]) {
            $email = str($name.'.'.$surname)->slug('.')->append('@prometeo.test')->value();
            $this->member($company, $this->user($email, $name, $surname), $roleCodes, $status);
        }

        // Every member gets the six folders of prototype 258.
        (new PersonalFoldersSeeder)->setCommand($this->command)->run($company);

        $this->command->info('Employer: demo@prometeo.test / '.self::PASSWORD);
    }

    private function user(string $email, string $name, string $surname): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'surname' => $surname,
                'password' => Hash::make(self::PASSWORD),
                'type' => UserType::CompanyUser,
            ],
        );
    }

    /**
     * @param  array<int, string>  $roleCodes
     */
    private function member(
        Company $company,
        User $user,
        array $roleCodes,
        MembershipStatus $status,
        bool $isAdmin = false,
    ): void {
        $membership = $company->memberships()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['status' => $status, 'is_admin' => $isAdmin],
        );

        $membership->orgRoles()->sync(
            OrgRole::whereIn('code', $roleCodes)->pluck('id')->all(),
            ['appointed_at' => now()->toDateString()],
        );
    }
}
