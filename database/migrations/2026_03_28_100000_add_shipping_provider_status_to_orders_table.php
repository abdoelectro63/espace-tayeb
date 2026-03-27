<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'shipping_provider_status')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shipping_provider_status', 255)->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'shipping_provider_status')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('shipping_provider_status');
        });
    }
};
