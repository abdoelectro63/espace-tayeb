<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('other_cities_fee');
            $table->string('header_bg_color', 20)->default('#ffffff')->after('logo_path');
            $table->string('menu_text_color', 20)->default('#0f172a')->after('header_bg_color');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'header_bg_color', 'menu_text_color']);
        });
    }
};
