<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shipping_companies', 'store_id')) {
            return;
        }

        Schema::table('shipping_companies', function (Blueprint $table): void {
            $table->unsignedBigInteger('store_id')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shipping_companies', 'store_id')) {
            return;
        }

        Schema::table('shipping_companies', function (Blueprint $table): void {
            $table->dropColumn('store_id');
        });
    }
};
