<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sharing ("Condividi") addresses people by email, and the prototype shows an
 * invitee who has no account yet as pending (the clock badge on 174). Such a
 * grant has no user to point at until they register, so the grantee becomes
 * nullable and the email is kept beside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_grants', function (Blueprint $table) {
            $table->unsignedBigInteger('grantee_id')->nullable()->change();
            $table->string('invited_email')->nullable()->after('grantee_id');
            $table->index('invited_email');
        });
    }

    public function down(): void
    {
        Schema::table('access_grants', function (Blueprint $table) {
            $table->dropIndex(['invited_email']);
            $table->dropColumn('invited_email');
            $table->unsignedBigInteger('grantee_id')->nullable(false)->change();
        });
    }
};
