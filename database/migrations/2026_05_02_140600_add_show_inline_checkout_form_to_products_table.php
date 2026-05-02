<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'show_inline_checkout_form')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('show_inline_checkout_form')
                    ->default(false)
                    ->after('show_quantity_selector');
            });
        }

        DB::table('products')
            ->whereNull('show_inline_checkout_form')
            ->update(['show_inline_checkout_form' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'show_inline_checkout_form')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('show_inline_checkout_form');
            });
        }
    }
};
