<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_company_cities', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipping_company_cities', 'vitips_city_id')) {
                $table->string('vitips_city_id', 32)->nullable()->after('vitips_label')
                    ->comment('Numeric id from Vitips <option value="…"> when present; sent as city= in add-colis');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipping_company_cities', function (Blueprint $table): void {
            if (Schema::hasColumn('shipping_company_cities', 'vitips_city_id')) {
                $table->dropColumn('vitips_city_id');
            }
        });
    }
};
