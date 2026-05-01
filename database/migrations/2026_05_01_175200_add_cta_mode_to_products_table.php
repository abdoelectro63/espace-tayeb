<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'cta_mode')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('cta_mode', 40)
                    ->default('add_to_cart_and_buy_now')
                    ->after('offer_value');
            });
        }

        DB::table('products')
            ->whereNull('cta_mode')
            ->update(['cta_mode' => 'add_to_cart_and_buy_now']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'cta_mode')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('cta_mode');
            });
        }
    }
};
