<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\ShippingCalculator;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

    /**
     * Set shipping fee from zone + cart rules, then refresh order total.
     */
    public static function applyZoneBasedShippingFee(Get $get, Set $set): void
    {
        $items = $get('orderItems');
        $zone = (string) ($get('shipping_zone') ?? 'casablanca');
        $arr = is_array($items) ? $items : [];
        $fee = ShippingCalculator::feeForAdminOrderItems($arr, $zone);
        $set('shipping_fee', $fee);
        $set('total_price', round(self::calculateTotalFromItems($arr) + $fee, 2));
        $set('_free_shipping', ((float) $fee) <= 0.0);
    }

    /**
     * Keep current shipping_fee; only recompute total from line items (after qty/price changes).
     */
    public static function recalculateTotalFromItemsAndCurrentShipping(Get $get, Set $set): void
    {
        $items = $get('orderItems');
        $arr = is_array($items) ? $items : [];
        $fee = max(0, (float) ($get('shipping_fee') ?? 0));
        $set('shipping_fee', round($fee, 2));
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
                                'completed' => 'مغلقة (تحصيل من الموزع)',
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
                                self::applyZoneBasedShippingFee($get, $set);
                            })
                            ->required(),

                        Forms\Components\TextInput::make('city')
                            ->label('المدينة')
                            ->required()
                            ->maxLength(255),

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

                Section::make('الشحن والدفع')
                    ->description('رقم التتبع قابل للتعديل يدوياً — لا يُرسل شيء تلقائياً إلى شركة الشحن من هنا.')
                    ->schema([
                        Forms\Components\Select::make('shipping_company_id')
                            ->label('شركة الشحن')
                            ->relationship('shippingCompany', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('shipping_company')
                            ->label('اسم شركة الشحن (نص)')
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('رقم التتبع')
                            ->maxLength(255)
                            ->nullable()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('shipping_provider_status')
                            ->label('حالة المزوّد')
                            ->maxLength(255)
                            ->nullable(),
                        Forms\Components\Select::make('payment_status')
                            ->label('حالة الدفع')
                            ->options([
                                'unpaid' => 'غير مدفوع',
                                'paid' => 'مدفوع',
                            ])
                            ->default('unpaid')
                            ->live(),
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('تاريخ الدفع')
                            ->nullable(),
                        Forms\Components\Select::make('delivery_man_id')
                            ->label('الموزع')
                            ->relationship(
                                'deliveryMan',
                                'name',
                                fn ($query) => $query->where('role', 'delivery_man')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('تفاصيل الطلبية')
                    ->schema([
                        Forms\Components\Repeater::make('orderItems')
                            ->label('المنتجات')
                            ->relationship('orderItems')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::recalculateTotalFromItemsAndCurrentShipping($get, $set);
                            })
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name', fn ($query) => $query->with('variations'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                        $set('product_variation_id', null);
                                        if (blank($state)) {
                                            self::recalculateTotalFromItemsAndCurrentShipping($get, $set);

                                            return;
                                        }

                                        $product = Product::query()->with('variations')->find($state);
                                        if ($product === null) {
                                            self::recalculateTotalFromItemsAndCurrentShipping($get, $set);

                                            return;
                                        }

                                        if ($product->variations->isNotEmpty()) {
                                            $def = $product->getDefaultVariation();
                                            if ($def !== null) {
                                                $set('product_variation_id', $def->id);
                                                $set('unit_price', $def->price);

                                                self::recalculateTotalFromItemsAndCurrentShipping($get, $set);

                                                return;
                                            }
                                        }

                                        $price = filled($product->discount_price)
                                            ? $product->discount_price
                                            : $product->price;

                                        $set('unit_price', $price);
                                        self::recalculateTotalFromItemsAndCurrentShipping($get, $set);
                                    }),

                                Forms\Components\Select::make('product_variation_id')
                                    ->label('النوع')
                                    ->options(function (Get $get): array {
                                        $pid = $get('product_id');
                                        if (blank($pid)) {
                                            return [];
                                        }
                                        $product = Product::query()->with('variations')->find($pid);
                                        if ($product === null || $product->variations->isEmpty()) {
                                            return [];
                                        }

                                        return $product->variations
                                            ->mapWithKeys(fn (ProductVariation $v): array => [
                                                $v->id => $v->label().' — '.number_format((float) $v->price, 2).' MAD',
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->nullable()
                                    ->visible(function (Get $get): bool {
                                        $pid = $get('product_id');
                                        if (blank($pid)) {
                                            return false;
                                        }

                                        return Product::query()->whereKey($pid)->whereHas('variations')->exists();
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                        $pid = $get('product_id');
                                        if (blank($pid) || blank($state)) {
                                            self::recalculateTotalFromItemsAndCurrentShipping($get, $set);

                                            return;
                                        }
                                        $v = ProductVariation::query()
                                            ->whereKey($state)
                                            ->where('product_id', $pid)
                                            ->first();
                                        if ($v !== null) {
                                            $set('unit_price', $v->price);
                                        }
                                        self::recalculateTotalFromItemsAndCurrentShipping($get, $set);
                                    }),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::recalculateTotalFromItemsAndCurrentShipping($get, $set);
                                    }),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->numeric()
                                    ->prefix('MAD')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::recalculateTotalFromItemsAndCurrentShipping($get, $set);
                                    }),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('إضافة منتج')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('_free_shipping')
                            ->label('توصيل مجاني')
                            ->helperText('عند التفعيل تُصبح رسوم التوصيل 0. عند الإلغاء تُعاد حساب الرسوم حسب المنطقة والمنتجات.')
                            ->dehydrated(false)
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                if ($state) {
                                    $set('shipping_fee', 0);
                                    self::recalculateTotalFromItemsAndCurrentShipping($get, $set);

                                    return;
                                }
                                self::applyZoneBasedShippingFee($get, $set);
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('shipping_fee')
                            ->label('رسوم التوصيل')
                            ->helperText('يُحدَّث المجموع تلقائياً. تغيير منطقة التوصيل يعيد حساب الرسوم؛ تعديل المنتجات يحافظ على الرسوم الحالية.')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('MAD')
                            ->required()
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                $fee = max(0, (float) $state);
                                $set('_free_shipping', $fee <= 0.0);
                                self::recalculateTotalFromItemsAndCurrentShipping($get, $set);
                            }),

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
