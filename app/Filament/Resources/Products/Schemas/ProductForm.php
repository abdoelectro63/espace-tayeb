<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Support\ImageOptimizer;
use App\Support\PublicDiskFileCleanup;
use Filament\Forms;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema; // تم تصحيح المسار هنا
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('معلومات المنتج الأساسية')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم المنتج')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->label('الرابط المختصر (Slug)')
                            ->required()
                            ->unique('products', 'slug', ignoreRecord: true),

                        Forms\Components\Select::make('category_id')
                            ->label('التصنيف')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug($state))),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('تفاصيل صفحة المنتج')
                    ->description('هذه البيانات تظهر أسفل زر أضف إلى السلة في صفحة المنتج.')
                    ->schema([
                        Forms\Components\Select::make('cta_mode')
                            ->label('أزرار الشراء في صفحة المنتج')
                            ->options([
                                Product::CTA_ADD_TO_CART_AND_BUY_NOW => 'أضف إلى السلة + شراء الآن',
                                Product::CTA_BUY_NOW_ONLY => 'شراء الآن فقط',
                            ])
                            ->default(Product::CTA_ADD_TO_CART_AND_BUY_NOW)
                            ->required()
                            ->native(false)
                            ->helperText('تحكم في ظهور زر "أضف إلى السلة" داخل صفحة المنتج.'),
                        Forms\Components\Toggle::make('show_quantity_selector')
                            ->label('إظهار اختيار الكمية في صفحة المنتج')
                            ->default(true)
                            ->helperText('عند الإيقاف، يتم إرسال الطلب بكمية 1 تلقائياً.'),
                        Forms\Components\Radio::make('show_inline_checkout_form')
                            ->label('عرض نموذج إتمام الشراء أسفل زر "شراء الآن"')
                            ->options([
                                1 => 'نعم',
                                0 => 'لا',
                            ])
                            ->default(0)
                            ->required()
                            ->inline()
                            ->helperText('عند اختيار "نعم"، زر "شراء الآن" يمرر الزبون إلى النموذج داخل نفس الصفحة بدل النافذة المنبثقة.'),

                        Forms\Components\Repeater::make('specifications')
                            ->label('مواصفات المنتج')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('الاسم')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('value')
                                    ->label('القيمة')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('إضافة مواصفة')
                            ->collapsible()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('long_description')
                            ->label('الوصف الطويل')
                            ->rows(8)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('الأسعار والعروض')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('الثمن الأصلي')
                            ->numeric()
                            ->prefix('MAD')
                            ->required(),

                        Forms\Components\TextInput::make('discount_price')
                            ->label('ثمن العرض')
                            ->numeric()
                            ->prefix('MAD'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('تفعيل المنتج في المتجر')
                            ->default(true),

                        Forms\Components\Toggle::make('free_shipping')
                            ->label('توصيل مجاني لهذا المنتج')
                            ->helperText('إن كان مفعّلاً، لا تُحتسب رسوم التوصيل لهذا المنتج عند الطلب.')
                            ->default(false),
                    ])->columns(3),

                Section::make('عروض إضافية و upsell')
                    ->description('اربط منتجاً ثانياً ثم حدّد العرض: النسبة و«توصيل مجاني» يُطبَّقان على المنتج الثاني فقط، لا على هذا المنتج.')
                    ->schema([
                        Forms\Components\Select::make('upsell_id')
                            ->label('منتج مقترَح (البيع المجمّع)')
                            ->relationship(
                                name: 'upsellProduct',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query): void {
                                    $query->where('is_active', true);
                                    $record = request()->route('record');
                                    if ($record instanceof Product) {
                                        $query->whereKeyNot($record->getKey());
                                    }
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->helperText('يظهر في المتجر كقسم «اشتري الاثنين معاً» عند توفر المخزون.'),
                        Forms\Components\Select::make('offer_type')
                            ->label('نوع العرض (على المنتج المقترَح)')
                            ->options([
                                Product::OFFER_NONE => 'بدون',
                                Product::OFFER_PERCENTAGE => 'خصم نسبة على سعر المنتج المقترَح',
                                Product::OFFER_FREE_DELIVERY => 'توصيل مجاني للمنتج المقترَح (عرض)',
                            ])
                            ->default(Product::OFFER_NONE)
                            ->required()
                            ->native(false)
                            ->live()
                            ->visible(fn (Get $get): bool => filled($get('upsell_id'))),
                        Forms\Components\TextInput::make('offer_value')
                            ->label('نسبة الخصم على المنتج المقترَح')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->visible(fn (Get $get): bool => filled($get('upsell_id')) && $get('offer_type') === Product::OFFER_PERCENTAGE)
                            ->required(fn (Get $get): bool => filled($get('upsell_id')) && $get('offer_type') === Product::OFFER_PERCENTAGE),
                    ])
                    ->columns(2),

                Section::make('متغيرات المنتج')
                    ->description('اختياري: ألوان، مقاسات، إلخ. عند وجود متغيرات، يختار الزبون النوع ويُحسب السعر من سعر المتغير.')
                    ->schema([
                        Forms\Components\Repeater::make('variations')
                            ->relationship('variations')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('اسم الخاصية')
                                    ->placeholder('مثال: اللون')
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('value')
                                    ->label('القيمة')
                                    ->placeholder('مثال: أحمر')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('price')
                                    ->label('الثمن')
                                    ->numeric()
                                    ->prefix('MAD')
                                    ->required(),
                                Forms\Components\Toggle::make('is_default')
                                    ->label('افتراضي')
                                    ->helperText('يُعرض كاختيار أولي في المتجر.')
                                    ->default(false),
                            ])
                            ->columns(2)
                            ->addActionLabel('إضافة متغير')
                            ->collapsible()
                            ->reorderable()
                            ->defaultItems(0),
                    ])
                    ->columns(1),

                Section::make('المخزون والتوفر')
                    ->schema([
                        Forms\Components\Toggle::make('track_stock')
                            ->label('تحديد كمية محددة في المخزون')
                            ->helperText('عند الإيقاف: يُعرض المنتج كـ «متوفر» دون رقم، ولا يُحدّ الطلب برقم مخزون. عند التفعيل: أدخل عدد القطع المتوفرة.')
                            ->default(false)
                            ->live(),
                        Forms\Components\TextInput::make('stock')
                            ->label('الكمية المتوفرة')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => (bool) $get('track_stock'))
                            ->required(fn (Get $get): bool => (bool) $get('track_stock')),
                    ])
                    ->columns(1),

                Section::make('صور المنتج')
                    ->description('ارفع الصورة الرئيسية وصور العرض الجانبية للأجهزة')
                    ->schema([
                        Forms\Components\FileUpload::make('main_image')
                            ->label('صورة المنتج الرئيسية (Thumbnail)')
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->directory('products/titles')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->imageEditor()
                            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                return ImageOptimizer::processAndStore($file, 'products/titles', 'main_image');
                            })
                            ->helperText('تُحوَّل تلقائياً إلى WebP، بعرض أقصى 1000px وجودة 80%.')
                            ->required(fn (?Model $record): bool => $record === null),

                        Forms\Components\FileUpload::make('images')
                            ->label('صور إضافية للمنتج')
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->directory('products/gallery')
                            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                return ImageOptimizer::processAndStore($file, 'products/gallery', 'images');
                            })
                            ->deleteUploadedFileUsing(PublicDiskFileCleanup::filamentDeleteUploadedFile())
                            ->helperText('نفس المعالجة: WebP، عرض أقصى 1000px، جودة 80%.')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('detail_images')
                            ->label('صور قسم التفاصيل (4 صور)')
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->multiple()
                            ->maxFiles(4)
                            ->reorderable()
                            ->appendFiles()
                            ->directory('products/details')
                            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                return ImageOptimizer::processAndStore($file, 'products/details', 'detail_images');
                            })
                            ->deleteUploadedFileUsing(PublicDiskFileCleanup::filamentDeleteUploadedFile())
                            ->helperText('تظهر أسفل زر أضف إلى السلة في المتجر. الحد الأقصى 4 صور.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
