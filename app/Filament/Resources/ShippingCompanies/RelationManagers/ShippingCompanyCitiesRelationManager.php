<?php

namespace App\Filament\Resources\ShippingCompanies\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingCompanyCitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'cities';

    protected static ?string $title = 'مدن شركة الشحن';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم المدينة')
                    ->required()
                    ->maxLength(255),
                TagsInput::make('aliases')
                    ->label('أسماء بديلة للمطابقة')
                    ->helperText('اختياري — لمطابقة إملاءات مختلفة لحقل المدينة في الطلبية.')
                    ->placeholder('أضف واضغط Enter')
                    ->columnSpanFull(),
                TextInput::make('vitips_label')
                    ->label('نص Vitips')
                    ->helperText('التسمية كما في قائمة Vitips (مثلاً CASABLANCA).')
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('vitips_city_id')
                    ->label('معرّف Vitips (رقم)')
                    ->helperText('من value في &lt;option value="…"&gt; — يُرسل في حقل city للـ API.')
                    ->maxLength(32)
                    ->nullable(),
                TextInput::make('express_city_code')
                    ->label('رمز Express Coursier')
                    ->helperText('الرمز الرقمي للمدينة عند Express (إن وُجد).')
                    ->maxLength(32)
                    ->nullable(),
                TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('المدينة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vitips_label')
                    ->label('Vitips')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('vitips_city_id')
                    ->label('ID Vitips')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('express_city_code')
                    ->label('Express')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
