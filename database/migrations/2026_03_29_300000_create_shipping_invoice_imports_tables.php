<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_invoice_imports')) {
            Schema::create('shipping_invoice_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('carrier_filter', 16)->default('both');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_invoice_import_lines')) {
            Schema::create('shipping_invoice_import_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipping_invoice_import_id')->constrained()->cascadeOnDelete();
                $table->string('carrier', 32);
                $table->string('tracking_key', 191);
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('customer_name')->nullable();
                $table->string('city')->nullable();
                $table->decimal('total_price', 12, 2)->nullable();
                $table->unsignedInteger('invoice_frais')->default(0)->comment('Frais from invoice (DH) — shipping fee line');
                $table->string('etat')->nullable();
                $table->string('match_status', 32);
                $table->timestamp('collected_at')->nullable();
                $table->timestamps();

                $table->index(['shipping_invoice_import_id', 'carrier']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_invoice_import_lines');
        Schema::dropIfExists('shipping_invoice_imports');
    }
};
