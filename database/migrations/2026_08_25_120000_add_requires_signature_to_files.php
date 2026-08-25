<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "azioni richieste" lists two independent duties: presa visione and firma.
        // The acknowledgement carries a signature_path, but nothing said whether a
        // signature was ever required — that is this flag.
        Schema::table('files', function (Blueprint $table) {
            $table->boolean('requires_signature')->default(false)->after('requires_acknowledgement');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('requires_signature');
        });
    }
};
