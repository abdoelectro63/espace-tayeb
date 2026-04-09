<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->date('invoice_date');
            $table->string('client_company_name')->nullable();
            $table->string('client_ice')->nullable();
            $table->string('client_if')->nullable();
            $table->string('client_rc')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('manual_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manual_invoice_id')->constrained('manual_invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('designation');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_invoice_lines');
        Schema::dropIfExists('manual_invoices');
    }
};
