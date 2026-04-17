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
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
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
            ->disabledSelection(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'delivered')
            ->stackedOnMobile()
            ->striped()
            ->recordUrl(
                fn (Order $record): ?string => (Livewire::current()?->activeTab ?? null) === 'delivered'
                    ? null
                    : OrderResource::getUrl('edit', ['record' => $record])
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'orders-table-col-date'])
                    ->extraCellAttributes(['class' => 'orders-table-col-date'])
                    ->extraAttributes(['class' => 'text-xs']),
                TextColumn::make('number')
                    ->label('رقم')
                    ->toggleable()
                    ->searchable()
                    ->sortable()
                    ->grow(false)
                    ->alignment(Alignment::Start)
                    ->extraHeaderAttributes(['class' => 'orders-table-col-id'])
                    ->extraCellAttributes(['class' => 'orders-table-col-id'])
                    ->extraAttributes(['class' => 'text-xs tabular-nums']),
                TextColumn::make('customer_name')
                    ->label('الزبون')
                    ->toggleable()
                    ->searchable()
                    ->wrap()
                    ->alignment(Alignment::Start)
                    ->extraHeaderAttributes(['class' => 'orders-table-col-name'])
                    ->extraCellAttributes(['class' => 'orders-table-col-name'])
                    ->extraAttributes(['class' => 'text-xs']),
                TextInputColumn::make('customer_phone')
                    ->label('الهاتف')
                    ->toggleable()
                    ->disabled(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'delivered')
                    ->grow(false)
                    ->alignment(Alignment::Start)
                    ->extraHeaderAttributes(['class' => 'orders-table-col-phone'])
                    ->extraCellAttributes(['class' => 'orders-table-col-phone'])
                    ->extraAttributes(['class' => 'text-xs']),
                TextInputColumn::make('city')
                    ->label('المدينة')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->alignment(Alignment::Start)
                    ->disabled(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'delivered')
                    ->extraHeaderAttributes(['class' => 'orders-table-col-city'])
                    ->extraCellAttributes(['class' => 'orders-table-col-city'])
                    ->extraAttributes(['class' => 'text-xs']),
                TextColumn::make('shipping_address')
                    ->label('العنوان')
                    ->toggleable()
                    ->limit(32)
                    ->wrap()
                    ->alignment(Alignment::Start)
                    ->tooltip(fn (Order $record): ?string => filled($record->shipping_address)
                        ? (string) $record->shipping_address
                        : null)
                    ->extraHeaderAttributes(['class' => 'orders-table-col-address'])
                    ->extraCellAttributes(['class' => 'orders-table-col-address'])
                    ->extraAttributes(['class' => 'text-xs']),
                TextColumn::make('total_price')
                    ->label('المجموع')
                    ->toggleable()
                    ->money('MAD')
                    ->grow(false)
                    ->alignment(Alignment::End)
                    ->extraHeaderAttributes(['class' => 'orders-table-col-total'])
                    ->extraCellAttributes(['class' => 'orders-table-col-total'])
                    ->extraAttributes(['class' => 'text-xs tabular-nums']),
                TextColumn::make('postponed_at')
                    ->label('تاريخ التأجيل')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('-')
                    ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'postponed')
                    ->extraAttributes(['class' => 'text-xs tabular-nums']),
                TextColumn::make('postponed_reason')
                    ->label('سبب التأجيل')
                    ->wrap()
                    ->limit(50)
                    ->tooltip(fn (Order $record): ?string => filled($record->postponed_reason) ? (string) $record->postponed_reason : null)
                    ->placeholder('-')
                    ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'postponed')
                    ->extraAttributes(['class' => 'text-xs']),

                SelectColumn::make('status')
                    ->label('تغيير الحالة')
                    ->toggleable()
                    ->grow(false)
                    ->native()
                    ->alignment(Alignment::Center)
                    ->extraHeaderAttributes(['class' => 'orders-table-col-status'])
                    ->extraCellAttributes(['class' => 'orders-table-col-status'])
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
                        'postponed' => 'تأجيل',
                        'cancelled' => 'ملغي',
                    ])
                    ->updateStateUsing(function (Order $record, string $state): string {
                        if ($state !== 'postponed') {
                            return $state;
                        }

                        $livewire = Livewire::current();

                        if ($livewire !== null && method_exists($livewire, 'mountTableAction')) {
                            $livewire->mountTableAction('changeStatus', (string) $record->getKey(), [
                                'status' => 'postponed',
                                'postponed_at' => $record->postponed_at?->toDateString(),
                                'postponed_reason' => $record->postponed_reason,
                            ]);
                        }

                        // Keep old status in inline edit; postponed is confirmed only via popup submit.
                        return (string) $record->status;
                    })
                    ->extraInputAttributes(function ($state): array {
                        $state = mb_strtolower(trim((string) $state));
                        [$bg, $text] = match ($state) {
                            'pending', 'waiting', 'en attente', 'انتظار' => ['#fff', '#000'],
                            'confirmed', 'confirme', 'تأكيد' => ['#16a34a', '#111827'],
                            'no_response', 'no_answer', 'pas de reponse', 'لا جواب' => ['#f97316', '#111827'],
                            'postponed', 'postpone', 'تأجيل' => ['#eab308', '#111827'],
                            'pending', 'waiting', 'en attente', 'انتظار' => ['#6b7280', '#111827'],
                            'shipped', 'expedie', 'تم الشحن' => ['#2EFFF9', '#111827'],
                            'cancelled', 'annule', 'ملغي' => ['#FF0000', '#000'],
                            default => ['#6b7280', '#111827'],
                        };

                        return [
                            'class' => 'orders-status-select',
                            'style' => "box-sizing:border-box;width:auto;background-color:{$bg} !important;color:{$text} !important;border-color:{$bg} !important;transition:background-color 150ms ease-in-out,color 150ms ease-in-out,box-shadow 150ms ease-in-out;",
                        ];
                    }),
                TextColumn::make('products')
                    ->label('المنتجات')
                    ->toggleable(isToggledHiddenByDefault: true)
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
                    ->wrap()
                    ->grow(false)
                    ->extraHeaderAttributes(['class' => 'orders-table-col-products'])
                    ->extraCellAttributes(['class' => 'orders-table-col-products'])
                    ->extraAttributes(['class' => 'text-xs'])
                    ->tooltip(function ($record): ?string {
                        $names = $record->orderItems
                            ->map(fn ($item): ?string => $item->product?->name)
                            ->filter()
                            ->unique()
                            ->values();

                        return $names->isEmpty() ? null : $names->implode('، ');
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
                        'no_response' => 'لا جواب',
                        'postponed' => 'تأجيل',
                        'cancelled' => 'ملغي',
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
                Action::make('changeStatus')
                    ->label('تغيير حالة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->hidden(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'trash')
                    ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) !== 'delivered')
                    ->fillForm(fn (Order $record): array => [
                        'status' => $record->status,
                        'postponed_at' => $record->postponed_at?->toDateString(),
                        'postponed_reason' => $record->postponed_reason,
                    ])
                    ->form([
                        Select::make('status')
                            ->label('تغيير حالة')
                            ->options([
                                'pending' => 'انتظار',
                                'confirmed' => 'تأكيد',
                                'no_response' => 'لا جواب',
                                'postponed' => 'تأجيل',
                                'cancelled' => 'ملغي',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if ($state !== 'postponed') {
                                    $set('postponed_at', null);
                                    $set('postponed_reason', null);
                                }
                            }),
                        DatePicker::make('postponed_at')
                            ->label('تاريخ التأجيل')
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('status') === 'postponed')
                            ->required(fn (Get $get): bool => $get('status') === 'postponed'),
                        Textarea::make('postponed_reason')
                            ->label('سبب التأجيل')
                            ->rows(3)
                            ->visible(fn (Get $get): bool => $get('status') === 'postponed'),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $isPostponed = ($data['status'] ?? null) === 'postponed';

                        $record->update([
                            'status' => $data['status'] ?? $record->status,
                            'postponed_at' => $isPostponed ? ($data['postponed_at'] ?? null) : null,
                            'postponed_reason' => $isPostponed ? ($data['postponed_reason'] ?? null) : null,
                        ]);
                    }),
                DeleteAction::make()
                    ->iconButton()
                    ->label('')
                    ->tooltip(__('filament-actions::delete.single.label'))
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
