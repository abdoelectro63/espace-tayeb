<?php

namespace App\Filament\Resources\InvoiceResource\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client — affiché sur la facture (Facturé à)')
                    ->description('Renseignez les informations légales du client pour cette commande. Elles remplacent tout réglage global.')
                    ->schema([
                        TextInput::make('invoice_client_company_name')
                            ->label('Raison sociale / Nom')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('invoice_client_ice')
                            ->label('ICE')
                            ->maxLength(100),
                        TextInput::make('invoice_client_if')
                            ->label('I.F.')
                            ->maxLength(100),
                        TextInput::make('invoice_client_rc')
                            ->label('RC')
                            ->maxLength(100),
                        Textarea::make('invoice_billing_address')
                            ->label('Adresse de facturation')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Lignes produits')
                    ->description('Nom catalogue : libellé actuel du produit. Libellé facture : texte imprimé sur le PDF (vide = nom catalogue).')
                    ->schema([
                        Repeater::make('invoice_lines')
                            ->label('')
                            ->schema([
                                Hidden::make('order_product_id'),
                                TextInput::make('catalog_label')
                                    ->label('Nom catalogue (référence)')
                                    ->disabled()
                                    ->columnSpan(1),
                                TextInput::make('invoice_designation')
                                    ->label('Libellé sur la facture')
                                    ->placeholder('Optionnel — remplace le nom catalogue')
                                    ->maxLength(500)
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->defaultItems(0),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
