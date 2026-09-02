<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_attachments', function (Blueprint $table) {
            // The name the file was uploaded under, the way `files.name` keeps it:
            // `storage_path` is hashed, and the detail screen has to label the row
            // with something a human recognises. Nullable for the rows that
            // predate it — the client falls back to the stored basename.
            $table->string('name')->nullable()->after('storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('incident_attachments', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
