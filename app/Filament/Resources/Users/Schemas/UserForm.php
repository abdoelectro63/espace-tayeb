<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم المستخدم')
                    ->required(),

                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->required(),

                Select::make('role')
                    ->label('الدور')
                    ->options([
                        'admin' => 'Administrateur',
                        'staff' => 'Employe',
                        'delivery_man' => 'Livreur',
                        'confirmation' => 'Confirmation',
                        'manager' => 'Manager',
                    ])
                    ->default('staff')
                    ->required(),

                DateTimePicker::make('email_verified_at')
                    ->label('تاريخ تفعيل الإيميل'),

                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->required(fn (string $context): bool => $context === 'create') // مطلوبة فقط عند إنشاء مستخدم جديد
                    ->dehydrated(fn ($state) => filled($state)) // لا ترسل الحقل لقاعدة البيانات إذا كان فارغاً عند التعديل
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state)), // تشفير تلقائي قبل الحفظ
            ]);
    }
}