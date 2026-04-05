<?php

namespace App\Filament\Pages;

use App\Models\ContactSetting;
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

class ContactPageSettings extends Page
{
    protected static ?string $slug = 'contact-page-settings';

    protected static ?string $title = 'Contact page';

    protected static ?string $navigationLabel = 'اتصل بنا';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed>|null */
    public ?array $contactData = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $c = ContactSetting::settings();

        $this->contactData = [
            'page_title' => $c->page_title,
            'phone' => $c->phone,
            'email' => $c->email,
            'address' => $c->address,
            'map_embed_html' => $c->map_embed_html,
            'seo_title' => $c->seo_title,
            'seo_description' => $c->seo_description,
        ];
    }

    public function defaultContactForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('contactData');
    }

    public function contactForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('page_title')
                    ->label('عنوان الصفحة')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('الهاتف')
                    ->tel()
                    ->maxLength(100),
                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->maxLength(255),
                Textarea::make('address')
                    ->label('العنوان (الشارع)')
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('map_embed_html')
                    ->label('خريطة Google (كود التضمين)')
                    ->helperText('من Google Maps: مشاركة ← تضمين خريطة ← انسخ كامل وسوم iframe والصقها هنا.')
                    ->rows(6)
                    ->columnSpanFull(),
                TextInput::make('seo_title')
                    ->label('SEO — عنوان الصفحة (اختياري)')
                    ->maxLength(255),
                Textarea::make('seo_description')
                    ->label('SEO — وصف مختصر (اختياري)')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('contactForm'),
                ])
                    ->id('contact-page-settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment(Alignment::Start)
                            ->fullWidth(false)
                            ->sticky(false)
                            ->key('contact-page-settings-form-actions'),
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
        $state = $this->getSchema('contactForm')->getState();

        $c = ContactSetting::settings();
        $c->fill([
            'page_title' => (string) ($state['page_title'] ?? 'اتصل بنا'),
            'phone' => filled($state['phone'] ?? null) ? (string) $state['phone'] : null,
            'email' => filled($state['email'] ?? null) ? (string) $state['email'] : null,
            'address' => filled($state['address'] ?? null) ? (string) $state['address'] : null,
            'map_embed_html' => filled($state['map_embed_html'] ?? null) ? (string) $state['map_embed_html'] : null,
            'seo_title' => filled($state['seo_title'] ?? null) ? (string) $state['seo_title'] : null,
            'seo_description' => filled($state['seo_description'] ?? null) ? (string) $state['seo_description'] : null,
        ]);
        $c->save();

        Notification::make()
            ->title('Saved')
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewStoreContact')
                ->label('عرض الصفحة')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (): string => route('store.contact'))
                ->openUrlInNewTab(),
        ];
    }
}
