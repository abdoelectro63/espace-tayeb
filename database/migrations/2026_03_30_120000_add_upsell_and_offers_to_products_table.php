<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('upsell_id')
                ->nullable()
                ->after('category_id')
                ->constrained('products')
                ->nullOnDelete();

            $table->string('offer_type', 32)
                ->default('none')
                ->after('free_shipping');

            $table->decimal('offer_value', 8, 2)
                ->nullable()
                ->after('offer_type');

            $table->index('offer_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['upsell_id']);
            $table->dropIndex(['offer_type']);
            $table->dropColumn(['upsell_id', 'offer_type', 'offer_value']);
        });
    }
};
