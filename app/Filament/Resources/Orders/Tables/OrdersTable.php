<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->withTrashed())
            // بمجرد تعريف الـ Bulk Actions، ستظهر الـ Checkboxes تلقائياً في الجدول
            ->columns([
                TextColumn::make('number')->label('رقم الطلبية')->searchable(),
                TextColumn::make('customer_name')->label('الزبون')->searchable(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'انتظار',
                        'confirmed' => 'تأكيد',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
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