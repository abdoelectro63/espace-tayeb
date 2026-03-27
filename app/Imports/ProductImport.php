<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Support\ImageOptimizer;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

class ProductImport implements SkipsOnError, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    /**
     * @var array<int, Failure>
     */
    protected array $failures = [];

    protected int $importedCount = 0;

    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): ?Product
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($name);
        }

        $sheetPrice = (float) ($row['price'] ?? 0);
        $oldPrice = (float) ($row['old_price'] ?? 0);

        // Sheet mapping:
        // - If old_price is provided and bigger than price, treat:
        //   old_price => original product price, price => discount_price.
        // - Otherwise price remains original and discount is null.
        $productPrice = $sheetPrice;
        $discountPrice = null;
        if ($oldPrice > 0 && $sheetPrice > 0 && $sheetPrice < $oldPrice) {
            $productPrice = $oldPrice;
            $discountPrice = $sheetPrice;
        }

        $qtyRaw = $row['qty'] ?? null;
        $trackStock = filled($qtyRaw);
        $stock = $trackStock ? max(0, (int) $qtyRaw) : 0;

        $categoryId = $this->resolveCategoryId($row['category_id'] ?? null);
        if (! $categoryId) {
            return null;
        }

        $model = Product::query()->firstOrNew(['slug' => $slug]);

        $model->fill([
            'name' => $name,
            'slug' => $slug,
            'description' => (string) ($row['description'] ?? ''),
            'price' => max(0, $productPrice),
            'discount_price' => $discountPrice,
            'category_id' => $categoryId,
            'stock' => $stock,
            'track_stock' => $trackStock,
            'is_active' => true,
        ]);

        $imageUrl = trim((string) ($row['image_url'] ?? ''));
        if ($imageUrl !== '') {
            $optimizedPath = ImageOptimizer::processRemoteImageUrl($imageUrl, 'products/titles');
            if ($optimizedPath !== null) {
                $model->main_image = $optimizedPath;
            }
        }

        $additionalImagesRaw = trim((string) ($row['additional_images'] ?? ''));
        if ($additionalImagesRaw !== '') {
            $additionalPaths = collect(explode(',', $additionalImagesRaw))
                ->map(fn (string $url): string => trim($url))
                ->filter()
                ->map(fn (string $url): ?string => ImageOptimizer::processRemoteImageUrl($url, 'products/gallery'))
                ->filter()
                ->values()
                ->all();

            if ($additionalPaths !== []) {
                $model->images = $additionalPaths;
            }
        }

        $this->importedCount++;

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*.name' => ['required', 'string', 'max:255'],
            '*.slug' => ['nullable', 'string', 'max:255'],
            '*.description' => ['nullable', 'string'],
            '*.price' => ['required', 'numeric', 'min:0'],
            '*.old_price' => ['nullable', 'numeric', 'min:0'],
            '*.qty' => ['nullable', 'integer', 'min:0'],
            '*.category_id' => ['required'],
            '*.image_url' => ['nullable', 'url'],
            '*.additional_images' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<int, Failure>  $failures
     */
    public function onFailure(Failure ...$failures): void
    {
        $this->failures = [...$this->failures, ...$failures];
    }

    public function onError(Throwable $e): void
    {
        // Skipped row errors are intentionally ignored here.
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    public function failuresCount(): int
    {
        return count($this->failures);
    }

    protected function resolveCategoryId(mixed $value): ?int
    {
        if (is_numeric($value)) {
            $id = (int) $value;
            if ($id > 0 && Category::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        return Category::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->value('id');
    }
}
