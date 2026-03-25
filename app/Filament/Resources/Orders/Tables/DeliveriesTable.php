<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\ShippingCompany;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                    ->state(fn ($record): string => $record->deliveryMan?->name ?: ($record->shipping_company ?: 'غير معين'))
                    ->searchable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'shipped' => 'warning',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'unpaid')
                    ->color(fn (?string $state): string => $state === 'paid' ? 'success' : 'danger'),

                TextColumn::make('total_price')
                    ->label('المجموع')
                    ->money('MAD'),
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
                            ->title('تم تأكيد قبض المبلغ')
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
                    ->visible(fn (): bool => auth()->user()?->role === 'admin')
                    ->requiresConfirmation(),
                BulkAction::make('assignShippingCompany')
                    ->label('Assigner a une societe de livraison')
                    ->icon('heroicon-o-building-office-2')
                    ->form([
                        Select::make('shipping_company')
                            ->label('Shipping Company')
                            ->options(fn (): array => ShippingCompany::query()
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each(function ($order) use ($data): void {
                            $order->update([
                                'shipping_company' => $data['shipping_company'],
                                'delivery_man_id' => null,
                                'status' => 'shipped',
                            ]);
                        });

                        Notification::make()
                            ->title('تم تعيين الطلبات لشركة الشحن بنجاح')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => auth()->user()?->role === 'admin')
                    ->requiresConfirmation(),
            ]);
    }
}
