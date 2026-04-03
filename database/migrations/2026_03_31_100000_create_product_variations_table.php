<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variations')) {
            Schema::create('product_variations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('value');
                $table->string('sku')->nullable();
                $table->decimal('price', 10, 2);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['product_id', 'sku']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
