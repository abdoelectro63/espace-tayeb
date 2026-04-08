<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('invoice_status', 32)->default('not_invoiced')->after('payment_status');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_status');
        });

        Schema::create('invoice_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('seller_company_name')->nullable();
            $table->text('seller_address')->nullable();
            $table->string('seller_ice')->nullable();
            $table->string('seller_if')->nullable();
            $table->string('seller_rc')->nullable();
            $table->string('seller_patente')->nullable();
            $table->string('seller_rib')->nullable();
            $table->decimal('default_tva_rate', 5, 2)->default(14);
            $table->string('default_client_company_name')->nullable();
            $table->string('default_client_ice')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['invoice_status', 'invoiced_at']);
        });
    }
};
