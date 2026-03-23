<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('الإيميل')
                    ->copyable() // ميزة مفيدة لنسخ الإيميل بضغطة واحدة
                    ->searchable(),

                TextColumn::make('role')
                    ->label('الدور')
                    ->badge() // بديل BadgeColumn في Filament v3
                    ->colors([
                        'primary' => 'admin',
                        'success' => 'staff',
                    ]),

                TextColumn::make('created_at')
                    ->label('تاريخ الانضمام')
                    ->dateTime('d/m/Y H:i') // تنسيق التاريخ ليكون أوضح
                    ->sortable(),
            ])
            ->filters([
                // يمكنك إضافة فلتر للدور هنا لاحقاً
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}