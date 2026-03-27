<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->string('hero_banner_path')->nullable()->after('menu_text_color');
            $table->string('hero_banner_link')->nullable()->after('hero_banner_path');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->dropColumn(['hero_banner_path', 'hero_banner_link']);
        });
    }
};
