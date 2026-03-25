<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Product;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTrashed()->with('orderItems.product'))
            // بمجرد تعريف الـ Bulk Actions، ستظهر الـ Checkboxes تلقائياً في الجدول
            ->columns([
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
                
                // كود الحالة الملون الذي أعددناه سابقاً
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'confirmed' => 'info',
                        'no_response' => 'warning',
                        'cancelled' => 'danger',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        default => 'primary',
                    }),
            ])
            ->filters([
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
                            ->where('name', 'like', '%' . $data['product_name'] . '%'));
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
                                ->where('customer_name', 'like', '%' . $data['customer_name'] . '%'))
                            ->when(filled($data['city'] ?? null), fn (Builder $q): Builder => $q
                                ->where('city', 'like', '%' . $data['city'] . '%'));
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
                            }, "orders_export_" . now()->format('Y-m-d') . ".csv");
                        })
                        ->requiresConfirmation() // يطلب تأكيد قبل التحميل
                        ->modalHeading('تصدير الطلبات المختارة')
                        ->modalDescription('هل أنت متأكد من رغبتك في تحميل بيانات الطلبات التي قمت بتحديدها؟')
                        ->modalSubmitActionLabel('تحميل الآن'),
                ]),
            ]);
    }
}