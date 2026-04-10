<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Services\Shipping\ShippingManager;
use App\Services\VitipsService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTrashed()->with('orderItems.product'))
            ->defaultSort('created_at', 'desc')
            ->checkIfRecordIsSelectableUsing(
                fn (Order $record): bool => (Livewire::current()?->activeTab ?? null) !== 'delivered'
            )
            ->recordUrl(
                fn (Order $record): ?string => (Livewire::current()?->activeTab ?? null) === 'delivered'
                    ? null
                    : OrderResource::getUrl('edit', ['record' => $record])
            )
            // بمجرد تعريف الـ Bulk Actions، ستظهر الـ Checkboxes تلقائياً في الجدول
            ->columns([
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->wrap(),
                TextColumn::make('customer_name')->label('الزبون')->searchable()->wrap(),
                TextInputColumn::make('customer_phone')
                    ->label('الهاتف')
                    ->disabled(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'delivered'),
                TextInputColumn::make('city')
                    ->label('المدينة')
                    ->disabled(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'delivered'),
                TextInputColumn::make('shipping_address')
                    ->label('العنوان')
                    ->disabled(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'delivered'),
                TextColumn::make('products')
                    ->label('المنتجات')
                    ->state(function ($record): string {
                        $products = $record->orderItems
                            ->map(fn ($item): ?string => $item->product?->name)
                            ->filter()
                            ->unique()
                            ->values();

                        if ($products->count() <= 1) {
                            return (string) ($products->first() ?? '—');
                        }

                        return 'عدة منتجات';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'عدة منتجات' ? 'warning' : 'gray')
                    ->wrap(),
                TextColumn::make('total_price')->label('المجموع')->money('MAD'),

                TextColumn::make('status')
                    ->label('حالة الطلب')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'انتظار',
                        'confirmed' => 'تأكيد',
                        'no_response' => 'لا جواب',
                        'cancelled' => 'ملغي',
                        'shipped' => 'تم الشحن',
                        'delivered' => 'تم التسليم',
                        default => $state,
                    })
                    ->color(function (string $state): string {
                        $state = mb_strtolower(trim($state));

                        return match ($state) {
                            'confirmed', 'confirme', 'تأكيد' => 'success',
                            'no_response', 'pas de reponse', 'لا جواب' => 'warning',
                            'cancelled', 'annule', 'ملغي' => 'danger',
                            'shipped', 'expedie', 'تم الشحن' => 'primary',
                            'delivered', 'livre', 'تم التسليم' => 'gray',
                            'pending', 'en attente', 'انتظار' => 'gray',
                            default => 'gray',
                        };
                    })
                    ->wrap(),

                SelectColumn::make('status')
                    ->label('تغيير الحالة')
                    ->hidden(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'trash')
                    ->disabled(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'delivered')
                    ->selectablePlaceholder(false)
                    ->rules(['required'])
                    ->validationMessages([
                        'required' => 'المرجو اختيار حالة الطلب',
                    ])
                    ->options([
                        'pending' => 'انتظار',
                        'confirmed' => 'تأكيد',
                        'no_response' => 'لا جواب',
                        'cancelled' => 'ملغي',
                    ])
                    ->extraInputAttributes(function ($state): array {
                        $state = mb_strtolower(trim((string) $state));
                        [$bg, $text] = match ($state) {
                            'pending', 'waiting', 'en attente', 'انتظار' => ['#fff', '#000'],
                            'confirmed', 'confirme', 'تأكيد' => ['#16a34a', '#111827'],
                            'no_response', 'no_answer', 'pas de reponse', 'لا جواب' => ['#f97316', '#111827'],
                            'pending', 'waiting', 'en attente', 'انتظار' => ['#6b7280', '#111827'],
                            'shipped', 'expedie', 'تم الشحن' => ['#2EFFF9', '#111827'],
                            'cancelled', 'annule', 'ملغي' => ['#FF0000', '#000'],
                            default => ['#6b7280', '#111827'],
                        };

                        return [
                            'style' => "background-color: {$bg} !important; color: {$text} !important; border-color: {$bg} !important; transition: background-color 150ms ease-in-out, color 150ms ease-in-out;",
                        ];
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_between')
                    ->label('تصفية حسب التاريخ')
                    ->form([
                        Select::make('preset')
                            ->label('الفترة')
                            ->options([
                                'all' => 'كل الفترات',
                                'today' => 'اليوم',
                                'yesterday' => 'أمس',
                                'last_14_days' => 'آخر 14 يوماً',
                                'last_month' => 'الشهر الماضي',
                                'custom' => 'فترة مخصصة',
                            ])
                            ->default('all')
                            ->live()
                            ->native(false),
                        DatePicker::make('from')
                            ->label('من تاريخ')
                            ->visible(fn ($get): bool => ($get('preset') ?? 'all') === 'custom'),
                        DatePicker::make('until')
                            ->label('إلى تاريخ')
                            ->visible(fn ($get): bool => ($get('preset') ?? 'all') === 'custom'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $preset = $data['preset'] ?? 'all';
                        if ($preset === 'all' || blank($preset)) {
                            return $query;
                        }

                        return match ($preset) {
                            'today' => $query->whereBetween('created_at', [
                                Carbon::today()->startOfDay(),
                                Carbon::today()->endOfDay(),
                            ]),
                            'yesterday' => $query->whereBetween('created_at', [
                                Carbon::yesterday()->startOfDay(),
                                Carbon::yesterday()->endOfDay(),
                            ]),
                            'last_14_days' => $query->whereBetween('created_at', [
                                Carbon::now()->subDays(13)->startOfDay(),
                                Carbon::now()->endOfDay(),
                            ]),
                            'last_month' => $query->whereBetween('created_at', [
                                Carbon::now()->subMonth()->startOfMonth(),
                                Carbon::now()->subMonth()->endOfMonth(),
                            ]),
                            'custom' => $query
                                ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $data['from']))
                                ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $data['until'])),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): array {
                        $preset = $data['preset'] ?? 'all';
                        if ($preset === 'all' || blank($preset)) {
                            return [];
                        }

                        $labels = [
                            'today' => 'اليوم',
                            'yesterday' => 'أمس',
                            'last_14_days' => 'آخر 14 يوماً',
                            'last_month' => 'الشهر الماضي',
                            'custom' => 'فترة مخصصة',
                        ];

                        if ($preset === 'custom') {
                            $parts = [];
                            if (filled($data['from'] ?? null)) {
                                $parts[] = 'من '.$data['from'];
                            }
                            if (filled($data['until'] ?? null)) {
                                $parts[] = 'إلى '.$data['until'];
                            }

                            return $parts !== []
                                ? [Indicator::make(implode(' — ', $parts))]
                                : [Indicator::make($labels['custom'])];
                        }

                        return [Indicator::make($labels[$preset] ?? $preset)];
                    }),
                Tables\Filters\Filter::make('new_orders')
                    ->label('New Orders')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'pending')),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('منتج محدد')
                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('orderItems', fn (Builder $itemQuery): Builder => $itemQuery
                            ->where('product_id', $data['value']));
                    }),
                Tables\Filters\Filter::make('product_name')
                    ->label('اسم المنتج')
                    ->form([
                        TextInput::make('product_name')
                            ->label('اسم المنتج')
                            ->placeholder('بحث جزئي في اسم المنتج'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $term = trim((string) ($data['product_name'] ?? ''));
                        if ($term === '') {
                            return $query;
                        }

                        return $query->whereHas('orderItems.product', fn (Builder $productQuery): Builder => $productQuery
                            ->where('name', 'like', '%'.$term.'%'));
                    })
                    ->indicateUsing(function (array $data): array {
                        $term = trim((string) ($data['product_name'] ?? ''));
                        if ($term === '') {
                            return [];
                        }

                        return [Indicator::make('منتج: '.$term)];
                    }),
                Tables\Filters\Filter::make('order_city')
                    ->label('المدينة')
                    ->form([
                        TextInput::make('city')
                            ->label('اسم المدينة')
                            ->placeholder('بحث جزئي'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $term = trim((string) ($data['city'] ?? ''));
                        if ($term === '') {
                            return $query;
                        }

                        return $query->where('city', 'like', '%'.$term.'%');
                    })
                    ->indicateUsing(function (array $data): array {
                        $term = trim((string) ($data['city'] ?? ''));
                        if ($term === '') {
                            return [];
                        }

                        return [Indicator::make('مدينة: '.$term)];
                    }),
                Tables\Filters\Filter::make('order_phone')
                    ->label('رقم الهاتف')
                    ->form([
                        TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->tel()
                            ->placeholder('أرقام أو جزء من الرقم'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $raw = trim((string) ($data['phone'] ?? ''));
                        if ($raw === '') {
                            return $query;
                        }

                        return $query->where('customer_phone', 'like', '%'.$raw.'%');
                    })
                    ->indicateUsing(function (array $data): array {
                        $raw = trim((string) ($data['phone'] ?? ''));
                        if ($raw === '') {
                            return [];
                        }

                        return [Indicator::make('هاتف: '.$raw)];
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'انتظار',
                        'confirmed' => 'تأكيد',
                    ]),
            ])
            ->recordActions([
                Action::make('showProducts')
                    ->label('عدة منتجات')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record->orderItems->count() > 1)
                    ->modalHeading('تفاصيل المنتجات')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->extraAttributes(['style' => 'background-color:#ff751f;border-color:#ff751f;color:#fff'])
                    ->modalContent(fn ($record) => view('filament.orders.products-modal', [
                        'items' => $record->orderItems,
                    ])),
                DeleteAction::make()
                    ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) !== 'delivered'),
                RestoreAction::make()
                    ->visible(fn ($record): bool => method_exists($record, 'trashed') && $record->trashed()),
                ForceDeleteAction::make()
                    ->label('حذف نهائي')
                    ->visible(fn ($record): bool => method_exists($record, 'trashed')
                        && $record->trashed()
                        && auth()->user()?->role === 'admin'),
            ])
            // --- هنا يتم تفعيل الـ Checkboxes والعمليات الجماعية ---
            ->bulkActions([
                BulkActionGroup::make([

                    // 1. حذف الطلبات المختارة (سيتم نقلها للـ Trash إذا كنت تستخدم SoftDeletes)
                    DeleteBulkAction::make()
                        ->label('حذف الطلبات المختارة'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),

                    // 2. تصدير الطلبات المختارة (Export)
                    BulkAction::make('export')
                        ->label('تصدير البيانات (Excel/CSV)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function (Collection $records) {
                            // هنا يمكنك برمجة منطق التصدير الخاص بك
                            // مثال بسيط: تحميل ملف CSV للطلبات المختارة فقط
                            return response()->streamDownload(function () use ($records) {
                                echo "رقم الطلبية,الزبون,الهاتف,المجموع,الحالة\n";
                                foreach ($records as $order) {
                                    echo "{$order->number},{$order->customer_name},{$order->customer_phone},{$order->total_price},{$order->status}\n";
                                }
                            }, 'orders_export_'.now()->format('Y-m-d').'.csv');
                        })
                        ->requiresConfirmation() // يطلب تأكيد قبل التحميل
                        ->modalHeading('تصدير الطلبات المختارة')
                        ->modalDescription('هل أنت متأكد من رغبتك في تحميل بيانات الطلبات التي قمت بتحديدها؟')
                        ->modalSubmitActionLabel('تحميل الآن'),

                    BulkAction::make('assignToShipping')
                        ->label('Assign to Shipping Company')
                        ->icon('heroicon-o-truck')
                        ->color('primary')
                        ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'all')
                        ->form([
                            Select::make('shipping_company_id')
                                ->label('Select Company')
                                ->options(fn (): array => ShippingCompany::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->action(function (Collection $records, array $data): void {
                            $shippingCompanyId = (int) ($data['shipping_company_id'] ?? 0);
                            $shippingCompany = ShippingCompany::query()->find($shippingCompanyId);

                            if (! $shippingCompany) {
                                Notification::make()
                                    ->title('شركة الشحن غير موجودة')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $success = 0;
                            $failed = 0;
                            $errors = [];
                            $shippingManager = app(ShippingManager::class);

                            /** @var Order $order */
                            foreach ($records as $order) {
                                try {
                                    $result = $shippingManager->process($order, $shippingCompanyId);

                                    if (($result['code'] ?? '') !== 'ok') {
                                        $failed++;
                                        $errors[] = "Order #{$order->id}: ".($result['message'] ?: 'Unknown API response.');

                                        continue;
                                    }

                                    $order->update([
                                        'status' => 'shipped',
                                        'shipping_company_id' => $shippingCompany->id,
                                        'shipping_company' => $shippingCompany->name,
                                        'tracking_number' => $result['tracking_number'] ?? $order->tracking_number,
                                    ]);

                                    $success++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                    $errors[] = "Order #{$order->id}: {$e->getMessage()}";
                                    Log::error('Assign to shipping failed', [
                                        'order_id' => $order->id,
                                        'shipping_company_id' => $shippingCompanyId,
                                        'exception' => $e->getMessage(),
                                    ]);
                                }
                            }

                            $notification = Notification::make()
                                ->title("Shipped: {$success} | Failed: {$failed}");

                            if ($failed > 0) {
                                $notification
                                    ->warning()
                                    ->body(collect($errors)->take(5)->implode("\n"));
                            } else {
                                $notification->success();
                            }

                            $notification->send();
                        }),

                    // 3. Send selected orders to Vitips Express
                    BulkAction::make('sendToVitips')
                        ->label('إرسال للموزع')
                        ->icon('heroicon-o-truck')
                        ->color('success')
                        ->visible(fn (Collection $records): bool => $records->isNotEmpty()
                            && $records->contains(fn (Order $order) => blank($order->tracking_number) && $order->status === 'confirmed'))
                        ->form([
                            Select::make('provider')
                                ->label('اختر شركة التوصيل')
                                ->options([
                                    'vitips' => 'Vitips Express',
                                    'express_coursier' => 'Express Coursier',
                                ])
                                ->default('vitips')
                                ->native(false)
                                ->required(),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('إرسال للموزع')
                        ->modalDescription('اختر شركة التوصيل ثم قم بإرسال الطلبات. سيتم إضافة رقم التتبع للطلبات التي لا تحتوي على رقم تتبع.')
                        ->action(function (Collection $records, array $data): void {
                            $provider = (string) ($data['provider'] ?? 'vitips');

                            if ($provider !== 'vitips') {
                                Notification::make()
                                    ->title('تكامل Express Coursier قادم قريبا')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $success = 0;
                            $failed = 0;

                            /** @var Order $order */
                            $targets = $records->filter(fn (Order $order) => blank($order->tracking_number) && $order->status === 'confirmed');

                            if ($targets->isEmpty()) {
                                Notification::make()
                                    ->title('لا توجد طلبات مؤهلة للإرسال (جميعها لديها رقم تتبع).')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $vitips = app(VitipsService::class);

                            $targets->each(function (Order $order) use (&$success, &$failed, $vitips): void {
                                try {
                                    $productNames = $order->orderItems
                                        ->map(fn ($item) => $item->product?->name)
                                        ->filter()
                                        ->unique()
                                        ->values()
                                        ->implode(', ');

                                    $itemsCount = (int) $order->orderItems->sum('quantity');

                                    $payload = [
                                        'customer_name' => (string) $order->customer_name,
                                        'customer_phone' => (string) $order->customer_phone,
                                        'shipping_address' => (string) $order->shipping_address,
                                        'total_price' => (string) $order->total_price,
                                        'product_names' => $productNames,
                                        'items_count' => (string) $itemsCount,
                                        'id' => (string) $order->id,
                                    ];

                                    $trackingNumber = $vitips->createShipment($payload);

                                    $order->update([
                                        'tracking_number' => $trackingNumber,
                                    ]);

                                    $success++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                    Log::error('Vitips bulk shipment sync failed', [
                                        'order_id' => $order->id,
                                        'exception' => $e->getMessage(),
                                    ]);
                                }
                            });

                            Notification::make()
                                ->title("تم الإرسال: {$success} طلب/طلبات، فشل: {$failed} طلب/طلبات")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);

        // Safety net: only enable drag/drop when DB column exists.
        if (Schema::hasColumn('orders', 'sort_order')) {
            $table->reorderable('sort_order');
        }

        return $table;
    }
}
