<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Product;
use App\Services\OrderImportService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

class ImportOrdersCsv extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'استيراد طلبات من CSV';

    protected static ?string $navigationLabel = 'استيراد CSV';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|UnitEnum|null $navigationGroup = 'الطلبيات';

    protected string $view = 'filament.pages.import-orders-csv';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public int $step = 1;

    /** Single product applied to every imported order (variations stay per-row). */
    public int|string|null $syncProductId = null;

    /** @var TemporaryUploadedFile|mixed|null */
    public $csv_file = null;

    public function mount(): void
    {
        abort_unless(OrderResource::canViewAny(), 403);
    }

    public function parseCsv(OrderImportService $import): void
    {
        $this->validate([
            'csv_file' => ['required', 'file', 'max:5120'],
        ]);

        $path = $this->csv_file?->getRealPath();
        if ($path === false || $path === null) {
            Notification::make()->title('تعذر قراءة الملف')->danger()->send();

            return;
        }

        try {
            $raw = $import->parseCsvToRows($path);
            $mapped = $import->buildMappingRows($raw);
            $this->rows = array_map(fn (array $row): array => $this->hydrateEditableFields($row), $mapped);
            $this->syncProductId = $this->guessInitialSyncProductId();
            $this->step = 2;
            $this->csv_file = null;
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function finalizeImport(OrderImportService $import): void
    {
        if ($this->rows === []) {
            Notification::make()->title('لا توجد بيانات للاستيراد')->warning()->send();

            return;
        }

        $this->validate([
            'syncProductId' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
        ], [
            'syncProductId.required' => 'اختر المنتج الموحّد لجميع الطلبات.',
        ]);

        $pid = (int) $this->syncProductId;
        $product = Product::query()->with('variations')->findOrFail($pid);

        $rules = [];
        $messages = [];

        foreach (array_keys($this->rows) as $i) {
            $rules["rows.{$i}.customer_name"] = ['required', 'string', 'max:255'];
            $rules["rows.{$i}.customer_phone"] = ['required', 'string', 'max:80'];
            $rules["rows.{$i}.city"] = ['required', 'string', 'max:255'];
            $rules["rows.{$i}.shipping_address"] = ['required', 'string', 'max:2000'];
            $rules["rows.{$i}.quantity"] = ['required', 'integer', 'min:1', 'max:999'];

            if ($product->variations->isNotEmpty()) {
                $rules["rows.{$i}.product_variation_id"] = [
                    'required',
                    'integer',
                    Rule::exists('product_variations', 'id')->where('product_id', $pid),
                ];
            }

            $messages["rows.{$i}.customer_name.required"] = 'أدخل اسم الزبون (الصف '.($i + 1).').';
            $messages["rows.{$i}.customer_phone.required"] = 'أدخل الهاتف (الصف '.($i + 1).').';
            $messages["rows.{$i}.city.required"] = 'أدخل المدينة (الصف '.($i + 1).').';
            $messages["rows.{$i}.shipping_address.required"] = 'أدخل العنوان (الصف '.($i + 1).').';
            $messages["rows.{$i}.quantity.required"] = 'أدخل الكمية (الصف '.($i + 1).').';
            $messages["rows.{$i}.product_variation_id.required"] = 'اختر النوع (الصف '.($i + 1).').';
        }

        $this->validate($rules, $messages);

        foreach (array_keys($this->rows) as $i) {
            $this->rows[$i]['product_id'] = $pid;
        }

        $count = $import->createOrdersFromMappings($this->rows);

        Notification::make()
            ->title('تم إنشاء '.$count.' طلبية')
            ->success()
            ->send();

        $this->rows = [];
        $this->syncProductId = null;
        $this->step = 1;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProductsForSelectProperty(): Collection
    {
        return Product::query()
            ->with('variations')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function resetImport(): void
    {
        $this->step = 1;
        $this->rows = [];
        $this->syncProductId = null;
        $this->csv_file = null;
    }

    public function updatedSyncProductId(mixed $value): void
    {
        $pid = (int) $value;
        if ($pid < 1) {
            foreach (array_keys($this->rows) as $i) {
                $this->rows[$i]['product_id'] = null;
                $this->rows[$i]['product_variation_id'] = null;
            }

            return;
        }

        $product = Product::query()->with('variations')->find($pid);
        $defaultVariationId = $product?->getDefaultVariation()?->id;

        foreach (array_keys($this->rows) as $i) {
            $this->rows[$i]['product_id'] = $pid;
            if ($product !== null && $product->variations->isNotEmpty()) {
                $this->rows[$i]['product_variation_id'] = $defaultVariationId;
            } else {
                $this->rows[$i]['product_variation_id'] = null;
            }
        }
    }

    /**
     * If CSV auto-match agrees on one product for every row, pre-fill the sync selector.
     */
    private function guessInitialSyncProductId(): int|string|null
    {
        if ($this->rows === []) {
            return null;
        }

        $ids = collect($this->rows)->pluck('product_id')->filter(fn ($id): bool => (int) $id > 0)->unique()->values();

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * Ensure stable keys for Livewire bindings (matches {@see OrderImportService::createOrdersFromMappings()} pick lists).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function hydrateEditableFields(array $row): array
    {
        $pick = static function (array $r, array $keys): string {
            foreach ($keys as $k) {
                if (isset($r[$k]) && trim((string) $r[$k]) !== '') {
                    return trim((string) $r[$k]);
                }
            }

            return '';
        };

        $out = $row;

        $name = $pick($row, ['customer_name', 'name', 'client']);
        if ($name !== '') {
            $out['customer_name'] = $name;
        } elseif (! isset($out['customer_name'])) {
            $out['customer_name'] = '';
        }

        $phone = $pick($row, ['customer_phone', 'phone', 'tel']);
        if ($phone !== '') {
            $out['customer_phone'] = $phone;
        } elseif (! isset($out['customer_phone'])) {
            $out['customer_phone'] = '';
        }

        $city = $pick($row, ['city', 'ville']);
        if ($city !== '') {
            $out['city'] = $city;
        } elseif (! isset($out['city'])) {
            $out['city'] = '';
        }

        $address = $pick($row, ['shipping_address', 'address', 'adresse']);
        if ($address !== '') {
            $out['shipping_address'] = $address;
        } elseif (! isset($out['shipping_address'])) {
            $out['shipping_address'] = '';
        }

        $qty = $pick($row, ['quantity', 'qty', 'count']);
        $out['quantity'] = $qty !== '' && is_numeric($qty) ? (string) max(1, min(999, (int) $qty)) : ($out['quantity'] ?? '1');

        return $out;
    }
}
