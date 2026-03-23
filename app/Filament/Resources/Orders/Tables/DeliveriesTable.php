<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class DeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('رقم الطلبية')
                    ->searchable(),

                TextColumn::make('customer_name')
                    ->label('الزبون')
                    ->searchable(),

                TextColumn::make('deliveryMan.name')
                    ->label('Delivery Man')
                    ->placeholder('غير معين')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'warning',
                        'shipped' => 'warning',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'unpaid')
                    ->color(fn (?string $state): string => $state === 'paid' ? 'success' : 'warning'),

                TextColumn::make('total_price')
                    ->label('المجموع')
                    ->money('MAD'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('delivery_man_id')
                    ->label('Delivery Man')
                    ->options(
                        User::query()
                            ->where('role', 'delivery_man')
                            ->pluck('name', 'id')
                            ->toArray()
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Changer le statut')
                    ->form([
                        Select::make('status')
                            ->label('Statut')
                            ->options(fn (): array => auth()->user()?->role === 'delivery_man'
                                ? [
                                    'no_response' => 'Pas de reponse',
                                    'cancelled' => 'Annule',
                                    'shipped' => 'Expedie',
                                    'delivered' => 'Livre',
                                ]
                                : [
                                    'pending' => 'En attente',
                                    'confirmed' => 'Confirme',
                                    'no_response' => 'Pas de reponse',
                                    'cancelled' => 'Annule',
                                    'shipped' => 'Expedie',
                                    'delivered' => 'Livre',
                                ])
                            ->required(),
                    ])
                    ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'delivery_man'], true)),
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
                    ->visible(fn ($record): bool => in_array(auth()->user()?->role, ['admin', 'confirmation'], true)
                        && $record->status === 'delivered'
                        && $record->payment_status !== 'paid')
                    ->action(function ($record): void {
                        $record->update([
                            'payment_status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        Notification::make()
                            ->title('تم تأكيد الدفع')
                            ->body('جاري فتح صفحة الفاتورة للطباعة.')
                            ->success()
                            ->send();

                        redirect()->to(route('invoices.orders.show', $record));
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
                                'status' => 'shipped',
                            ]);
                        });
                    })
                    ->visible(fn (): bool => auth()->user()?->role === 'admin')
                    ->requiresConfirmation(),
            ]);
    }
}
