<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'slug')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        $rows = DB::table('products')->whereNull('slug')->orWhere('slug', '')->get();
        foreach ($rows as $row) {
            $base = Str::slug($row->name ?? 'product-'.$row->id);
            if ($base === '') {
                $base = 'product-'.$row->id;
            }
            $slug = $base;
            $n = 0;
            while (
                DB::table('products')
                    ->where('slug', $slug)
                    ->where('id', '!=', $row->id)
                    ->exists()
            ) {
                $n++;
                $slug = $base.'-'.$n;
            }
            DB::table('products')->where('id', $row->id)->update(['slug' => $slug]);
        }

        // SQLite: keep slug nullable (NOT NULL alter is awkward); Filament still requires slug on forms.
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
