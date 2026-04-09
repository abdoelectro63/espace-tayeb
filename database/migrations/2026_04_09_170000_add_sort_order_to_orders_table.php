<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'sort_order')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('sort_order')->nullable()->after('number');
                $table->index('sort_order');
            });
        }

        // Keep existing orders stable by initializing sort_order once.
        DB::table('orders')
            ->whereNull('sort_order')
            ->update([
                'sort_order' => DB::raw('id'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'sort_order')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex(['sort_order']);
                $table->dropColumn('sort_order');
            });
        }
    }
};
