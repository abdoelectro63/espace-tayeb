<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('معلومات الطلبية')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('رقم الطلبية')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'pending' => 'قيد الانتظار',
                                'confirmed' => 'تم التأكيد',
                                'no_response' => 'لا يجيب',
                                'cancelled' => 'ملغي',
                                'shipped' => 'في الطريق',
                                'delivered' => 'تم التوصيل',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\TextInput::make('customer_name')
                            ->label('اسم الزبون')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('رقم الهاتف')
                            ->required()
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\Select::make('city')
                            ->label('المدينة')
                            ->options([
                                'Casablanca' => 'الدار البيضاء',
                                'Rabat' => 'الرباط',
                            ])
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('total_price')
                            ->label('المجموع')
                            ->numeric()
                            ->prefix('MAD')
                            ->required(),

                        Forms\Components\Textarea::make('shipping_address')
                            ->label('عنوان التوصيل')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('تفاصيل الطلبية')
                    ->schema([
                        Forms\Components\Repeater::make('orderItems')
                            ->label('المنتجات')
                            ->relationship('orderItems')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->numeric()
                                    ->prefix('MAD')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('إضافة منتج')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}