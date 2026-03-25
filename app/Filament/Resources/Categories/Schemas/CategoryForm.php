<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        $iconOptions = collect(Heroicon::cases())
            ->mapWithKeys(fn (Heroicon $case): array => [
                'heroicon-o-'.$case->value => str_replace('_', ' ', $case->name),
            ])
            ->sort();

        return $schema
            ->components([
                Section::make('معلومات التصنيف')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم التصنيف')
                            ->required(),
                        TextInput::make('slug')
                            ->label('الرابط المختصر')
                            ->required(),
                        TextInput::make('category_id')
                            ->label('التصنيف الأب (معرف)')
                            ->numeric()
                            ->helperText('اتركه فارغاً للتصنيف الرئيسي'),
                    ])->columns(2),

                Section::make('الصورة والأيقونة')
                    ->schema([
                        FileUpload::make('image')
                            ->label('صورة التصنيف')
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->directory('categories/images')
                            ->imageEditor()
                            ->columnSpanFull(),

                        Select::make('icon')
                            ->label('الأيقونة')
                            ->searchable()
                            ->options($iconOptions->all())
                            ->placeholder('بدون أيقونة')
                            ->helperText('تُعرض في المتجر مع أسماء التصنيفات.'),
                    ]),
            ]);
    }
}
