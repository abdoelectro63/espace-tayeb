<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'long_description')) {
                $table->longText('long_description')->nullable()->after('description');
            }

            if (! Schema::hasColumn('products', 'specifications')) {
                $table->json('specifications')->nullable()->after('long_description');
            }

            if (! Schema::hasColumn('products', 'detail_images')) {
                $table->json('detail_images')->nullable()->after('images');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'detail_images')) {
                $table->dropColumn('detail_images');
            }

            if (Schema::hasColumn('products', 'specifications')) {
                $table->dropColumn('specifications');
            }

            if (Schema::hasColumn('products', 'long_description')) {
                $table->dropColumn('long_description');
            }
        });
    }
};
