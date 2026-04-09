<?php

namespace App\Filament\Pages;

use App\Settings\TrackingSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class TrackingSettingsPage extends Page
{
    protected static ?string $slug = 'tracking-settings';

    protected static ?string $navigationLabel = 'Pixels & tracking';

    protected static ?string $title = 'Pixels Meta / TikTok';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'المتجر';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed> */
    public array $trackingData = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $s = app(TrackingSettings::class);
        $this->trackingData = [
            'facebook_pixel_id' => $s->facebook_pixel_id,
            'facebook_access_token' => $s->facebook_access_token,
            'facebook_test_event_code' => $s->facebook_test_event_code,
            'tiktok_pixel_id' => $s->tiktok_pixel_id,
            'tiktok_access_token' => $s->tiktok_access_token,
            'tracking_debug' => $s->tracking_debug,
        ];
    }

    public function defaultTrackingForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('trackingData');
    }

    public function trackingForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Meta (Facebook) Pixel')
                    ->description('Pixel navigateur + Conversion API (Purchase) sur la page merci checkout — pas sur le statut payé admin.')
                    ->schema([
                        TextInput::make('facebook_pixel_id')
                            ->label('Pixel ID')
                            ->maxLength(64)
                            ->placeholder('ex. 123456789012345'),
                        TextInput::make('facebook_access_token')
                            ->label('Access token (Conversion API)')
                            ->password()
                            ->revealable()
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        TextInput::make('facebook_test_event_code')
                            ->label('Test event code (optionnel)')
                            ->maxLength(64)
                            ->helperText('Événements de test dans Events Manager.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('TikTok Pixel')
                    ->schema([
                        TextInput::make('tiktok_pixel_id')
                            ->label('Pixel ID')
                            ->maxLength(64),
                        TextInput::make('tiktok_access_token')
                            ->label('Access token (Events API)')
                            ->password()
                            ->revealable()
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Debug')
                    ->schema([
                        Toggle::make('tracking_debug')
                            ->label('Journaliser les réponses API (Laravel log)')
                            ->inline(false),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('trackingForm'),
                ])
                    ->id('tracking-settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment(Alignment::Start)
                            ->fullWidth(false)
                            ->sticky(false)
                            ->key('tracking-settings-form-actions'),
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
        $state = $this->getSchema('trackingForm')->getState();

        $s = app(TrackingSettings::class);
        $s->facebook_pixel_id = (string) ($state['facebook_pixel_id'] ?? '');
        $s->facebook_access_token = (string) ($state['facebook_access_token'] ?? '');
        $s->facebook_test_event_code = (string) ($state['facebook_test_event_code'] ?? '');
        $s->tiktok_pixel_id = (string) ($state['tiktok_pixel_id'] ?? '');
        $s->tiktok_access_token = (string) ($state['tiktok_access_token'] ?? '');
        $s->tracking_debug = (bool) ($state['tracking_debug'] ?? false);
        $s->save();

        forget_setting_cache();
        try {
            Artisan::call('settings:clear-cache');
        } catch (\Throwable) {
            // ignore
        }

        Notification::make()
            ->title('Pixels enregistrés')
            ->success()
            ->send();
    }
}
