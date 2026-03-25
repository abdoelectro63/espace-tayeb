<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Product;
use Carbon\Carbon;
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
use Filament\Tables;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTrashed()->with('orderItems.product'))
            ->defaultSort('created_at', 'desc')
            // بمجرد تعريف الـ Bulk Actions، ستظهر الـ Checkboxes تلقائياً في الجدول
            ->columns([
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('customer_name')->label('الزبون')->searchable(),
                TextInputColumn::make('customer_phone')->label('الهاتف'),
                TextInputColumn::make('city')->label('المدينة'),
                TextInputColumn::make('shipping_address')->label('العنوان'),
                TextColumn::make('products')
                    ->label('المنتجات')
                    ->state(fn ($record): string => $record->orderItems
                        ->map(fn ($item): ?string => $item->product?->name)
                        ->filter()
                        ->unique()
                        ->implode(', '))
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
                    }),

                SelectColumn::make('status')
                    ->label('تغيير الحالة')
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
                        'shipped' => 'تم الشحن',
                        'delivered' => 'تم التسليم',
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
                    ->label('Selected Product')
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
                    ->label('Product Name')
                    ->form([
                        TextInput::make('product_name')
                            ->label('Product Name'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['product_name'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('orderItems.product', fn (Builder $productQuery): Builder => $productQuery
                            ->where('name', 'like', '%'.$data['product_name'].'%'));
                    }),
                Tables\Filters\Filter::make('city_and_name')
                    ->label('City & Name')
                    ->form([
                        TextInput::make('customer_name')
                            ->label('Customer Name'),
                        TextInput::make('city')
                            ->label('City'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(filled($data['customer_name'] ?? null), fn (Builder $q): Builder => $q
                                ->where('customer_name', 'like', '%'.$data['customer_name'].'%'))
                            ->when(filled($data['city'] ?? null), fn (Builder $q): Builder => $q
                                ->where('city', 'like', '%'.$data['city'].'%'));
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'انتظار',
                        'confirmed' => 'تأكيد',
                    ]),
            ])
            ->recordActions([
                DeleteAction::make(),
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
                ]),
            ]);
    }
}
