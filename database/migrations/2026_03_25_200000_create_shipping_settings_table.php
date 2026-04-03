<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_settings')) {
            Schema::create('shipping_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('casablanca_fee', 10, 2)->default(20);
                $table->decimal('other_cities_fee', 10, 2)->default(40);
                $table->timestamps();
            });

            DB::table('shipping_settings')->insert([
                'casablanca_fee' => 20,
                'other_cities_fee' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_settings');
    }
};
