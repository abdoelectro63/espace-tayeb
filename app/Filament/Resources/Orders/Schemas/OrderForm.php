<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Product;
use App\Services\ShippingCalculator;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    /**
     * @param  array<int, array<string, mixed>|null>|null  $orderItems
     */
    public static function calculateTotalFromItems(?array $orderItems): float
    {
        if ($orderItems === null || $orderItems === []) {
            return 0.0;
        }

        $sum = collect($orderItems)->sum(function ($item) {
            if (! is_array($item)) {
                return 0.0;
            }

            $qty = (float) ($item['quantity'] ?? 0);
            $unit = (float) ($item['unit_price'] ?? 0);

            return $qty * $unit;
        });

        return round($sum, 2);
    }

    public static function recalculateShippingAndTotal(Get $get, Set $set): void
    {
        $items = $get('orderItems');
        $zone = (string) ($get('shipping_zone') ?? 'casablanca');
        $arr = is_array($items) ? $items : [];
        $fee = ShippingCalculator::feeForAdminOrderItems($arr, $zone);
        $set('shipping_fee', $fee);
        $set('total_price', round(self::calculateTotalFromItems($arr) + $fee, 2));
    }

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

                        Forms\Components\Select::make('shipping_zone')
                            ->label('منطقة التوصيل (المغرب)')
                            ->options([
                                'casablanca' => 'الدار البيضاء',
                                'other' => 'مدن أخرى بالمغرب',
                            ])
                            ->default('casablanca')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::recalculateShippingAndTotal($get, $set);
                            })
                            ->required(),

                        Forms\Components\TextInput::make('city')
                            ->label('المدينة (تفصيل)')
                            ->maxLength(255)
                            ->placeholder('مثال: الرباط، طنجة، أكادير…'),

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
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::recalculateShippingAndTotal($get, $set);
                            })
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($set, $state): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $product = Product::query()->find($state);
                                        if ($product === null) {
                                            return;
                                        }

                                        $price = filled($product->discount_price)
                                            ? $product->discount_price
                                            : $product->price;

                                        $set('unit_price', $price);
                                    }),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->numeric()
                                    ->prefix('MAD')
                                    ->required()
                                    ->live(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('إضافة منتج')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('shipping_fee')
                            ->label('رسوم التوصيل (حسب المنطقة والمنتجات)')
                            ->numeric()
                            ->prefix('MAD')
                            ->disabled()
                            ->dehydrated()
                            ->default(0),

                        Forms\Components\TextInput::make('total_price')
                            ->label('المجموع (منتجات + توصيل)')
                            ->numeric()
                            ->prefix('MAD')
                            ->disabled()
                            ->dehydrated()
                            ->default(0)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
