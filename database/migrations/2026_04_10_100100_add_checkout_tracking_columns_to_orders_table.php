<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('checkout_capi_meta_sent_at')->nullable()->after('invoice_billing_address');
            $table->timestamp('checkout_capi_tiktok_sent_at')->nullable()->after('checkout_capi_meta_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['checkout_capi_meta_sent_at', 'checkout_capi_tiktok_sent_at']);
        });
    }
};
