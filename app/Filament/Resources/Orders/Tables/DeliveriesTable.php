<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\ShippingInvoiceImports\ShippingInvoiceImportResource;
use App\Models\Order;
use App\Models\OrderCarrierCitySelection;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanyCity;
use App\Models\User;
use App\Services\Shipping\ShippingCompanyCityMatcher;
use App\Services\Shipping\ShippingInvoiceFileReader;
use App\Services\Shipping\ShippingInvoiceImportRecorder;
use App\Services\Shipping\ShippingInvoiceImportRecordResult;
use App\Services\Shipping\ShippingManager;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Actions\HeaderActionsPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Livewire;

class DeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                if (auth()->user()?->role === 'delivery_man') {
                    $query->where('delivery_man_id', auth()->id());
                }

                return $query->with(['orderItems.product']);
            })
            ->checkIfRecordIsSelectableUsing(
                fn (Order $record): bool => ! self::isDeliveredAndPaid($record)
            )
            ->columns([
                TextColumn::make('number')
                    ->label('رقم الطلبية')
                    ->searchable(),

                TextColumn::make('customer_name')
                    ->label('الزبون')
                    ->searchable(),

                TextColumn::make('customer_phone')
                    ->label('Téléphone')
                    ->searchable(),

                TextColumn::make('city')
                    ->label('المدينة')
                    ->searchable(),

                TextColumn::make('shipping_address')
                    ->label('العنوان')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deliveryMan.name')
                    ->label('Delivery Man')
                    ->state(fn ($record): string => $record->deliveryMan?->name ?: 'غير معين')
                    ->hidden(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'shipping_companies')
                    ->searchable(),

                TextColumn::make('shipping_company')
                    ->label('Shipping Company')
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'Local Delivery')
                    ->badge()
                    ->color(function (?string $state): string {
                        if (blank($state)) {
                            return 'info';
                        }

                        static $colors = null;

                        if ($colors === null) {
                            $colors = ShippingCompany::query()
                                ->pluck('color', 'name')
                                ->toArray();
                        }

                        return $colors[$state] ?? 'gray';
                    })
                    ->hidden(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'local_delivery')
                    ->searchable(),

                TextInputColumn::make('tracking_number')
                    ->label('رقم التتبع')
                    ->placeholder('في انتظار التتبع')
                    ->tooltip('تعديل يدوي إذا لزم. إن وُجد فارغاً: قد يُكمَل لاحقاً من المزوّد أو الويبهوك.')
                    ->disabled(fn (Order $record): bool => self::isDeliveredAndPaid($record))
                    ->searchable()
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'shipping_companies'),

                TextColumn::make('tracking_number_readonly')
                    ->label('رقم التتبع')
                    ->state(fn (Order $record): string => filled($record->tracking_number) ? (string) $record->tracking_number : '—')
                    ->searchable(
                        true,
                        fn (Builder $query, string $search): Builder => $query->where('tracking_number', 'like', '%'.$search.'%'),
                    )
                    ->copyable()
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'completed'),

                TextColumn::make('shipping_provider_status')
                    ->label('الحالة في شركة التوصيل')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'غير متوفر')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'info' : 'gray')
                    ->wrap()
                    ->searchable()
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'shipping_companies'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(function (string $state): string {
                        $state = mb_strtolower(trim($state));

                        return match ($state) {
                            'confirmed', 'confirme', 'تأكيد' => 'success',
                            'no_response', 'pas de reponse', 'لا جواب' => 'warning',
                            'cancelled', 'annule', 'ملغي' => 'danger',
                            'refuse', 'refus', 'رفض' => 'danger',
                            'reporter', 'report', 'مؤجل' => 'info',
                            'shipped', 'expedie', 'تم الشحن' => 'warning',
                            'delivered', 'livre', 'تم التسليم' => 'gray',
                            'completed', 'cloture', 'مغلق' => 'success',
                            'pending', 'en attente', 'انتظار' => 'gray',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'unpaid')
                    ->color(fn (?string $state): string => $state === 'paid' ? 'success' : 'danger'),

                TextColumn::make('total_price')
                    ->label('المجموع')
                    ->money('MAD'),
                TextInputColumn::make('delivery_fee')
                    ->label('تكاليف التوصيل')
                    ->type('number')
                    ->step('0.01')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->disabled(fn (Order $record): bool => self::isDeliveredAndPaid($record))
                    ->visible(fn ($livewire): bool => in_array(($livewire->activeTab ?? null), ['collection', 'completed'], true)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('delivery_man_id')
                    ->label('Delivery Man')
                    ->options(
                        User::query()
                            ->where('role', 'delivery_man')
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->visible(fn ($livewire): bool => auth()->user()?->role !== 'delivery_man'
                        && ($livewire->activeTab ?? null) !== 'collection'
                        && ($livewire->activeTab ?? null) !== 'completed'),
                Tables\Filters\Filter::make('collection_carrier')
                    ->label('تصفية حسب الناقل')
                    ->visible(fn ($livewire): bool => auth()->user()?->role !== 'delivery_man'
                        && in_array(($livewire->activeTab ?? null), ['collection', 'completed'], true))
                    ->form([
                        Select::make('mode')
                            ->label('البحث حسب')
                            ->options([
                                'delivery_man' => 'الموزع',
                                'shipping_company' => 'شركة الشحن',
                            ])
                            ->default('delivery_man')
                            ->live()
                            ->native(false),
                        Select::make('delivery_man_id')
                            ->label('الموزع')
                            ->options(
                                User::query()
                                    ->where('role', 'delivery_man')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get): bool => ($get('mode') ?? 'delivery_man') === 'delivery_man'),
                        Select::make('shipping_company')
                            ->label('شركة الشحن')
                            ->options(
                                ShippingCompany::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'name')
                                    ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get): bool => ($get('mode') ?? 'delivery_man') === 'shipping_company'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $mode = $data['mode'] ?? 'delivery_man';

                        if ($mode === 'delivery_man' && filled($data['delivery_man_id'] ?? null)) {
                            return $query->where('delivery_man_id', $data['delivery_man_id']);
                        }

                        if ($mode === 'shipping_company' && filled($data['shipping_company'] ?? null)) {
                            return $query->where('shipping_company', $data['shipping_company']);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $mode = $data['mode'] ?? 'delivery_man';

                        if ($mode === 'delivery_man' && filled($data['delivery_man_id'] ?? null)) {
                            $name = User::query()->whereKey($data['delivery_man_id'])->value('name');

                            return filled($name) ? [Indicator::make('الموزع: '.$name)] : [];
                        }

                        if ($mode === 'shipping_company' && filled($data['shipping_company'] ?? null)) {
                            return [Indicator::make('شركة الشحن: '.$data['shipping_company'])];
                        }

                        return [];
                    }),
                Tables\Filters\Filter::make('delivered_date')
                    ->label('تاريخ التسليم')
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('updated_at', '>=', $data['from'])
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('updated_at', '<=', $data['until'])
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = Indicator::make('من: '.$data['from']);
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = Indicator::make('إلى: '.$data['until']);
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Changer le statut')
                    ->form(fn (): array => auth()->user()?->role === 'delivery_man'
                        ? [
                            Select::make('status')
                                ->label('Statut')
                                ->options([
                                    'delivered' => 'Livre',
                                    'cancelled' => 'Annule',
                                    'no_response' => 'Pas de reponse',
                                    'refuse' => 'Refuse',
                                    'reporter' => 'Reporter',
                                ])
                                ->required(),
                        ]
                        : [
                            Select::make('status')
                                ->label('Statut')
                                ->options([
                                    'pending' => 'En attente',
                                    'confirmed' => 'Confirme',
                                    'no_response' => 'Pas de reponse',
                                    'cancelled' => 'Annule',
                                    'refuse' => 'Refuse',
                                    'reporter' => 'Reporter',
                                    'shipped' => 'Expedie',
                                    'delivered' => 'Livre',
                                ])
                                ->required(),

                            TextInput::make('city')
                                ->label('المدينة')
                                ->default(fn ($record) => $record->city)
                                ->nullable(),

                            TextInput::make('shipping_address')
                                ->label('العنوان')
                                ->default(fn ($record) => $record->shipping_address)
                                ->nullable(),

                            TextInput::make('total_price')
                                ->label('السعر')
                                ->numeric()
                                ->default(fn ($record) => $record->total_price)
                                ->nullable(),
                        ])
                    ->visible(fn ($record): bool => in_array(auth()->user()?->role, ['admin', 'delivery_man'], true)
                        && ! self::isDeliveredAndPaid($record)),

                Action::make('unsyncFromDelivery')
                    ->label('إلغاء من التوصيل')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => in_array(auth()->user()?->role, ['admin', 'confirmation'], true)
                        && in_array($record->status, ['confirmed', 'shipped', 'no_response', 'cancelled', 'refuse', 'reporter'], true)
                        && (filled($record->delivery_man_id) || filled($record->shipping_company))
                        && ! in_array(Livewire::current()?->activeTab ?? null, ['collection', 'completed'], true))
                    ->action(function ($record): void {
                        $record->update([
                            'delivery_man_id' => null,
                            'shipping_company' => null,
                            'shipping_company_id' => null,
                            'status' => 'confirmed',
                        ]);

                        Notification::make()
                            ->title('تم إرجاع الطلب إلى الانتظار')
                            ->success()
                            ->send();
                    }),

                Action::make('confirmDelivery')
                    ->label('تم التوصيل')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record->status === 'shipped')
                    ->action(function ($record): void {
                        $record->update([
                            'status' => 'delivered',
                        ]);

                        Notification::make()
                            ->title('تم تأكيد التوصيل بنجاح')
                            ->success()
                            ->send();
                    }),
                Action::make('confirmPayment')
                    ->label('تأكيد استلام المبلغ')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Placeholder::make('collection_summary')
                            ->label('ملخص التحصيل')
                            ->content(function (Order $record): string {
                                $gross = (float) ($record->total_price ?? 0);
                                $fees = (float) ($record->delivery_fee ?? 0);
                                $net = $gross - $fees;

                                return 'المجموع الكلي: '.number_format($gross, 2).' MAD'
                                    ."\n".'تكاليف التوصيل: '.number_format($fees, 2).' MAD'
                                    ."\n".'المبلغ الواجب تسليمه: '.number_format($net, 2).' MAD';
                            }),
                    ])
                    ->visible(fn (Order $record): bool => (Livewire::current()?->activeTab ?? null) === 'collection'
                        && $record->status === 'delivered'
                        && $record->payment_status !== 'paid'
                        && (
                            auth()->user()?->role === 'admin'
                            || (auth()->user()?->role === 'confirmation' && $record->isLocalDriverCashCollectionOrder())
                        ))
                    ->action(function ($record): void {
                        $record->update([
                            'payment_status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        Notification::make()
                            ->title('تم تأكيد قبض المبلغ')
                            ->success()
                            ->send();
                    }),

                Action::make('confirmCashCollectedFromDriver')
                    ->label('إقفال بعد تحصيل المبلغ من الموزع')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('تُعلَم الطلبية كمغلقة؛ تختفي من قائمة الموزّع النشطة وتظهر في تبويب «Completed» بالتطبيق.')
                    ->visible(fn (Order $record): bool => (Livewire::current()?->activeTab ?? null) === 'completed'
                        && $record->status === 'delivered'
                        && $record->payment_status === 'paid'
                        && (
                            in_array(auth()->user()?->role, ['admin', 'manager'], true)
                            || (auth()->user()?->role === 'confirmation' && $record->isLocalDriverCashCollectionOrder())
                        ))
                    ->action(function (Order $record): void {
                        $record->update(['status' => 'completed']);

                        Notification::make()
                            ->title('تم إقفال الطلبية')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('assignDeliveryMan')
                    ->label('Assigner a un livreur')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Select::make('delivery_man_id')
                            ->label('Livreur')
                            ->options(
                                User::query()
                                    ->where('role', 'delivery_man')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each(function ($order) use ($data): void {
                            $order->update([
                                'delivery_man_id' => $data['delivery_man_id'],
                                'shipping_company' => null,
                                'status' => 'shipped',
                            ]);
                        });

                        Notification::make()
                            ->title('تم تعيين الطلبات للموزع بنجاح')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'confirmation'], true)
                        && ! in_array(Livewire::current()?->activeTab ?? null, ['collection', 'completed'], true))
                    ->requiresConfirmation(),

                BulkAction::make('unsyncFromDelivery')
                    ->label('إلغاء من التوصيل')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'confirmation'], true)
                        && ! in_array(Livewire::current()?->activeTab ?? null, ['collection', 'completed'], true))
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $records->each(function ($order): void {
                            if (! in_array($order->status, ['confirmed', 'shipped', 'no_response', 'cancelled', 'refuse', 'reporter'], true)) {
                                return;
                            }

                            $order->update([
                                'delivery_man_id' => null,
                                'shipping_company' => null,
                                'shipping_company_id' => null,
                                'status' => 'confirmed',
                            ]);
                        });

                        Notification::make()
                            ->title('تم إرجاع الطلبات إلى الانتظار بنجاح')
                            ->success()
                            ->send();
                    }),

                BulkAction::make('confirmPaymentBulk')
                    ->label('تأكيد استلام المبلغ')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'collection'
                        && in_array(auth()->user()?->role, ['admin', 'confirmation'], true))
                    ->requiresConfirmation()
                    ->form(function (): array {
                        $livewire = Livewire::current();
                        $records = $livewire && method_exists($livewire, 'getSelectedTableRecords')
                            ? $livewire->getSelectedTableRecords()
                            : collect();

                        $eligible = $records->filter(fn (Order $order): bool => $order->status === 'delivered' && $order->payment_status !== 'paid');

                        $gross = (float) $eligible->sum(fn (Order $order): float => (float) ($order->total_price ?? 0));
                        $fees = (float) $eligible->sum(fn (Order $order): float => (float) ($order->delivery_fee ?? 0));
                        $net = $gross - $fees;

                        return [
                            Placeholder::make('bulk_collection_summary')
                                ->label('ملخص التحصيل')
                                ->content(
                                    'عدد الطلبيات: '.$eligible->count()
                                    ."\n".'المجموع الكلي: '.number_format($gross, 2).' MAD'
                                    ."\n".'تكاليف التوصيل: '.number_format($fees, 2).' MAD'
                                    ."\n".'المبلغ الواجب تسليمه: '.number_format($net, 2).' MAD'
                                ),
                        ];
                    })
                    ->action(function (Collection $records): void {
                        $updatedCount = 0;
                        $role = auth()->user()?->role;

                        $records->each(function (Order $order) use (&$updatedCount, $role): void {
                            if ($order->status !== 'delivered' || $order->payment_status === 'paid') {
                                return;
                            }

                            if ($role === 'confirmation' && ! $order->isLocalDriverCashCollectionOrder()) {
                                return;
                            }

                            $order->update([
                                'payment_status' => 'paid',
                                'paid_at' => now(),
                            ]);

                            $updatedCount++;
                        });

                        Notification::make()
                            ->title($updatedCount > 0
                                ? "تم تأكيد قبض المبلغ لـ {$updatedCount} طلب(ات)"
                                : 'لم يتم تحديث أي طلب')
                            ->success()
                            ->send();
                    }),

                BulkAction::make('closeAfterDriverSettlementBulk')
                    ->label('إقفال المحدد (تم تحصيل المبلغ من الموزع)')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'completed'
                        && (
                            in_array(auth()->user()?->role, ['admin', 'manager'], true)
                            || auth()->user()?->role === 'confirmation'
                        ))
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $updatedCount = 0;
                        $role = auth()->user()?->role;

                        $records->each(function (Order $order) use (&$updatedCount, $role): void {
                            if ($order->status !== 'delivered' || $order->payment_status !== 'paid') {
                                return;
                            }

                            if ($role === 'confirmation' && ! $order->isLocalDriverCashCollectionOrder()) {
                                return;
                            }

                            $order->update(['status' => 'completed']);
                            $updatedCount++;
                        });

                        Notification::make()
                            ->title($updatedCount > 0
                                ? "تم إقفال {$updatedCount} طلب(ات)"
                                : 'لم يتم تحديث أي طلب')
                            ->success()
                            ->send();
                    }),

                BulkAction::make('assignShippingCompany')
                    ->label('Assigner a une societe de livraison')
                    ->icon('heroicon-o-building-office-2')
                    ->modalWidth('5xl')
                    ->modalHeading('مراجعة المدن ثم المزامنة')
                    ->modalDescription('عند وجود قائمة مدن لشركة الشحن: الطلبيات المتطابقة تُرسل تلقائياً؛ يظهر هنا فقط ما يحتاج تصحيح المدينة.')
                    ->form(function (): array {
                        $livewire = Livewire::current();
                        if ($livewire === null || ! method_exists($livewire, 'getSelectedTableRecords')) {
                            return [
                                Placeholder::make('sync_error')
                                    ->label('')
                                    ->content('تعذر تحميل الطلبيات المحددة.'),
                            ];
                        }

                        /** @var Collection<int, Order> $records */
                        $records = $livewire->getSelectedTableRecords();
                        $records->loadMissing('orderItems.product');
                        $matcher = app(ShippingCompanyCityMatcher::class);

                        $fields = [
                            Select::make('shipping_company_id')
                                ->label('شركة الشحن')
                                ->options(fn (): array => ShippingCompany::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) use ($records, $matcher): void {
                                    $company = ShippingCompany::query()->find((int) $state);
                                    if ($company === null) {
                                        $set('order_cities', []);

                                        return;
                                    }

                                    $presets = [];
                                    foreach ($records as $order) {
                                        $match = $matcher->findMatchingCity($company, (string) $order->city);
                                        $presets[$order->id] = $match?->id;
                                    }
                                    $set('order_cities', $presets);
                                }),

                            Placeholder::make('city_match_summary')
                                ->label('ملخص المطابقة')
                                ->content(function (Get $get) use ($records, $matcher): HtmlString {
                                    $cid = $get('shipping_company_id');
                                    if (! $cid) {
                                        return new HtmlString('<span class="text-gray-500 text-sm">اختر شركة الشحن لعرض ملخص المطابقة.</span>');
                                    }

                                    $company = ShippingCompany::query()->find((int) $cid);
                                    if ($company === null) {
                                        return new HtmlString('');
                                    }

                                    if (! $company->cities()->active()->exists()) {
                                        return new HtmlString(
                                            '<p class="text-sm text-gray-600 dark:text-gray-400">لا توجد مدن مسجّلة لهذه الشركة — يُستخدم حقل المدينة من الطلبية كما هو عند المزامنة.</p>'
                                        );
                                    }

                                    $auto = 0;
                                    $need = 0;
                                    foreach ($records as $order) {
                                        if ($matcher->findMatchingCity($company, (string) $order->city) !== null) {
                                            $auto++;
                                        } else {
                                            $need++;
                                        }
                                    }

                                    if ($need === 0) {
                                        return new HtmlString(
                                            '<div class="rounded-lg border border-success-200 bg-success-50/80 p-3 text-sm dark:border-success-900/40 dark:bg-success-950/30">'.
                                            "<p class=\"font-semibold text-success-800 dark:text-success-300\">جميع المدن متطابقة مع القائمة ({$auto} طلبية).</p>".
                                            '<p class="mt-1 text-success-700 dark:text-success-400">اضغط «تأكيد» لإرسال الكل إلى شركة الشحن.</p>'.
                                            '</div>'
                                        );
                                    }

                                    return new HtmlString(
                                        '<div class="rounded-lg border border-amber-200 bg-amber-50/80 p-3 text-sm dark:border-amber-900/40 dark:bg-amber-950/30">'.
                                        "<p><span class=\"font-semibold text-gray-800 dark:text-gray-200\">تطابق تلقائي:</span> {$auto} طلبية — تُرسل دون اختيار إضافي.</p>".
                                        "<p class=\"mt-2\"><span class=\"font-semibold text-amber-800 dark:text-amber-300\">يحتاج تصحيح المدينة:</span> {$need} — اختر المدينة الصحيحة لكل طلبية أدناه.</p>".
                                        '</div>'
                                    );
                                })
                                ->columnSpanFull(),

                            Placeholder::make('city_fix_column_headers')
                                ->label('')
                                ->content(function (Get $get) use ($records, $matcher): HtmlString {
                                    $cid = $get('shipping_company_id');
                                    if (! $cid) {
                                        return new HtmlString('');
                                    }

                                    $company = ShippingCompany::query()->find((int) $cid);
                                    if ($company === null || ! $company->cities()->active()->exists()) {
                                        return new HtmlString('');
                                    }

                                    $need = 0;
                                    foreach ($records as $order) {
                                        if ($matcher->findMatchingCity($company, (string) $order->city) === null) {
                                            $need++;
                                        }
                                    }

                                    if ($need === 0) {
                                        return new HtmlString('');
                                    }

                                    return new HtmlString(
                                        '<div class="mb-2 hidden grid-cols-12 gap-2 border-b border-gray-200 pb-2 text-xs font-semibold text-gray-600 lg:grid dark:border-gray-600 dark:text-gray-400">'.
                                        '<span class="col-span-2">رقم الطلبية</span>'.
                                        '<span class="col-span-1">الحالة</span>'.
                                        '<span class="col-span-2">المدينة</span>'.
                                        '<span class="col-span-2">الزبون</span>'.
                                        '<span class="col-span-1">الهاتف</span>'.
                                        '<span class="col-span-1">المجموع</span>'.
                                        '<span class="col-span-1">المنتجات</span>'.
                                        '<span class="col-span-2">اختر اسم مدينة صحيح</span>'.
                                        '</div>'
                                    );
                                })
                                ->columnSpanFull(),
                        ];

                        foreach ($records as $order) {
                            $productsPlain = $order->orderItems
                                ->map(function ($item): string {
                                    $name = trim((string) ($item->product?->name ?? ''));
                                    if ($name === '') {
                                        return '';
                                    }
                                    $qty = max(1, (int) ($item->quantity ?? 1));

                                    return $name.' × '.$qty;
                                })
                                ->filter()
                                ->implode('، ');
                            if ($productsPlain === '') {
                                $productsPlain = '—';
                            }

                            $productsShort = Str::limit($productsPlain, 48, '…');

                            $statusLabel = match ((string) $order->status) {
                                'pending' => 'في الانتظار',
                                'confirmed' => 'مؤكّد',
                                'no_response' => 'لا يجيب',
                                'cancelled' => 'ملغى',
                                'refuse' => 'مرفوض',
                                'reporter' => 'مؤجّل',
                                'shipped' => 'مُرسل',
                                'delivered' => 'مُسلّم',
                                default => (string) $order->status,
                            };

                            $priceDisplay = number_format((float) ($order->total_price ?? 0), 2, '.', ' ').' MAD';

                            $fields[] = Section::make()
                                ->heading('')
                                ->schema([
                                    TextInput::make('ship_row_'.$order->id.'_num')
                                        ->label('رقم الطلبية')
                                        ->default($order->number)
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(['default' => 12, 'lg' => 2]),
                                    TextInput::make('ship_row_'.$order->id.'_status')
                                        ->label('الحالة')
                                        ->default($statusLabel)
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(['default' => 12, 'lg' => 1]),
                                    TextInput::make('ship_row_'.$order->id.'_city_order')
                                        ->label('المدينة (الطلبية)')
                                        ->default(filled($order->city) ? $order->city : '—')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(['default' => 12, 'lg' => 2]),
                                    TextInput::make('ship_row_'.$order->id.'_client')
                                        ->label('الزبون')
                                        ->default(filled($order->customer_name) ? $order->customer_name : '—')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(['default' => 12, 'lg' => 2]),
                                    TextInput::make('ship_row_'.$order->id.'_phone')
                                        ->label('الهاتف')
                                        ->default(filled($order->customer_phone) ? $order->customer_phone : '—')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(['default' => 12, 'lg' => 1]),
                                    TextInput::make('ship_row_'.$order->id.'_price')
                                        ->label('المجموع')
                                        ->default($priceDisplay)
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(['default' => 12, 'lg' => 1]),
                                    TextInput::make('ship_row_'.$order->id.'_products')
                                        ->label('المنتجات')
                                        ->default($productsShort)
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->extraInputAttributes(['title' => $productsPlain])
                                        ->columnSpan(['default' => 12, 'lg' => 1]),
                                    Select::make('order_cities.'.$order->id)
                                        ->label('اختر اسم مدينة صحيح')
                                        ->placeholder('اختر المدينة')
                                        ->options(function (Get $get): array {
                                            $cid = $get('shipping_company_id');
                                            if (! $cid) {
                                                return [];
                                            }

                                            return ShippingCompanyCity::query()
                                                ->where('shipping_company_id', (int) $cid)
                                                ->where('is_active', true)
                                                ->orderBy('sort_order')
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->required(function (Get $get) use ($order, $matcher): bool {
                                            $cid = $get('shipping_company_id');
                                            if (! $cid) {
                                                return false;
                                            }

                                            $company = ShippingCompany::query()->find((int) $cid);
                                            if ($company === null || ! $company->cities()->active()->exists()) {
                                                return false;
                                            }

                                            return $matcher->findMatchingCity($company, (string) $order->city) === null;
                                        })
                                        ->helperText('Vitips / Express')
                                        ->columnSpan(['default' => 12, 'lg' => 2]),
                                ])
                                ->columns(12)
                                ->extraAttributes([
                                    'class' => 'rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900/40',
                                ])
                                ->visible(function (Get $get) use ($order, $matcher): bool {
                                    $cid = $get('shipping_company_id');
                                    if (! $cid) {
                                        return false;
                                    }

                                    $company = ShippingCompany::query()->find((int) $cid);
                                    if ($company === null || ! $company->cities()->active()->exists()) {
                                        return false;
                                    }

                                    return $matcher->findMatchingCity($company, (string) $order->city) === null;
                                })
                                ->columnSpanFull();
                        }

                        return $fields;
                    })
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

                        $eligible = $records->filter(
                            fn (Order $order): bool => $order->status === 'confirmed'
                                && $order->shipping_company_id === null
                                && blank($order->shipping_company)
                        );

                        if ($eligible->isEmpty()) {
                            Notification::make()
                                ->title('لا توجد طلبية قابلة للإسناد')
                                ->body('يجب أن تكون الحالة «مؤكّد» وبدون شركة شحن مسبقاً (تبويب Pending فقط).')
                                ->warning()
                                ->send();

                            return;
                        }

                        $matcher = app(ShippingCompanyCityMatcher::class);
                        $orderCitiesInput = $data['order_cities'] ?? [];

                        $autoCityMatchCount = 0;
                        if ($shippingCompany->cities()->active()->exists()) {
                            foreach ($eligible as $order) {
                                if ($matcher->findMatchingCity($shippingCompany, (string) $order->city) !== null) {
                                    $autoCityMatchCount++;
                                }
                            }
                        }

                        foreach ($eligible as $order) {
                            $picked = $orderCitiesInput[$order->id] ?? null;
                            $picked = $picked !== null && $picked !== '' ? (int) $picked : null;

                            if ($shippingCompany->cities()->active()->exists()) {
                                $match = $matcher->findMatchingCity($shippingCompany, (string) $order->city);
                                $finalId = $picked ?? $match?->id;

                                if ($finalId === null) {
                                    Notification::make()
                                        ->title('الطلبية '.$order->number.': اختر المدينة الصحيحة.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $cityRow = ShippingCompanyCity::query()
                                    ->where('shipping_company_id', $shippingCompany->id)
                                    ->whereKey($finalId)
                                    ->first();

                                if ($cityRow === null) {
                                    Notification::make()
                                        ->title('مدينة غير صالحة لهذه الشركة.')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                OrderCarrierCitySelection::query()->updateOrCreate(
                                    [
                                        'order_id' => $order->id,
                                        'shipping_company_id' => $shippingCompany->id,
                                    ],
                                    ['shipping_company_city_id' => $finalId]
                                );
                            } else {
                                OrderCarrierCitySelection::query()
                                    ->where('order_id', $order->id)
                                    ->where('shipping_company_id', $shippingCompany->id)
                                    ->delete();
                            }
                        }

                        $shippingManager = app(ShippingManager::class);
                        $success = 0;
                        $failed = 0;
                        $errors = [];
                        try {
                            $batch = $shippingManager->processMany($eligible->values(), $shippingCompanyId);
                            $results = $batch['results'] ?? [];
                            $batchProvider = $batch['provider'] ?? null;
                        } catch (\Throwable $e) {
                            Log::error('Deliveries bulk shipping batch failed before per-order loop', [
                                'shipping_company_id' => $shippingCompanyId,
                                'exception' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('فشل مزامنة الشحن')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $batchOrders = $eligible->values();
                        $batchOrderCount = $batchOrders->count();
                        $isExpressBatch = $batchProvider === 'express_coursier';

                        /** @var Order $order */
                        foreach ($batchOrders as $batchIndex => $order) {
                            try {
                                $result = $results[$order->id] ?? [
                                    'code' => 'error',
                                    'message' => 'No result returned for this order.',
                                    'tracking_number' => null,
                                    'response' => [],
                                ];

                                if (($result['code'] ?? '') !== 'ok') {
                                    $failed++;
                                    $raw = $result['response'] ?? [];
                                    $rawText = is_array($raw) ? json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $raw;
                                    $apiMsg = trim((string) ($result['message'] ?? ''));
                                    $line = "Order #{$order->id}: ".($apiMsg !== '' ? $apiMsg : 'API response is not ok.');
                                    if ($apiMsg === '' && $rawText !== '') {
                                        $line .= " | {$rawText}";
                                    }
                                    $errors[] = $line;

                                    continue;
                                }

                                $responsePayload = is_array($result['response'] ?? null) ? $result['response'] : [];
                                $batchIdx = $isExpressBatch ? $batchIndex : null;
                                $batchTot = $isExpressBatch ? $batchOrderCount : null;

                                $parsedTracking = $shippingManager->parseTrackingFromProviderResponseForOrder(
                                    $responsePayload,
                                    $order,
                                    $batchIdx,
                                    $batchTot,
                                );
                                // Prefer parsed row; `result['tracking_number']` is the same per-order value from processMany.
                                $tracking = filled($parsedTracking)
                                    ? $parsedTracking
                                    : ($result['tracking_number'] ?? null);

                                $providerStatus = $shippingManager->parseProviderStatusForOrder(
                                    $responsePayload,
                                    $order,
                                    $batchIdx,
                                    $batchTot,
                                );

                                $updatePayload = [
                                    'shipping_company_id' => $shippingCompany->id,
                                    'shipping_company' => $shippingCompany->name,
                                    'delivery_man_id' => null,
                                    'status' => 'shipped',
                                    'tracking_number' => $tracking ?? $order->tracking_number,
                                ];

                                if (filled($providerStatus)) {
                                    $updatePayload['shipping_provider_status'] = $providerStatus;
                                }

                                $order->update($updatePayload);

                                if (blank($tracking)) {
                                    Log::warning('Shipping sync OK but no tracking parsed from provider JSON', [
                                        'order_id' => $order->id,
                                        'shipping_company_id' => $shippingCompanyId,
                                        'response' => $responsePayload,
                                    ]);
                                }

                                $success++;
                            } catch (\Throwable $e) {
                                $failed++;
                                $errors[] = "Order #{$order->id}: {$e->getMessage()}";

                                Log::error('Deliveries bulk shipping sync failed', [
                                    'order_id' => $order->id,
                                    'shipping_company_id' => $shippingCompanyId,
                                    'exception' => $e->getMessage(),
                                ]);
                            }
                        }

                        $cityLine = $shippingCompany->cities()->active()->exists()
                            ? "تطابق تلقائي للمدينة: {$autoCityMatchCount} | "
                            : '';

                        Notification::make()
                            ->title("{$cityLine}تم الإرسال بنجاح: {$success} | فشل: {$failed}")
                            ->body($failed > 0 ? collect($errors)->take(5)->implode("\n") : null)
                            ->{$failed > 0 ? 'warning' : 'success'}()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'confirmation'], true)
                        && (Livewire::current()?->activeTab ?? null) === 'pending'),
            ])
            ->headerActions([
                Action::make('deliveryStatistics')
                    ->label('إحصائيات التوصيل')
                    ->icon('heroicon-o-chart-bar')
                    ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'confirmation', 'delivery_man'], true))
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                        Select::make('delivery_man_id')
                            ->label('الموزع')
                            ->options(
                                User::query()
                                    ->where('role', 'delivery_man')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'confirmation'], true)),
                    ])
                    ->action(function (array $data): void {
                        $query = Order::query()
                            ->whereIn('status', ['delivered', 'completed'])
                            ->whereNotNull('delivery_man_id');

                        if (auth()->user()?->role === 'delivery_man') {
                            $query->where('delivery_man_id', auth()->id());
                        } elseif (filled($data['delivery_man_id'] ?? null)) {
                            $query->where('delivery_man_id', $data['delivery_man_id']);
                        }

                        if (filled($data['from'] ?? null)) {
                            $query->whereDate('updated_at', '>=', $data['from']);
                        }

                        if (filled($data['until'] ?? null)) {
                            $query->whereDate('updated_at', '<=', $data['until']);
                        }

                        $deliveredCount = (clone $query)->count();
                        $gross = (float) (clone $query)->sum('total_price');
                        $fees = (float) (clone $query)->sum('delivery_fee');
                        $net = $gross - $fees;

                        $body = 'عدد الطلبيات المسلمة: '.$deliveredCount
                            ."\n".'المجموع الكلي: '.number_format($gross, 2).' MAD'
                            ."\n".'مجموع تكاليف التوصيل (Benefit): '.number_format($fees, 2).' MAD'
                            ."\n".'المبلغ الواجب تسليمه: '.number_format($net, 2).' MAD';

                        if (in_array(auth()->user()?->role, ['admin', 'confirmation'], true)) {
                            $perDelivery = Order::query()
                                ->whereIn('status', ['delivered', 'completed'])
                                ->whereNotNull('delivery_man_id')
                                ->when(
                                    filled($data['from'] ?? null),
                                    fn (Builder $q): Builder => $q->whereDate('updated_at', '>=', $data['from'])
                                )
                                ->when(
                                    filled($data['until'] ?? null),
                                    fn (Builder $q): Builder => $q->whereDate('updated_at', '<=', $data['until'])
                                )
                                ->selectRaw('delivery_man_id, COUNT(*) as delivered_count, SUM(delivery_fee) as fees_sum')
                                ->groupBy('delivery_man_id')
                                ->get();

                            if ($perDelivery->isNotEmpty()) {
                                $names = User::query()
                                    ->whereIn('id', $perDelivery->pluck('delivery_man_id'))
                                    ->pluck('name', 'id');

                                $lines = $perDelivery->map(function ($row) use ($names): string {
                                    $name = $names[$row->delivery_man_id] ?? ('#'.$row->delivery_man_id);

                                    return $name.' => طلبيات: '.$row->delivered_count.' | Benefit: '.number_format((float) ($row->fees_sum ?? 0), 2).' MAD';
                                })->implode("\n");

                                $body .= "\n\n".'ربح كل موزع:'."\n".$lines;
                            }
                        }

                        Notification::make()
                            ->title('إحصائيات التوصيل')
                            ->body($body)
                            ->success()
                            ->send();
                    }),
                Action::make('importShippingInvoices')
                    ->label('استيراد فاتورة الشحن')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading('استيراد فاتورة Vitips أو Express Coursier')
                    ->modalDescription('ارفع ملفاً من لوحة الشحن: PDF أو نص (.txt / .csv). يُنشأ استيراد يعرض كل سطر (عميل، مدينة، مبلغ، Frais). ثم من صفحة الاستيراد اضغط «تحصيل أموال» لـ Vitips أو Express بشكل منفصل.')
                    ->modalSubmitActionLabel('معالجة')
                    ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'confirmation'], true)
                        && (Livewire::current()?->activeTab ?? null) === 'shipping_companies')
                    ->form([
                        Select::make('invoice_carrier_filter')
                            ->label('نطاق المعالجة')
                            ->options([
                                'both' => 'Vitips Express + Express Coursier',
                                'vitips' => 'Vitips Express فقط (تجاهل Express Coursier)',
                                'express' => 'Express Coursier فقط (تجاهل Vitips)',
                            ])
                            ->default('both')
                            ->native(false)
                            ->required(),
                        FileUpload::make('invoice_files')
                            ->label('ملفات الفاتورة')
                            ->helperText('Vitips: عمود Code d\'envoi = نفس رقم التتبع (CL-…). Express: إما المعرف الطويل 2603…-553C… أو سطر أرقام فقط ثم Livré: (مثل 2020002555 يطابق CL-2020002555 في القاعدة).')
                            ->multiple()
                            ->disk('local')
                            ->directory('shipping-invoice-imports')
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'text/plain',
                                'text/csv',
                                '.pdf',
                                '.txt',
                                '.csv',
                            ])
                            ->maxFiles(15)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $paths = $data['invoice_files'] ?? [];
                        if (! is_array($paths)) {
                            $paths = array_filter([$paths]);
                        }

                        $disk = Storage::disk('local');
                        $reader = app(ShippingInvoiceFileReader::class);
                        $combined = '';
                        $readErrors = [];

                        foreach ($paths as $path) {
                            if (! is_string($path) || $path === '') {
                                continue;
                            }
                            if (! $disk->exists($path)) {
                                continue;
                            }

                            try {
                                $combined .= "\n".$reader->textFromStoragePath($disk, $path);
                            } catch (\Throwable $e) {
                                $readErrors[] = basename($path).': '.$e->getMessage();
                                Log::warning('Shipping invoice file read failed', [
                                    'path' => $path,
                                    'exception' => $e->getMessage(),
                                ]);
                            }
                        }

                        if ($readErrors !== []) {
                            Notification::make()
                                ->title('فشل قراءة بعض الملفات')
                                ->body(implode("\n", array_slice($readErrors, 0, 5)))
                                ->danger()
                                ->send();
                        }

                        if (trim($combined) === '') {
                            Notification::make()
                                ->title('لا يوجد نص قابل للمعالجة')
                                ->body('تأكد أن PDF يحتوي على نص وليس صوراً فقط. على Windows: ثبّت Poppler وأضف pdftotext إلى PATH (أو عيّن PDFTOTEXT_BINARY في .env). أو استورد CSV/TXT بدلاً من PDF.')
                                ->warning()
                                ->send();

                            foreach ($paths as $path) {
                                if (is_string($path) && $path !== '' && $disk->exists($path)) {
                                    $disk->delete($path);
                                }
                            }

                            return;
                        }

                        $carrierFilter = $data['invoice_carrier_filter'] ?? 'both';
                        if (! is_string($carrierFilter) || ! in_array($carrierFilter, ['both', 'vitips', 'express'], true)) {
                            $carrierFilter = 'both';
                        }

                        /** @var ShippingInvoiceImportRecordResult $importResult */
                        $importResult = app(ShippingInvoiceImportRecorder::class)->recordFromText(
                            $combined,
                            $carrierFilter,
                            auth()->id(),
                        );

                        foreach ($paths as $path) {
                            if (is_string($path) && $path !== '' && $disk->exists($path)) {
                                $disk->delete($path);
                            }
                        }

                        if ($importResult->import === null) {
                            if ($importResult->alreadyPaidRejectedCount > 0) {
                                Notification::make()
                                    ->title('لم يُسجَّل الاستيراد')
                                    ->body('لا يوجد سطر يُحفَظ في الفاتورة (كل الأسطر المستخرجة: غير موجود و/أو مدفوع مسبقاً — مرفوض).')
                                    ->warning()
                                    ->send();
                                self::sendShippingInvoiceAlreadyPaidNotification($importResult);
                            } else {
                                Notification::make()
                                    ->title('لم يُسجَّل الاستيراد')
                                    ->body('أسطر الفاتورة = 0 — لا يُطبَّق التحصيل. تحقق من النص أو النطاق (Vitips / Express).')
                                    ->warning()
                                    ->send();
                            }

                            return;
                        }

                        $import = $importResult->import;

                        $invoiceSuccessStatus = 'تم فاتورة بنجاح';
                        $orderIdsFromInvoice = $import->lines()
                            ->whereNotNull('order_id')
                            ->pluck('order_id')
                            ->unique()
                            ->filter()
                            ->values()
                            ->all();
                        if ($orderIdsFromInvoice !== []) {
                            Order::query()
                                ->whereIn('id', $orderIdsFromInvoice)
                                ->update(['shipping_provider_status' => $invoiceSuccessStatus]);
                        }

                        $vitipsParsed = $import->vitipsLines()->count();
                        $expressParsed = $import->expressLines()->count();
                        $eligibleVitips = $import->vitipsLines()->where('match_status', 'eligible')->count();
                        $eligibleExpress = $import->expressLines()->where('match_status', 'eligible')->count();
                        $notFound = $import->lines()->where('match_status', 'not_found')->count();
                        $ineligible = $import->lines()->where('match_status', 'ineligible')->count();
                        $skippedZero = $import->lines()->where('match_status', 'skipped_zero')->count();
                        $alreadyPaidCount = $importResult->alreadyPaidRejectedCount;

                        $title = 'تم تسجيل الفاتورة — راجع الأسطر ثم استخدم «تحصيل أموال».';
                        $bodyParts = [];
                        $bodyParts[] = "Vitips: {$vitipsParsed} سطر، Express: {$expressParsed} سطر.";
                        $bodyParts[] = "قابلة للتحصيل — Vitips: {$eligibleVitips} | Express: {$eligibleExpress}.";
                        if ($carrierFilter === 'vitips') {
                            $bodyParts[] = 'تم تجاهل أسطر Express في الملف (نطاق Vitips فقط).';
                        } elseif ($carrierFilter === 'express') {
                            $bodyParts[] = 'تم تجاهل أسطر Vitips في الملف (نطاق Express فقط).';
                        }

                        if ($vitipsParsed === 0 && $expressParsed === 0 && trim($combined) !== '') {
                            $bodyParts[] = 'لم يُستخرج أي سطر — غالباً PDF بدون نص أو جدول غير معروف.';
                        }

                        if ($notFound > 0) {
                            $bodyParts[] = "غير مطابق / غير موجود: {$notFound}.";
                        }
                        if ($ineligible > 0) {
                            $bodyParts[] = "Vitips غير مؤهل (ليست شحن+غير مدفوع): {$ineligible}.";
                        }
                        if ($alreadyPaidCount > 0) {
                            $bodyParts[] = "مدفوع مسبقاً — مرفوض (لم يُحفَظ في الفاتورة): {$alreadyPaidCount} — تفاصيل في الإشعار التالي.";
                        }

                        Notification::make()
                            ->title($title)
                            ->body(implode("\n", $bodyParts))
                            ->{$vitipsParsed + $expressParsed > 0 ? 'success' : 'warning'}()
                            ->actions([
                                Action::make('open_import')
                                    ->label('عرض الفاتورة والتحصيل')
                                    ->url(ShippingInvoiceImportResource::getUrl('view', ['record' => $import])),
                            ])
                            ->send();

                        if ($skippedZero > 0) {
                            Notification::make()
                                ->title('لديك بعض طلبيات عائدة')
                                ->body("{$skippedZero} سطر(ات) بـ Frais 0 (لن تُحصَّل تلقائياً).")
                                ->warning()
                                ->send();
                        }

                        self::sendShippingInvoiceAlreadyPaidNotification($importResult);
                    }),
            ])
            ->headerActionsPosition(HeaderActionsPosition::Bottom);
    }

    /**
     * Delivered+paid (awaiting office cash from driver) or fully closed — lock inline edits in the delivery table.
     */
    public static function isDeliveredAndPaid(Order $record): bool
    {
        if ($record->status === 'completed') {
            return true;
        }

        return $record->status === 'delivered' && $record->payment_status === 'paid';
    }

    private static function sendShippingInvoiceAlreadyPaidNotification(ShippingInvoiceImportRecordResult $result): void
    {
        if ($result->alreadyPaidRejectedCount === 0) {
            return;
        }

        $lines = collect($result->alreadyPaidRejectedLines)->map(function (array $line): string {
            $tag = ($line['carrier'] ?? '') === 'express' ? 'Express' : 'Vitips';

            return (string) ($line['tracking_key'] ?? '').' ('.$tag.')';
        });

        $maxList = 40;
        $shown = $lines->take($maxList)->implode("\n");
        $suffix = $lines->count() > $maxList
            ? "\n… و ".($lines->count() - $maxList).' آخر'
            : '';

        Notification::make()
            ->title('مدفوع مسبقاً — مرفوض')
            ->body(
                "لم تُحفَظ في الفاتورة (الطلبية مدفوعة مسبقاً) :\n".$shown.$suffix
            )
            ->warning()
            ->send();
    }
}
