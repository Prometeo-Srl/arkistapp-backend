<?php

namespace Database\Seeders;

use App\Models\OrgRole;
use Illuminate\Database\Seeder;

/** Org chart roles collected from the "Imposta Organigramma" flow of the company prototype. */
class OrgRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'datore_lavoro', 'label' => 'Datore di lavoro', 'is_unique_per_company' => true, 'min_required' => 1],
            ['code' => 'datore_lavoro_secondario', 'label' => 'Secondo datore di lavoro', 'is_unique_per_company' => true],
            ['code' => 'rspp', 'label' => 'RSPP', 'is_unique_per_company' => true, 'min_required' => 1],
            ['code' => 'aspp', 'label' => 'ASPP'],
            ['code' => 'medico_competente', 'label' => 'Medico competente'],
            ['code' => 'rls', 'label' => 'RLS'],
            ['code' => 'dirigente', 'label' => 'Dirigente'],
            ['code' => 'preposto', 'label' => 'Preposto'],
            ['code' => 'lavoratore', 'label' => 'Lavoratore'],
        ];

        foreach ($roles as $position => $role) {
            OrgRole::updateOrCreate(
                ['code' => $role['code']],
                $role + ['position' => $position, 'min_required' => $role['min_required'] ?? 0],
            );
        }
    }
}
