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
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

                return $query;
            })
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
                        && ($livewire->activeTab ?? null) !== 'collection'),
                Tables\Filters\Filter::make('collection_carrier')
                    ->label('تصفية التحصيل')
                    ->visible(fn ($livewire): bool => auth()->user()?->role !== 'delivery_man'
                        && ($livewire->activeTab ?? null) === 'collection')
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
                    ->visible(fn (): bool => in_array(auth()->user()?->role, ['admin', 'delivery_man'], true)),

                Action::make('unsyncFromDelivery')
                    ->label('إلغاء من التوصيل')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => in_array(auth()->user()?->role, ['admin', 'confirmation'], true)
                        && in_array($record->status, ['confirmed', 'shipped', 'no_response', 'cancelled', 'refuse', 'reporter'], true)
                        && (filled($record->delivery_man_id) || filled($record->shipping_company)))
                    ->action(function ($record): void {
                        $record->update([
                            'delivery_man_id' => null,
                            'shipping_company' => null,
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
                    ->visible(fn ($record): bool => (Livewire::current()?->activeTab ?? null) === 'collection'
                        && in_array(auth()->user()?->role, ['admin', 'confirmation'], true)
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

                BulkAction::make('unsyncFromDelivery')
                    ->label('إلغاء من التوصيل')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => auth()->user()?->role === 'admin')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $records->each(function ($order): void {
                            if (! in_array($order->status, ['confirmed', 'shipped', 'no_response', 'cancelled', 'refuse', 'reporter'], true)) {
                                return;
                            }

                            $order->update([
                                'delivery_man_id' => null,
                                'shipping_company' => null,
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
                    ->action(function (Collection $records): void {
                        $updatedCount = 0;

                        $records->each(function ($order) use (&$updatedCount): void {
                            if ($order->status !== 'delivered' || $order->payment_status === 'paid') {
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
