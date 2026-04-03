<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_company_cities')) {
            Schema::create('shipping_company_cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipping_company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->json('aliases')->nullable()->comment('Alternative spellings for matching order.city');
                $table->string('vitips_label')->nullable()->comment('City string sent to Vitips; defaults to name');
                $table->string('express_city_code', 32)->nullable()->comment('Numeric code for Express Coursier');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['shipping_company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('order_carrier_city_selections')) {
            Schema::create('order_carrier_city_selections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('shipping_company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('shipping_company_city_id')->nullable()->constrained('shipping_company_cities')->nullOnDelete();
                $table->timestamps();

                $table->unique(['order_id', 'shipping_company_id'], 'occs_order_carrier_uidx');
            });
        } elseif (! Schema::hasIndex('order_carrier_city_selections', 'occs_order_carrier_uidx')) {
            Schema::table('order_carrier_city_selections', function (Blueprint $table) {
                $table->unique(['order_id', 'shipping_company_id'], 'occs_order_carrier_uidx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_carrier_city_selections');
        Schema::dropIfExists('shipping_company_cities');
    }
};
