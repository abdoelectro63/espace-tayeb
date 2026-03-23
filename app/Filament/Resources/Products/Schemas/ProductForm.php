<?php 

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section; // تم تصحيح المسار هنا
use Illuminate\Support\Str;

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
                    ])->columns(3),

                Section::make('صور المنتج')
                    ->description('ارفع الصورة الرئيسية وصور العرض الجانبية للأجهزة')
                    ->schema([
                        Forms\Components\FileUpload::make('main_image')
                            ->label('صورة المنتج الرئيسية (Thumbnail)')
                            ->image()
                            ->directory('products/titles')
                            ->imageEditor()
                            ->required(),

                        Forms\Components\FileUpload::make('images')
                            ->label('صور إضافية للمنتج')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->directory('products/gallery')
                            ->imageEditor()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('stock')
                            ->label('المخزون المتوفر')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ]),
            ]);
    }
}