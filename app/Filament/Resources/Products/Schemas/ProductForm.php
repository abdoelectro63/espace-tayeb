<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Support\ImageOptimizer;
use Filament\Forms;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema; // تم تصحيح المسار هنا
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
                            ->imageEditor()
                            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                return ImageOptimizer::processAndStore($file, 'products/titles');
                            })
                            ->helperText('تُحوَّل تلقائياً إلى WebP، بعرض أقصى 1000px وجودة 80%.')
                            ->required(fn (?Model $record): bool => $record === null),

                        Forms\Components\FileUpload::make('images')
                            ->label('صور إضافية للمنتج')
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->directory('products/gallery')
                            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                return ImageOptimizer::processAndStore($file, 'products/gallery');
                            })
                            ->helperText('نفس المعالجة: WebP، عرض أقصى 1000px، جودة 80%.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
