<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table): void {
            $table->string('invoice_designation')->nullable()->after('unit_price');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('invoice_client_company_name')->nullable()->after('invoiced_at');
            $table->string('invoice_client_ice')->nullable()->after('invoice_client_company_name');
            $table->string('invoice_client_if')->nullable()->after('invoice_client_ice');
            $table->string('invoice_client_rc')->nullable()->after('invoice_client_if');
            $table->text('invoice_billing_address')->nullable()->after('invoice_client_rc');
        });

        Schema::table('invoice_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('invoice_settings', 'default_client_company_name')) {
                $table->dropColumn('default_client_company_name');
            }
            if (Schema::hasColumn('invoice_settings', 'default_client_ice')) {
                $table->dropColumn('default_client_ice');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table): void {
            $table->dropColumn('invoice_designation');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'invoice_client_company_name',
                'invoice_client_ice',
                'invoice_client_if',
                'invoice_client_rc',
                'invoice_billing_address',
            ]);
        });

        Schema::table('invoice_settings', function (Blueprint $table): void {
            $table->string('default_client_company_name')->nullable();
            $table->string('default_client_ice')->nullable();
        });
    }
};
