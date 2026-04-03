<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_cities')) {
            Schema::create('shipping_cities', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('vitips_label')->nullable()->comment('Exact city string for Vitips API');
                $table->string('express_city_code', 32)->nullable()->comment('Numeric city code for Express Coursier');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'shipping_city_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('shipping_city_id')
                    ->nullable()
                    ->after('city')
                    ->constrained('shipping_cities')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_city_id');
        });

        Schema::dropIfExists('shipping_cities');
    }
};
