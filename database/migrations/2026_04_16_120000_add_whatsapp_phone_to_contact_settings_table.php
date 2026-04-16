<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_settings')) {
            return;
        }

        if (! Schema::hasColumn('contact_settings', 'whatsapp_phone')) {
            Schema::table('contact_settings', function (Blueprint $table): void {
                $table->string('whatsapp_phone')->nullable()->after('phone');
            });
        }

        DB::table('contact_settings')
            ->whereNull('whatsapp_phone')
            ->update(['whatsapp_phone' => '212699464280']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('contact_settings') || ! Schema::hasColumn('contact_settings', 'whatsapp_phone')) {
            return;
        }

        Schema::table('contact_settings', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_phone');
        });
    }
};
