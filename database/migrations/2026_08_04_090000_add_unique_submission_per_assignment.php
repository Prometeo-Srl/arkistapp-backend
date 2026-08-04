<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One submission per assignment: the relation is a HasOne and the controller
        // relies on it. Enforced in the database so no future caller can duplicate it.
        DB::statement('
            DELETE FROM checklist_submissions
            WHERE id NOT IN (
                SELECT MIN(id) FROM checklist_submissions GROUP BY checklist_assignment_id
            )
        ');

        Schema::table('checklist_submissions', function (Blueprint $table) {
            $table->unique('checklist_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_submissions', function (Blueprint $table) {
            $table->dropUnique(['checklist_assignment_id']);
        });
    }
};
