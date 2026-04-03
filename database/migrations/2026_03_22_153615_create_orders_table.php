<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Intentionally empty: the `orders` table is created by
 * 2026_03_22_150236_create_orders_table.php (runs before order_items in the same batch).
 * This file remains so existing migration history stays valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
