<?php

namespace App\Filament\Pages;

use App\Models\InvoiceSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class InvoiceSettingsPage extends Page
{
    protected static ?string $slug = 'invoice-settings';

    protected static ?string $navigationLabel = 'Paramètres facture';

    protected static ?string $title = 'Paramètres facture';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Facturation';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed> */
    public array $invoiceData = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $s = InvoiceSetting::singleton();
        $this->invoiceData = [
            'seller_company_name' => $s->seller_company_name,
            'seller_address' => $s->seller_address,
            'seller_ice' => $s->seller_ice,
            'seller_if' => $s->seller_if,
            'seller_rc' => $s->seller_rc,
            'seller_patente' => $s->seller_patente,
            'seller_rib' => $s->seller_rib,
            'default_tva_rate' => (string) $s->default_tva_rate,
        ];
    }

    public function defaultInvoiceForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('invoiceData');
    }

    public function invoiceForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('seller_company_name')
                    ->label('Raison sociale (vendeur)')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('seller_address')
                    ->label('Adresse')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('seller_ice')
                    ->label('ICE')
                    ->maxLength(100),
                TextInput::make('seller_if')
                    ->label('I.F.')
                    ->maxLength(100),
                TextInput::make('seller_rc')
                    ->label('RC')
                    ->maxLength(100),
                TextInput::make('seller_patente')
                    ->label('Patente')
                    ->maxLength(100),
                TextInput::make('seller_rib')
                    ->label('RIB')
                    ->maxLength(100),
                TextInput::make('default_tva_rate')
                    ->label('TVA (%)')
                    ->numeric()
                    ->default('14'),
            ])
            ->columns(2);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('invoiceForm'),
                ])
                    ->id('invoice-settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment(Alignment::Start)
                            ->fullWidth(false)
                            ->sticky(false)
                            ->key('invoice-settings-form-actions'),
                    ]),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $state = $this->getSchema('invoiceForm')->getState();

        $s = InvoiceSetting::singleton();
        $s->fill([
            'seller_company_name' => filled($state['seller_company_name'] ?? null) ? (string) $state['seller_company_name'] : null,
            'seller_address' => filled($state['seller_address'] ?? null) ? (string) $state['seller_address'] : null,
            'seller_ice' => filled($state['seller_ice'] ?? null) ? (string) $state['seller_ice'] : null,
            'seller_if' => filled($state['seller_if'] ?? null) ? (string) $state['seller_if'] : null,
            'seller_rc' => filled($state['seller_rc'] ?? null) ? (string) $state['seller_rc'] : null,
            'seller_patente' => filled($state['seller_patente'] ?? null) ? (string) $state['seller_patente'] : null,
            'seller_rib' => filled($state['seller_rib'] ?? null) ? (string) $state['seller_rib'] : null,
            'default_tva_rate' => isset($state['default_tva_rate']) ? (float) $state['default_tva_rate'] : 14,
        ]);
        $s->save();

        Notification::make()
            ->title('Paramètres enregistrés')
            ->success()
            ->send();
    }
}
