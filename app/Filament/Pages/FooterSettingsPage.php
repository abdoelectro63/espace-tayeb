<?php

namespace App\Filament\Pages;

use App\Models\FooterLogo;
use App\Settings\FooterSettings;
use App\Support\FooterSocialIcons;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FooterSettingsPage extends Page
{
    protected static ?string $slug = 'footer-settings';

    protected static ?string $title = 'Footer settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed>|null */
    public ?array $footerData = [];

    /** @var array<string, mixed>|null */
    public ?array $logoData = [];

    public ?FooterLogo $footerLogoRecord = null;

    public static function canAccess(): bool
    {
        return ! in_array(auth()->user()?->role, ['delivery_man', 'manager'], true);
    }

    public function mount(): void
    {
        $this->footerLogoRecord = FooterLogo::singleton();

        $settings = app(FooterSettings::class);

        $this->footerData = [
            'copyright_text' => $settings->copyright_text,
            'tagline' => $settings->tagline,
            'social_links' => collect($settings->social_links ?: [])
                ->map(fn (array $link): array => [
                    ...$link,
                    'icon' => FooterSocialIcons::normalizeKey($link['icon'] ?? null),
                ])
                ->all(),
        ];

        $this->hydrateLogoFormState();
    }

    /**
     * SpatieMediaLibraryFileUpload only loads from the DB when form state is empty
     * in a way that triggers relationship hydration; an empty `logoData` array is
     * treated as real state, so existing media never loads and saving can run
     * deleteAbandonedFiles() and remove all footer logo files. Keep state in sync.
     */
    protected function hydrateLogoFormState(): void
    {
        $this->footerLogoRecord ??= FooterLogo::singleton();
        $this->footerLogoRecord->loadMissing('media');

        $logoMedia = $this->footerLogoRecord->getFirstMedia('logo');

        $this->logoData = $logoMedia
            ? ['logo' => [$logoMedia->uuid => $logoMedia->uuid]]
            : [];
    }

    public function defaultFooterForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('footerData');
    }

    public function footerForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('tagline')
                    ->label('وصف قصير في التذييل')
                    ->helperText('النص الظاهر تحت اسم المتجر في التذييل.')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                TextInput::make('copyright_text')
                    ->label('Copyright')
                    ->helperText('استخدم {year} لعرض السنة الحالية تلقائياً. مثال: © {year} . جميع الحقوق محفوظة.')
                    ->placeholder('© {year} . جميع الحقوق محفوظة.')
                    ->maxLength(500)
                    ->columnSpanFull(),
                Repeater::make('social_links')
                    ->label('Social links')
                    ->schema([
                        TextInput::make('platform')
                            ->label('الاسم الظاهر')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('url')
                            ->label('الرابط')
                            ->url()
                            ->required()
                            ->maxLength(2048),
                        ToggleButtons::make('icon')
                            ->label('الأيقونة')
                            ->helperText('اختر أيقونة تظهر بجانب الاسم في التذييل.')
                            ->options(FooterSocialIcons::options())
                            ->icons(FooterSocialIcons::toggleButtonIcons())
                            ->tooltips(FooterSocialIcons::options())
                            ->hiddenButtonLabels()
                            ->inline(false)
                            ->columns(6)
                            ->default('globe')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    public function defaultLogoForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->footerLogoRecord)
            ->statePath('logoData');
    }

    public function logoForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                SpatieMediaLibraryFileUpload::make('logo')
                    ->collection('logo')
                    ->disk('public')
                    ->image()
                    ->label('Footer logo'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('footerForm'),
                    EmbeddedSchema::make('logoForm'),
                ])
                    ->id('footer-settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment(Alignment::Start)
                            ->fullWidth(false)
                            ->sticky(false)
                            ->key('footer-settings-form-actions'),
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
        $footerState = $this->getSchema('footerForm')->getState();

        $footerSettings = app(FooterSettings::class);
        $footerSettings->copyright_text = (string) ($footerState['copyright_text'] ?? '');
        $footerSettings->tagline = (string) ($footerState['tagline'] ?? '');
        $footerSettings->social_links = $footerState['social_links'] ?? [];
        $footerSettings->save();

        $this->footerLogoRecord = FooterLogo::singleton();
        $this->footerLogoRecord->refresh();

        $this->getSchema('logoForm')->model($this->footerLogoRecord);
        $this->getSchema('logoForm')->getState();

        $this->footerLogoRecord->refresh();
        $this->hydrateLogoFormState();

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }
}
