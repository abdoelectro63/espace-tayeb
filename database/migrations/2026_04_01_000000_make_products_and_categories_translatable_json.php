<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        $productRows = DB::table('products')
            ->select('id', 'name', 'description', 'slug')
            ->get();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['name', 'description', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            // SQLite: adding NOT NULL columns to existing rows fails; fill then enforce on MySQL if needed.
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->json('slug')->nullable();
        });

        foreach ($productRows as $row) {
            $name = (string) $row->name;
            $slug = (string) $row->slug;
            $desc = $row->description;

            DB::table('products')->where('id', $row->id)->update([
                'name' => json_encode(['ar' => $name, 'fr' => $name], JSON_UNESCAPED_UNICODE),
                'slug' => json_encode(['ar' => $slug, 'fr' => $slug], JSON_UNESCAPED_UNICODE),
                'description' => $desc !== null
                    ? json_encode(['ar' => (string) $desc, 'fr' => (string) $desc], JSON_UNESCAPED_UNICODE)
                    : null,
            ]);
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        $categoryRows = DB::table('categories')
            ->select('id', 'name', 'slug')
            ->get();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name', 'slug']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->json('slug')->nullable();
        });

        foreach ($categoryRows as $row) {
            $name = (string) $row->name;
            $slug = (string) $row->slug;

            DB::table('categories')->where('id', $row->id)->update([
                'name' => json_encode(['ar' => $name, 'fr' => $name], JSON_UNESCAPED_UNICODE),
                'slug' => json_encode(['ar' => $slug, 'fr' => $slug], JSON_UNESCAPED_UNICODE),
                'description' => null,
            ]);
        }
    }

    public function down(): void
    {
        $productRows = DB::table('products')
            ->select('id', 'name', 'description', 'slug')
            ->get();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['name', 'description', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->nullable()->unique();
        });

        foreach ($productRows as $row) {
            $name = json_decode((string) $row->name, true);
            $slug = json_decode((string) $row->slug, true);
            $desc = $row->description !== null ? json_decode((string) $row->description, true) : null;

            DB::table('products')->where('id', $row->id)->update([
                'name' => is_array($name) ? (string) ($name['ar'] ?? $name['fr'] ?? '') : (string) $row->name,
                'slug' => is_array($slug) ? (string) ($slug['ar'] ?? $slug['fr'] ?? '') : (string) $row->slug,
                'description' => is_array($desc) ? (string) ($desc['ar'] ?? $desc['fr'] ?? '') : null,
            ]);
        }

        $categoryRows = DB::table('categories')
            ->select('id', 'name', 'description', 'slug')
            ->get();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name', 'description', 'slug']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('name');
            $table->string('slug')->unique();
        });

        foreach ($categoryRows as $row) {
            $name = json_decode((string) $row->name, true);
            $slug = json_decode((string) $row->slug, true);

            DB::table('categories')->where('id', $row->id)->update([
                'name' => is_array($name) ? (string) ($name['ar'] ?? $name['fr'] ?? '') : (string) $row->name,
                'slug' => is_array($slug) ? (string) ($slug['ar'] ?? $slug['fr'] ?? '') : (string) $row->slug,
            ]);
        }
    }
};
