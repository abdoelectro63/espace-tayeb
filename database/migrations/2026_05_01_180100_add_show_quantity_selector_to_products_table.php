<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'show_quantity_selector')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('show_quantity_selector')
                    ->default(true)
                    ->after('cta_mode');
            });
        }

        DB::table('products')
            ->whereNull('show_quantity_selector')
            ->update(['show_quantity_selector' => 1]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'show_quantity_selector')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('show_quantity_selector');
            });
        }
    }
};
