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
        if (! Schema::hasColumn('products', 'code')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('code')->nullable()->after('name');
            });
        }

        $usedCodes = DB::table('products')
            ->whereNotNull('code')
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->all();

        $used = array_fill_keys($usedCodes, true);

        $products = DB::table('products')
            ->select('id', 'name', 'code')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            if (filled($product->code)) {
                continue;
            }

            $prefix = $this->prefixFromName((string) $product->name);
            $candidate = $this->uniqueCandidate($prefix, $used);

            DB::table('products')
                ->where('id', $product->id)
                ->update(['code' => $candidate]);

            $used[$candidate] = true;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    private function prefixFromName(string $name): string
    {
        $normalized = Str::lower(Str::ascii($name));
        $lettersOnly = preg_replace('/[^a-z0-9]/', '', $normalized) ?: '';
        $prefix = substr($lettersOnly, 0, 3);

        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'x');
        }

        return $prefix;
    }

    /**
     * @param  array<string,bool>  $used
     */
    private function uniqueCandidate(string $prefix, array $used): string
    {
        do {
            $candidate = $prefix.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (isset($used[$candidate]));

        return $candidate;
    }
};
