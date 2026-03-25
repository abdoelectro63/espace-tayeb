<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_fee', 10, 2)->default(0)->after('city');
            $table->string('shipping_zone', 32)->nullable()->after('shipping_fee');
        });

        DB::table('orders')->where('city', 'Casablanca')->update(['shipping_zone' => 'casablanca']);
        DB::table('orders')->whereNotNull('city')->where('city', '!=', 'Casablanca')->update(['shipping_zone' => 'other']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_fee', 'shipping_zone']);
        });
    }
};
