<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipping_companies', 'token')) {
                $table->string('token')->nullable()->after('color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipping_companies', function (Blueprint $table): void {
            if (Schema::hasColumn('shipping_companies', 'token')) {
                $table->dropColumn('token');
            }
        });
    }
};
