<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            $this->revertProducts();
        }

        if (Schema::hasTable('categories')) {
            $this->revertCategories();
        }
    }

    private function decodeLocaleField(mixed $value, bool $nullable): ?string
    {
        if ($value === null) {
            return $nullable ? null : '';
        }

        $s = (string) $value;
        $d = json_decode($s, true);
        if (is_array($d)) {
            $out = (string) ($d['ar'] ?? $d['fr'] ?? (string) (reset($d) ?: ''));

            return ($out === '' && $nullable) ? null : $out;
        }

        return $s;
    }

    private function revertProducts(): void
    {
        $rows = DB::table('products')->select('id', 'name', 'description', 'slug')->get();

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['name', 'description', 'slug']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->nullable();
        });

        foreach ($rows as $row) {
            DB::table('products')->where('id', $row->id)->update([
                'name' => $this->decodeLocaleField($row->name, false) ?? '',
                'slug' => $this->decodeLocaleField($row->slug, true),
                'description' => $this->decodeLocaleField($row->description, true),
            ]);
        }

        $this->dedupeSlugs('products');

        Schema::table('products', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    private function revertCategories(): void
    {
        $rows = DB::table('categories')->select('id', 'name', 'description', 'slug')->get();

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['name', 'description', 'slug']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug');
        });

        foreach ($rows as $row) {
            DB::table('categories')->where('id', $row->id)->update([
                'name' => $this->decodeLocaleField($row->name, false) ?? '',
                'slug' => $this->decodeLocaleField($row->slug, false) ?? '',
                'description' => $this->decodeLocaleField($row->description, true),
            ]);
        }

        $this->dedupeSlugs('categories');

        Schema::table('categories', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    private function dedupeSlugs(string $table): void
    {
        $dupes = DB::table($table)
            ->select('slug')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->groupBy('slug')
            ->havingRaw('count(*) > 1')
            ->pluck('slug');

        foreach ($dupes as $slug) {
            $ids = DB::table($table)->where('slug', $slug)->orderBy('id')->pluck('id');
            foreach ($ids->slice(1) as $id) {
                DB::table($table)->where('id', $id)->update([
                    'slug' => $slug.'-'.$id,
                ]);
            }
        }
    }
};
