<?php

namespace App\Services;

use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderImportService
{
    /**
     * @return list<array<string, string>>
     */
    public function parseCsvToRows(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if ($path === false || ! is_readable($path)) {
            throw new RuntimeException('تعذر قراءة ملف CSV.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('تعذر فتح ملف CSV.');
        }

        try {
            $headerLine = fgetcsv($handle);
            if ($headerLine === false || $headerLine === []) {
                throw new RuntimeException('ملف CSV فارغ.');
            }

            /** @var list<string> */
            $headers = [];
            foreach ($headerLine as $h) {
                $raw = (string) $h;
                $canonical = $this->canonicalKeyFromHeader($raw);
                $headers[] = $canonical ?? $this->normalizeHeader($raw);
            }

            $rows = [];

            while (($line = fgetcsv($handle)) !== false) {
                if ($line === null || $line === [null] || $line === []) {
                    continue;
                }
                $row = [];
                foreach ($headers as $i => $key) {
                    if ($key === '') {
                        continue;
                    }
                    $val = trim((string) ($line[$i] ?? ''));
                    if ($val === '') {
                        continue;
                    }
                    if (! isset($row[$key])) {
                        $row[$key] = $val;
                    }
                }
                if ($this->rowIsEmpty($row)) {
                    continue;
                }
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Enrich raw CSV rows with suggested product_id / product_variation_id from SKU + variation text.
     *
     * @param  list<array<string, string>>  $csvRows
     * @return list<array<string, mixed>>
     */
    public function buildMappingRows(array $csvRows): array
    {
        $products = Product::query()
            ->with('variations')
            ->where('is_active', true)
            ->get();

        $byCode = $products->keyBy(fn (Product $p): string => Str::lower((string) $p->code));
        $bySku = [];
        foreach ($products as $p) {
            foreach ($p->variations as $v) {
                if (filled($v->sku)) {
                    $bySku[Str::lower((string) $v->sku)] = ['product_id' => $p->id, 'variation_id' => $v->id];
                }
            }
        }

        $out = [];
        foreach ($csvRows as $row) {
            $rawRef = $this->pickFirst($row, ['product_sku', 'sku', 'code', 'product_code']);
            $sku = $this->extractProductCodeFromReference($rawRef);
            $variationText = $this->pickFirst($row, ['variation', 'variant', 'option']);

            $productId = null;
            $variationId = null;

            if ($sku !== '') {
                $product = $byCode->get(Str::lower($sku));
                if ($product === null && $rawRef !== '') {
                    $product = $byCode->get(Str::lower(trim($rawRef)));
                }
                if ($product !== null) {
                    $productId = $product->id;
                    if ($variationText !== '' && $product->variations->isNotEmpty()) {
                        $variationId = $this->matchVariation($product, $variationText);
                    } elseif ($product->variations->isNotEmpty()) {
                        $variationId = $product->getDefaultVariation()?->id;
                    }
                } else {
                    $pair = $bySku[Str::lower($sku)] ?? null;
                    if (is_array($pair)) {
                        $productId = $pair['product_id'];
                        $variationId = $pair['variation_id'];
                    }
                }
            }

            $out[] = [
                ...$row,
                'product_id' => $productId,
                'product_variation_id' => $variationId,
                '_raw_sku' => $rawRef !== '' ? $rawRef : $sku,
                '_raw_variation' => $variationText,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function createOrdersFromMappings(array $rows): int
    {
        $count = 0;

        DB::transaction(function () use ($rows, &$count): void {
            foreach ($rows as $row) {
                $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
                if ($productId < 1) {
                    continue;
                }

                $product = Product::query()->with('variations')->find($productId);
                if ($product === null) {
                    continue;
                }

                $variationId = isset($row['product_variation_id']) ? (int) $row['product_variation_id'] : null;
                if ($variationId !== null) {
                    $v = $product->variations->firstWhere('id', $variationId);
                    if ($v === null) {
                        $variationId = null;
                    }
                }
                if ($product->variations->isNotEmpty() && $variationId === null) {
                    $variationId = $product->getDefaultVariation()?->id;
                }

                $qty = (int) ($this->pickFirst($row, ['quantity', 'qty', 'count']) ?: '1');
                $qty = max(1, min(999, $qty));

                $unit = $this->pickFirst($row, ['unit_price', 'price', 'amount']);
                if ($unit !== '' && is_numeric($unit)) {
                    $unitPrice = round((float) $unit, 2);
                } else {
                    $unitPrice = $product->finalUnitPriceForCart($variationId);
                }

                $name = $this->pickFirst($row, ['customer_name', 'name', 'client']);
                $phone = $this->pickFirst($row, ['customer_phone', 'phone', 'tel']);
                $city = $this->pickFirst($row, ['city', 'ville']);
                $address = $this->pickFirst($row, ['shipping_address', 'address', 'adresse']);
                $zone = $this->pickFirst($row, ['shipping_zone', 'zone']);
                $zone = $zone === 'other' ? 'other' : 'casablanca';

                if ($name === '' || $phone === '' || $address === '') {
                    continue;
                }

                $order = Order::query()->create([
                    'number' => $this->generateUniqueOrderNumber(),
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'city' => $city !== '' ? $city : ($zone === 'casablanca' ? 'الدار البيضاء' : ''),
                    'shipping_address' => $address,
                    'shipping_zone' => $zone,
                    'shipping_fee' => 0,
                    'total_price' => 0,
                    'status' => 'pending',
                    'notes' => $this->pickFirst($row, ['notes', 'note', 'remarque']) ?: null,
                ]);

                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variation_id' => $variationId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ]);

                $itemsForCalc = [[
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ]];
                $fee = ShippingCalculator::feeForAdminOrderItems($itemsForCalc, $zone);
                $total = round(OrderForm::calculateTotalFromItems($itemsForCalc) + $fee, 2);

                $order->update([
                    'shipping_fee' => $fee,
                    'total_price' => $total,
                ]);

                $count++;
            }
        });

        return $count;
    }

    private function generateUniqueOrderNumber(): string
    {
        do {
            $number = 'ET-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }

    private function normalizeHeader(string $h): string
    {
        return Str::lower(trim(str_replace([' ', '-'], '_', $h)));
    }

    /**
     * Map bilingual / Google Forms / Vitips-style headers to keys used by the import UI and {@see pickFirst()}.
     */
    private function canonicalKeyFromHeader(string $raw): ?string
    {
        $ascii = Str::lower(Str::ascii(trim($raw)));
        $ascii = preg_replace('/\s+/', ' ', $ascii) ?? '';

        $rawLower = Str::lower($raw);
        if (str_contains($raw, 'الهاتف') || str_contains($rawLower, 'téléphone') || str_contains($rawLower, 'telephone')) {
            return 'customer_phone';
        }
        if (str_contains($raw, 'المدينة')) {
            return 'city';
        }
        if (str_contains($raw, 'العنوان')) {
            return 'shipping_address';
        }
        if (str_contains($raw, 'الاسم') && ! preg_match('/form\s*name/i', $ascii)) {
            return 'customer_name';
        }
        if (str_contains($raw, 'اختر') || str_contains($raw, 'الحجم') || str_contains($raw, 'taille')) {
            return 'variation';
        }

        if (preg_match('/form\s*name/i', $ascii) || str_contains($ascii, 'form_name')) {
            return 'product_sku';
        }
        if (preg_match('/submission\s*id/i', $ascii)) {
            return 'submission_id';
        }
        if (preg_match('/created\s*at/i', $ascii)) {
            return 'created_at';
        }
        if (preg_match('/telephone|phone|tel|mobile/', $ascii)) {
            return 'customer_phone';
        }
        if (preg_match('/\bville\b|\bcity\b/', $ascii)) {
            return 'city';
        }
        if (preg_match('/adresse|address|rue|street/', $ascii)) {
            return 'shipping_address';
        }
        if (preg_match('/taille|size|choisissez|variation|variant/', $ascii)) {
            return 'variation';
        }
        if (preg_match('/\b(nom|name)\b/', $ascii) && ! preg_match('/form\s*name/', $ascii)) {
            return 'customer_name';
        }
        if (preg_match('/quantity|qty|count|nombre/', $ascii)) {
            return 'quantity';
        }
        if (preg_match('/unit_price|montant/', $ascii) || (preg_match('/\bprice\b|\bamount\b/', $ascii) && ! preg_match('/taille/', $ascii))) {
            return 'unit_price';
        }
        if (preg_match('/notes?|remarque|comment/', $ascii)) {
            return 'notes';
        }
        if (preg_match('/shipping_zone|\bzone\b/', $ascii)) {
            return 'shipping_zone';
        }
        if (preg_match('/\b(sku|code|ref|reference)\b/', $ascii) && ! preg_match('/form\s*name/', $ascii)) {
            return 'product_sku';
        }

        return null;
    }

    /**
     * Values like "chabka inox (634eeb7d)" → product code "634eeb7d".
     */
    private function extractProductCodeFromReference(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match_all('/\(([a-zA-Z0-9_-]+)\)/', $value, $matches)) {
            $last = end($matches[1]);

            return Str::lower((string) $last);
        }

        return Str::lower($value);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    private function pickFirst(array $row, array $keys): string
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
                return trim((string) $row[$k]);
            }
        }

        return '';
    }

    private function matchVariation(Product $product, string $text): ?int
    {
        $t = Str::lower(trim($text));
        $t = trim(preg_replace('/\s*:\s*\d[\d\s.,DHdhMADdhم]*$/u', '', $t) ?? $t);

        foreach ($product->variations as $v) {
            $val = Str::lower(trim((string) $v->value));
            $label = Str::lower($v->label());
            if ($val === $t || $label === $t) {
                return (int) $v->id;
            }
            if ($val !== '' && (str_contains($t, $val) || str_contains($val, $t))) {
                return (int) $v->id;
            }
            if (filled($v->sku) && Str::lower((string) $v->sku) === $t) {
                return (int) $v->id;
            }
        }

        return $product->getDefaultVariation()?->id;
    }
}
