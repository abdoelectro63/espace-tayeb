<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Menu')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('location')
                            ->options(Menu::locationOptions())
                            ->searchable()
                            ->nullable()
                            ->unique(table: 'menus', column: 'location', ignoreRecord: true),
                    ])
                    ->columns(2),
                Section::make('Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->orderColumn('order')
                            ->reorderable()
                            ->mutateRelationshipDataBeforeFillUsing(
                                fn (array $data): array => self::mutateRepeaterItemBeforeFill($data)
                            )
                            ->mutateRelationshipDataBeforeCreateUsing(
                                fn (array $data): array => self::stripVirtualFields($data)
                            )
                            ->mutateRelationshipDataBeforeSaveUsing(
                                fn (array $data, Model $record): array => self::stripVirtualFields($data)
                            )
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(255),
                                ToggleButtons::make('link_kind')
                                    ->label('Link type')
                                    ->inline()
                                    ->options([
                                        'page' => 'Page',
                                        'category' => 'Category',
                                        'contact' => 'Contact us',
                                        'custom' => 'Custom URL',
                                    ])
                                    ->default('page')
                                    ->live(),
                                Select::make('page_id')
                                    ->label('Page')
                                    ->options(fn (): array => Page::query()->orderBy('title')->pluck('title', 'id')->all())
                                    ->searchable()
                                    ->visible(fn ($get): bool => $get('link_kind') === 'page')
                                    ->required(fn ($get): bool => $get('link_kind') === 'page'),
                                Select::make('category_id')
                                    ->label('Category')
                                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->visible(fn ($get): bool => $get('link_kind') === 'category')
                                    ->required(fn ($get): bool => $get('link_kind') === 'category'),
                                TextInput::make('custom_url')
                                    ->label('URL')
                                    ->url()
                                    ->maxLength(2048)
                                    ->visible(fn ($get): bool => $get('link_kind') === 'custom')
                                    ->required(fn ($get): bool => $get('link_kind') === 'custom'),
                            ])
                            ->columns(1)
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function mutateRepeaterItemBeforeFill(array $data): array
    {
        if (filled($data['custom_url'] ?? null) && self::isContactPageUrl((string) $data['custom_url'])) {
            $data['link_kind'] = 'contact';
            $data['page_id'] = null;
            $data['category_id'] = null;

            return $data;
        }

        if (filled($data['custom_url'] ?? null)) {
            $data['link_kind'] = 'custom';
            $data['page_id'] = null;
            $data['category_id'] = null;

            return $data;
        }

        if (($data['linkable_type'] ?? null) === Page::class) {
            $data['link_kind'] = 'page';
            $data['page_id'] = $data['linkable_id'];
            $data['category_id'] = null;

            return $data;
        }

        if (($data['linkable_type'] ?? null) === Category::class) {
            $data['link_kind'] = 'category';
            $data['category_id'] = $data['linkable_id'];
            $data['page_id'] = null;

            return $data;
        }

        $data['link_kind'] = 'custom';
        $data['page_id'] = null;
        $data['category_id'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripVirtualFields(array $data): array
    {
        $kind = $data['link_kind'] ?? 'custom';
        $pageId = $data['page_id'] ?? null;
        $categoryId = $data['category_id'] ?? null;

        unset($data['link_kind'], $data['page_id'], $data['category_id']);

        if ($kind === 'page') {
            $data['linkable_type'] = Page::class;
            $data['linkable_id'] = $pageId;
            $data['custom_url'] = null;
        } elseif ($kind === 'category') {
            $data['linkable_type'] = Category::class;
            $data['linkable_id'] = $categoryId;
            $data['custom_url'] = null;
        } elseif ($kind === 'contact') {
            $data['linkable_type'] = null;
            $data['linkable_id'] = null;
            $data['custom_url'] = route('store.contact');
        } else {
            $data['linkable_type'] = null;
            $data['linkable_id'] = null;
        }

        return $data;
    }

    /**
     * Contact menu items are stored as {@see MenuItem::$custom_url} pointing at the named contact route.
     */
    public static function isContactPageUrl(string $url): bool
    {
        $contact = route('store.contact');
        $a = parse_url(rtrim($url, '/'), PHP_URL_PATH);
        $b = parse_url(rtrim($contact, '/'), PHP_URL_PATH);

        if ($a !== null && $b !== null && rtrim((string) $a, '/') === rtrim((string) $b, '/')) {
            return true;
        }

        return rtrim($url, '/') === rtrim($contact, '/');
    }
}
