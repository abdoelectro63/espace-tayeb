<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Support\ImageOptimizer;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                        Select::make('category_id')
                            ->label('التصنيف الأب')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->rule(function (?Category $record): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                                    if ($record !== null && filled($value) && (int) $value === (int) $record->id) {
                                        $fail('لا يمكن أن يكون التصنيف أبًا لنفسه.');
                                    }
                                };
                            })
                            ->placeholder('بدون تصنيف أب')
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
                            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                return ImageOptimizer::processAndStore($file, 'categories/images');
                            })
                            ->helperText('تُحوَّل تلقائياً إلى WebP، بعرض أقصى 1000px وجودة 80%.')
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
