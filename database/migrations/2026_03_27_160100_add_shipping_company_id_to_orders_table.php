<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'shipping_company_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('shipping_company_id')
                ->nullable()
                ->after('shipping_company')
                ->constrained('shipping_companies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'shipping_company_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_company_id');
        });
    }
};
