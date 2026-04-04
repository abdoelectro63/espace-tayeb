<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Support\PublicDiskFileCleanup;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'pages', column: 'slug', ignoreRecord: true),
                        RichEditor::make('content')
                            ->columnSpanFull(),
                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(false),
                    ])
                    ->columns(2),
                Section::make('Sections')
                    ->schema([
                        Builder::make('sections')
                            ->collapsible()
                            ->blocks([
                                Block::make('hero')
                                    ->label('Hero section')
                                    ->schema([
                                        TextInput::make('heading')->required(),
                                        TextInput::make('subheading'),
                                        FileUpload::make('image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('pages/sections/hero')
                                            ->imageEditor()
                                            ->deleteUploadedFileUsing(PublicDiskFileCleanup::filamentDeleteUploadedFile()),
                                        TextInput::make('cta_label'),
                                        TextInput::make('cta_url')->url(),
                                    ]),
                                Block::make('content_block')
                                    ->label('Content block')
                                    ->schema([
                                        RichEditor::make('body')
                                            ->columnSpanFull(),
                                    ]),
                                Block::make('image_block')
                                    ->label('Image block')
                                    ->schema([
                                        FileUpload::make('image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('pages/sections/images')
                                            ->imageEditor()
                                            ->deleteUploadedFileUsing(PublicDiskFileCleanup::filamentDeleteUploadedFile())
                                            ->required(),
                                        TextInput::make('alt')->label('Alt text'),
                                        TextInput::make('caption'),
                                    ]),
                                Block::make('gallery_block')
                                    ->label('Gallery block')
                                    ->schema([
                                        Repeater::make('images')
                                            ->schema([
                                                FileUpload::make('image')
                                                    ->image()
                                                    ->disk('public')
                                                    ->directory('pages/sections/gallery')
                                                    ->deleteUploadedFileUsing(PublicDiskFileCleanup::filamentDeleteUploadedFile())
                                                    ->required(),
                                                TextInput::make('caption'),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(1)
                                            ->collapsible(),
                                    ]),
                                Block::make('cta_block')
                                    ->label('CTA block')
                                    ->schema([
                                        TextInput::make('title')->required(),
                                        Textarea::make('body')->rows(3),
                                        TextInput::make('button_label'),
                                        TextInput::make('button_url')->url(),
                                        Select::make('style')
                                            ->options([
                                                'primary' => 'Primary',
                                                'secondary' => 'Secondary',
                                                'outline' => 'Outline',
                                            ])
                                            ->default('primary'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('SEO')
                    ->schema([
                        TextInput::make('seo_title')
                            ->maxLength(255),
                        Textarea::make('seo_description')
                            ->rows(3)
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('seo')
                            ->collection('seo')
                            ->disk('public')
                            ->image()
                            ->label('SEO image'),
                    ])
                    ->columns(2),
            ]);
    }
}
