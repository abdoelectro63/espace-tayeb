<?php

namespace App\Filament\Resources\ManualInvoices\Schemas;

use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManualInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('En-tête')
                    ->schema([
                        TextInput::make('number')
                            ->label('N° facture')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Généré après enregistrement')
                            ->visible(fn ($livewire): bool => $livewire instanceof EditRecord),
                        DatePicker::make('invoice_date')
                            ->label('Date de facture')
                            ->native(false)
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Client — FACTURÉ À')
                    ->schema([
                        TextInput::make('client_company_name')
                            ->label('Raison sociale / Nom')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('client_ice')
                            ->label('ICE')
                            ->maxLength(100),
                        TextInput::make('client_if')
                            ->label('I.F.')
                            ->maxLength(100),
                        TextInput::make('client_rc')
                            ->label('RC')
                            ->maxLength(100),
                        Textarea::make('billing_address')
                            ->label('Adresse de facturation')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Lignes')
                    ->description('TVA : taux par défaut des paramètres facture (vendeur).')
                    ->schema([
                        Repeater::make('line_rows')
                            ->label('')
                            ->schema([
                                TextInput::make('designation')
                                    ->label('Désignation')
                                    ->required()
                                    ->maxLength(500)
                                    ->columnSpan(2),
                                TextInput::make('quantity')
                                    ->label('Qté')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('unit_price')
                                    ->label('Prix unitaire HT')
                                    ->numeric()
                                    ->step('0.01')
                                    ->minValue(0)
                                    ->required(),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Ajouter une ligne')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Notes internes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }
}
