<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Product;
use App\Services\OrderImportService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    protected Width|string|null $maxContentWidth = Width::Full;

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

        if ($this->csv_file === null) {
            return;
        }

        try {
            $raw = $import->parseCsvToRows($this->csv_file);
            $mapped = $import->buildMappingRows($raw);
            $this->rows = [];
            foreach ($mapped as $row) {
                $hydrated = $this->hydrateEditableFields($row);
                if ($this->shouldDropImportedRow($hydrated)) {
                    continue;
                }
                $this->applyNameAddressFallbacks($hydrated);
                $this->rows[] = $hydrated;
            }

            $dupesRemoved = $this->dedupeRowsByPhone();
            if ($dupesRemoved > 0) {
                Notification::make()
                    ->title('تم حذف الصفوف المكررة')
                    ->body('حُذف '.$dupesRemoved.' صفاً بنفس رقم الهاتف (يُحتفظ بالأول في الملف).')
                    ->info()
                    ->send();
            }

            if ($this->rows === []) {
                Notification::make()
                    ->title('لا توجد صفوف صالحة')
                    ->body('تم تجاهل كل الصفوف التي بلا اسم ولا مدينة في الملف.')
                    ->warning()
                    ->send();

                return;
            }

            $this->syncProductId = $this->guessInitialSyncProductId();
            $this->hydrateDefaultsAfterCsvParse();
            $this->refreshImportSkuPriceForAllRows();
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

        $removedDupesBeforeSave = $this->normalizeRowsBeforeFinalize();

        if ($removedDupesBeforeSave > 0) {
            Notification::make()
                ->title('صفوف مكررة')
                ->body('حُذف '.$removedDupesBeforeSave.' صفاً بنفس رقم الهاتف قبل إنشاء الطلبات (يُحتفظ بالأول).')
                ->warning()
                ->send();
        }

        if ($this->rows === []) {
            Notification::make()
                ->title('لا توجد صفوف للاستيراد')
                ->body('أضف اسم الزبون أو المدينة لكل صف على الأقل.')
                ->warning()
                ->send();

            return;
        }

        $this->normalizeImportPayload();

        try {
            $this->validate([
                'syncProductId' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            ], [
                'syncProductId.required' => 'اختر المنتج الموحّد لجميع الطلبات.',
            ]);
        } catch (ValidationException $e) {
            $this->flashValidationSummary($e);

            throw $e;
        }

        $pid = (int) $this->syncProductId;
        $product = Product::query()->with('variations')->findOrFail($pid);

        $rules = [];
        $messages = [];

        foreach (array_keys($this->rows) as $i) {
            $rules["rows.{$i}.customer_name"] = ['required', 'string', 'max:255'];
            $rules["rows.{$i}.customer_phone"] = ['required', 'string', 'max:80'];
            $rules["rows.{$i}.city"] = ['required', 'string', 'max:255'];
            $rules["rows.{$i}.shipping_address"] = ['required', 'string', 'max:2000'];
            $rules["rows.{$i}.quantity"] = ['required', 'numeric', 'min:1', 'max:999'];

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

        try {
            $this->validate($rules, $messages);
        } catch (ValidationException $e) {
            $this->flashValidationSummary($e);

            throw $e;
        }

        foreach (array_keys($this->rows) as $i) {
            $this->rows[$i]['product_id'] = $pid;
            $this->rows[$i]['quantity'] = (int) $this->rows[$i]['quantity'];
        }

        $count = $import->createOrdersFromMappings($this->rows);

        $done = Notification::make()
            ->title($count > 0 ? 'تم إنشاء '.$count.' طلبية' : 'لم تُنشأ أي طلبية (تأكد من الحقول الإلزامية في الملف)');

        if ($count > 0) {
            $done->success();
        } else {
            $done->warning();
        }

        $done->send();

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

    public function defaultImportProductForm(Schema $schema): Schema
    {
        return $schema->statePath('');
    }

    public function importProductForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'sm' => 1,
                'md' => 1,
                'lg' => 1,
                'xl' => 1,
                '2xl' => 1,
            ])
            ->components([
                Select::make('syncProductId')
                    ->label('المنتج الموحّد لجميع الطلبات')
                    ->placeholder('— اختر المنتج —')
                    ->options(fn (): array => Product::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Product $p): array => [$p->id => $p->name.' ('.$p->code.')'])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->columnSpanFull()
                    ->extraFieldWrapperAttributes(['class' => 'w-full !max-w-none']),
            ]);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function variationOptionsForProduct(?Product $product): array
    {
        if ($product === null || $product->variations->isEmpty()) {
            return [];
        }

        return $product->variations
            ->map(function ($v): array {
                $sku = filled($v->sku) ? ' ('.$v->sku.')' : '';

                return [
                    'id' => $v->id,
                    'label' => $v->label().$sku.' — '.number_format((float) $v->price, 2).' MAD',
                ];
            })
            ->values()
            ->all();
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
        $this->applyProductSelectionToRows();
        $this->refreshImportSkuPriceForAllRows();
    }

    public function updated(string $name, mixed $value): void
    {
        if (preg_match('/^rows\.\d+\.product_variation_id$/', $name) === 1) {
            $index = (int) Str::between($name, 'rows.', '.product_variation_id');
            $this->refreshImportSkuPriceForRow($index);
        }
    }

    /**
     * Assign product / default variation to every row and refresh SKU & price labels.
     */
    private function applyProductSelectionToRows(): void
    {
        $pid = (int) ($this->syncProductId ?? 0);
        if ($pid < 1) {
            foreach (array_keys($this->rows) as $i) {
                $this->rows[$i]['product_id'] = null;
                $this->rows[$i]['product_variation_id'] = null;
                $this->rows[$i]['_import_sku'] = '';
                $this->rows[$i]['_import_price'] = '';
            }

            return;
        }

        $product = Product::query()->with('variations')->find($pid);

        foreach (array_keys($this->rows) as $i) {
            $this->rows[$i]['product_id'] = $pid;
            if ($product === null || $product->variations->isEmpty()) {
                $this->rows[$i]['product_variation_id'] = null;

                continue;
            }

            if ($product->variations->count() === 1) {
                $this->rows[$i]['product_variation_id'] = $product->variations->first()->id;

                continue;
            }

            $current = $this->rows[$i]['product_variation_id'] ?? null;
            $currentInt = is_numeric($current) ? (int) $current : 0;
            $stillValid = $currentInt > 0 && $product->variations->contains('id', $currentInt);
            $this->rows[$i]['product_variation_id'] = $stillValid ? $currentInt : null;
        }
    }

    private function refreshImportSkuPriceForAllRows(): void
    {
        $product = Product::query()
            ->with('variations')
            ->find((int) ($this->syncProductId ?? 0));

        foreach (array_keys($this->rows) as $i) {
            $this->refreshImportSkuPriceForRow($i, $product);
        }
    }

    private function refreshImportSkuPriceForRow(int $index, ?Product $product = null): void
    {
        if (! array_key_exists($index, $this->rows)) {
            return;
        }

        $product ??= Product::query()
            ->with('variations')
            ->find((int) ($this->syncProductId ?? 0));

        if ($product === null) {
            $this->rows[$index]['_import_sku'] = '';
            $this->rows[$index]['_import_price'] = '';

            return;
        }

        $vid = $this->rows[$index]['product_variation_id'] ?? null;
        $vid = is_numeric($vid) ? (int) $vid : null;

        if ($product->variations->isNotEmpty()) {
            $v = ($vid !== null && $vid > 0)
                ? $product->variations->firstWhere('id', $vid)
                : null;
            if ($v === null) {
                $this->rows[$index]['_import_sku'] = '';
                $this->rows[$index]['_import_price'] = '';
            } else {
                $this->rows[$index]['_import_sku'] = filled($v->sku) ? (string) $v->sku : (string) $product->code;
                $this->rows[$index]['_import_price'] = number_format((float) $v->price, 2).' MAD';
            }
        } else {
            $this->rows[$index]['_import_sku'] = (string) $product->code;
            $this->rows[$index]['_import_price'] = number_format((float) $product->finalUnitPriceForCart(null), 2).' MAD';
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
     * When the unified product is pre-filled from CSV, still assign default variations (updatedSyncProductId does not run).
     */
    private function hydrateDefaultsAfterCsvParse(): void
    {
        $pid = (int) ($this->syncProductId ?? 0);
        if ($pid < 1) {
            return;
        }

        $product = Product::query()->with('variations')->find($pid);
        if ($product === null) {
            return;
        }

        foreach (array_keys($this->rows) as $i) {
            $this->rows[$i]['product_id'] = $pid;
            if ($product->variations->isNotEmpty()) {
                $current = $this->rows[$i]['product_variation_id'] ?? null;
                $currentInt = is_numeric($current) ? (int) $current : 0;
                $valid = $currentInt > 0 && $product->variations->contains('id', $currentInt);
                if (! $valid) {
                    $this->rows[$i]['product_variation_id'] = $product->variations->count() === 1
                        ? $product->variations->first()->id
                        : null;
                }
            } else {
                $this->rows[$i]['product_variation_id'] = null;
            }
        }
    }

    private function normalizeImportPayload(): void
    {
        if ($this->syncProductId === '' || $this->syncProductId === '0') {
            $this->syncProductId = null;
        }

        foreach (array_keys($this->rows) as $i) {
            $q = $this->rows[$i]['quantity'] ?? '1';
            $this->rows[$i]['quantity'] = is_numeric($q) ? (string) max(1, min(999, (int) $q)) : '1';
            $v = $this->rows[$i]['product_variation_id'] ?? null;
            if ($v === '') {
                $this->rows[$i]['product_variation_id'] = null;
            }
        }
    }

    /**
     * Drop rows with no name and no city; apply Client + address=city before validation.
     *
     * @param  array<string, mixed>  $row
     */
    private function shouldDropImportedRow(array $row): bool
    {
        $name = trim((string) ($row['customer_name'] ?? ''));
        $city = trim((string) ($row['city'] ?? ''));

        return $name === '' && $city === '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyNameAddressFallbacks(array &$row): void
    {
        if (trim((string) ($row['customer_name'] ?? '')) === '') {
            $row['customer_name'] = 'Client';
        }

        $city = trim((string) ($row['city'] ?? ''));
        if (trim((string) ($row['shipping_address'] ?? '')) === '' && $city !== '') {
            $row['shipping_address'] = $city;
        }
    }

    /**
     * @return int Rows removed as duplicate phone numbers after normalization
     */
    private function normalizeRowsBeforeFinalize(): int
    {
        $out = [];
        foreach ($this->rows as $row) {
            $name = trim((string) ($row['customer_name'] ?? ''));
            $city = trim((string) ($row['city'] ?? ''));
            if ($name === '' && $city === '') {
                continue;
            }
            if ($name === '') {
                $row['customer_name'] = 'Client';
            }
            if (trim((string) ($row['shipping_address'] ?? '')) === '' && trim((string) ($row['city'] ?? '')) !== '') {
                $row['shipping_address'] = trim((string) $row['city']);
            }
            $out[] = $row;
        }
        $this->rows = array_values($out);

        return $this->dedupeRowsByPhone();
    }

    /**
     * Keep the first row per normalized phone; rows with an empty phone are never treated as duplicates of each other.
     *
     * @return int Number of rows removed as duplicates
     */
    private function dedupeRowsByPhone(): int
    {
        $seen = [];
        $out = [];
        $removed = 0;

        foreach ($this->rows as $row) {
            $key = $this->normalizedPhoneDedupeKey((string) ($row['customer_phone'] ?? ''));
            if ($key === '') {
                $out[] = $row;

                continue;
            }
            if (isset($seen[$key])) {
                $removed++;

                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        $this->rows = array_values($out);

        return $removed;
    }

    /**
     * Normalize phone for duplicate detection (Morocco-friendly: 212… vs 0…).
     */
    private function normalizedPhoneDedupeKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '212') && strlen($digits) > 3) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits;
    }

    private function flashValidationSummary(ValidationException $e): void
    {
        $first = collect($e->errors())->flatten()->first();

        if (filled($first)) {
            Notification::make()
                ->title('تعذر إكمال الاستيراد')
                ->body($first)
                ->danger()
                ->persistent()
                ->send();
        }
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

        $out['_import_sku'] = $row['_import_sku'] ?? '';
        $out['_import_price'] = $row['_import_price'] ?? '';

        return $out;
    }
}
