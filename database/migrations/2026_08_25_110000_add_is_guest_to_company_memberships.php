<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_memberships', function (Blueprint $table) {
            // The client's own word: a "guest" is an outside professional or company the
            // datore di lavoro shared a node with. Sharing lets them into the workspace
            // (CompanyMembership::ensureFor) but never onto the org chart, and the two
            // screens over the same grants are split along exactly this line:
            // "condividi" addresses guests, "gestisci accesso" the org chart.
            $table->boolean('is_guest')->default(false)->after('is_admin')->index();
        });
    }

    public function down(): void
    {
        Schema::table('company_memberships', function (Blueprint $table) {
            $table->dropColumn('is_guest');
        });
    }
};
