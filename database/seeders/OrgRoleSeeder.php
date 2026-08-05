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
            // Not unique: "inserisci RSPP" offers "+ aggiungi" and the prototype shows two
            // entries, where "inserisci secondo DDL" deliberately offers neither. Flip this
            // back if the client confirms D.Lgs 81/08 allows only one RSPP appointment.
            ['code' => 'rspp', 'label' => 'RSPP', 'min_required' => 1],
            ['code' => 'aspp', 'label' => 'ASPP'],
            ['code' => 'medico_competente', 'label' => 'Medico competente'],
            ['code' => 'rls', 'label' => 'RLS'],
            ['code' => 'dirigente', 'label' => 'Dirigente'],
            ['code' => 'preposto', 'label' => 'Preposto'],
            ['code' => 'lavoratore', 'label' => 'Lavoratore'],
        ];

        foreach ($roles as $position => $role) {
            // Every column is spelled out: updateOrCreate only overwrites the keys it is
            // given, so an omitted flag would keep whatever a previous seed run left
            // behind instead of falling back to the default.
            OrgRole::updateOrCreate(
                ['code' => $role['code']],
                $role + [
                    'position' => $position,
                    'min_required' => 0,
                    'is_unique_per_company' => false,
                ],
            );
        }
    }
}
