<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * validity_months guida il ricalcolo automatico di files.expires_at.
 * reminder_offsets = giorni prima della scadenza in cui parte la notifica.
 */
class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'attestato_antincendio', 'label' => 'Attestato antincendio', 'kind' => 'attestato', 'validity_months' => 60],
            ['code' => 'attestato_primo_soccorso', 'label' => 'Attestato primo soccorso', 'kind' => 'attestato', 'validity_months' => 36],
            ['code' => 'formazione_generale', 'label' => 'Formazione generale lavoratori', 'kind' => 'attestato', 'validity_months' => null],
            ['code' => 'aggiornamento_lavoratori', 'label' => 'Aggiornamento lavoratori', 'kind' => 'attestato', 'validity_months' => 60],
            ['code' => 'aggiornamento_preposto', 'label' => 'Aggiornamento preposto', 'kind' => 'attestato', 'validity_months' => 24],
            ['code' => 'aggiornamento_rspp', 'label' => 'Aggiornamento RSPP', 'kind' => 'attestato', 'validity_months' => 60],
            ['code' => 'aggiornamento_rls', 'label' => 'Aggiornamento RLS', 'kind' => 'attestato', 'validity_months' => 12],
            ['code' => 'visita_medica', 'label' => 'Visita medica periodica', 'kind' => 'visita_medica', 'validity_months' => 12],
            ['code' => 'consegna_dpi', 'label' => 'Verbale consegna DPI', 'kind' => 'dpi', 'validity_months' => null],
            ['code' => 'dvr', 'label' => 'Documento di valutazione dei rischi', 'kind' => 'generic', 'validity_months' => null],
            ['code' => 'nomina', 'label' => 'Lettera di nomina', 'kind' => 'generic', 'validity_months' => null],
            ['code' => 'generico', 'label' => 'Documento generico', 'kind' => 'generic', 'validity_months' => null],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                $type + [
                    'reminder_offsets' => $type['validity_months'] ? [180, 60, 15] : null,
                    'requires_acknowledgement_default' => $type['kind'] === 'dpi',
                ],
            );
        }
    }
}
