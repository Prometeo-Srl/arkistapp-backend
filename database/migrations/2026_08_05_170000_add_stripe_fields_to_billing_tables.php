<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // "1 mese prova gratuita" / "7 giorni prova gratuita" on the
            // "attiva il tuo abbonamento" screen; null means no trial.
            $table->unsignedSmallInteger('trial_days')->nullable()->after('billing_period');
            // Stripe Price created lazily on first subscription, then reused.
            $table->string('stripe_price_id')->nullable()->after('is_active');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['trial_days', 'stripe_price_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }
};
