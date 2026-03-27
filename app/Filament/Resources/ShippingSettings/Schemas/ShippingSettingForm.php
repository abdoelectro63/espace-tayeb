<?php

namespace App\Filament\Resources\ShippingSettings\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('التوصيل داخل المغرب')
                    ->description('يتم احتساب رسوم التوصيل حسب المدينة (الدار البيضاء أو باقي المدن). يمكن تعديل المبالغ في أي وقت.')
                    ->schema([
                        Forms\Components\TextInput::make('casablanca_fee')
                            ->label('الدار البيضاء (MAD)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->suffix('DH'),

                        Forms\Components\TextInput::make('other_cities_fee')
                            ->label('مدن أخرى بالمغرب (MAD)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->suffix('DH'),
                    ])
                    ->columns(2),

                Section::make('مظهر الهيدر (Frontend)')
                    ->description('تغيير الشعار ولون خلفية الهيدر ولون نص القائمة من لوحة التحكم.')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->directory('branding')
                            ->imageEditor()
                            ->helperText('يفضل SVG أو WebP بخلفية شفافة.'),

                        Forms\Components\TextInput::make('header_bg_color')
                            ->label('لون خلفية الهيدر')
                            ->default('#ffffff')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('#ffffff')
                            ->regex('/^#(?:[0-9a-fA-F]{3}){1,2}$/')
                            ->validationMessages([
                                'regex' => 'أدخل لونًا بصيغة Hex مثل #ffffff',
                            ]),

                        Forms\Components\TextInput::make('menu_text_color')
                            ->label('لون نص القائمة')
                            ->default('#0f172a')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('#0f172a')
                            ->regex('/^#(?:[0-9a-fA-F]{3}){1,2}$/')
                            ->validationMessages([
                                'regex' => 'أدخل لونًا بصيغة Hex مثل #0f172a',
                            ]),
                    ])
                    ->columns(2),

                Section::make('بانر الصفحة الرئيسية')
                    ->description('ارفع صورة بانر للواجهة الرئيسية وأضف رابط زر "اشتري الآن".')
                    ->schema([
                        Forms\Components\FileUpload::make('hero_banner_path')
                            ->label('صورة البانر')
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->directory('branding/banners')
                            ->imageEditor()
                            ->helperText('يفضل صورة عريضة (مثل 1600x600).'),
                        Forms\Components\TextInput::make('hero_banner_link')
                            ->label('رابط زر اشتري الآن')
                            ->placeholder('#products')
                            ->default('#products')
                            ->maxLength(2048),
                    ])
                    ->columns(2),
            ]);
    }
}
