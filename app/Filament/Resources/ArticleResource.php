<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Models\ArticleTag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel = 'Articles';
    protected static ?string $modelLabel = 'Article';
    protected static ?string $pluralModelLabel = 'Articles';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Article Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated from title'),
                        Forms\Components\Textarea::make('excerpt')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('author')
                            ->required()
                            ->maxLength(255)
                            ->default('Nara Editorial Team'),
                        Forms\Components\Select::make('category')
                            ->options([
                                'Updates' => 'Updates',
                                'Movies' => 'Movies',
                                'TV Shows' => 'TV Shows',
                                'Industry' => 'Industry',
                                'Platform' => 'Platform',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now()),
                    ])->columns(2),

                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Featured Image (Upload)')
                            ->image()
                            ->directory('articles')
                            ->maxSize(5120) // 5MB
                            ->helperText('Upload featured image'),
                        Forms\Components\TextInput::make('image_url')
                            ->label('Featured Image URL (Alternative)')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Or use URL instead of upload'),
                        Forms\Components\Select::make('video_type')
                            ->label('Video Type')
                            ->options([
                                'none' => 'No Video',
                                'youtube' => 'YouTube',
                                'vimeo' => 'Vimeo',
                                'url' => 'Direct URL',
                                'upload' => 'Local Upload',
                            ])
                            ->default('none')
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('video_url')
                            ->label('Video URL/ID')
                            ->maxLength(255)
                            ->helperText('YouTube/Vimeo ID or direct video URL')
                            ->visible(fn (Forms\Get $get) => in_array($get('video_type'), ['youtube', 'vimeo', 'url']))
                            ->required(fn (Forms\Get $get) => in_array($get('video_type'), ['youtube', 'vimeo', 'url'])),
                        Forms\Components\FileUpload::make('video_file')
                            ->label('Video File (Upload)')
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                            ->directory('articles/videos')
                            ->maxSize(102400) // 100MB
                            ->helperText('Upload video file (MP4, WebM, OGG)')
                            ->visible(fn (Forms\Get $get) => $get('video_type') === 'upload')
                            ->required(fn (Forms\Get $get) => $get('video_type') === 'upload'),
                    ]),

                Forms\Components\Section::make('Content Blocks')
                    ->description('Build your article content with rich text, images, quotes, and galleries')
                    ->schema([
                        Forms\Components\Repeater::make('blocks')
                            ->relationship('blocks')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'text' => 'Text (Rich Editor)',
                                        'image' => 'Image',
                                        'quote' => 'Quote',
                                        'gallery' => 'Gallery',
                                    ])
                                    ->required()
                                    ->live()
                                    ->columnSpanFull(),
                                
                                // Rich Text Editor for Text blocks
                                Forms\Components\RichEditor::make('value')
                                    ->label('Content')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'underline',
                                        'strike',
                                        'link',
                                        'bulletList',
                                        'orderedList',
                                        'blockquote',
                                        'codeBlock',
                                        'h2',
                                        'h3',
                                    ])
                                    ->required()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'text')
                                    ->columnSpanFull(),
                                
                                // Quote block
                                Forms\Components\RichEditor::make('value')
                                    ->label('Quote Text')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'link',
                                    ])
                                    ->required()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'quote')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('author')
                                    ->label('Quote Author')
                                    ->maxLength(255)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'quote'),
                                Forms\Components\TextInput::make('author_title')
                                    ->label('Author Title/Role')
                                    ->maxLength(255)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'quote'),
                                
                                // Image block
                                Forms\Components\FileUpload::make('image_file')
                                    ->label('Image (Upload)')
                                    ->image()
                                    ->directory('articles/blocks')
                                    ->maxSize(5120) // 5MB
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'image')
                                    ->helperText('Upload image file'),
                                Forms\Components\TextInput::make('value')
                                    ->label('Image URL (Alternative)')
                                    ->url()
                                    ->maxLength(255)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'image')
                                    ->helperText('Or use URL instead of upload'),
                                Forms\Components\Textarea::make('caption')
                                    ->label('Image Caption')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'image')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('alt_text')
                                    ->label('Alt Text')
                                    ->maxLength(255)
                                    ->helperText('Accessibility description')
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'image'),
                                
                                // Gallery block
                                Forms\Components\Repeater::make('gallery_images')
                                    ->label('Gallery Images')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_file')
                                            ->label('Image (Upload)')
                                            ->image()
                                            ->directory('articles/galleries')
                                            ->maxSize(5120),
                                        Forms\Components\TextInput::make('image_url')
                                            ->label('Image URL (Alternative)')
                                            ->url()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('caption')
                                            ->label('Caption')
                                            ->maxLength(255),
                                    ])
                                    ->defaultItems(1)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['caption'] ?? 'Image')
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'gallery')
                                    ->columnSpanFull(),
                                
                                Forms\Components\TextInput::make('order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->helperText('Lower numbers appear first'),
                            ])
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                match($state['type'] ?? null) {
                                    'text' => 'Text Block',
                                    'image' => 'Image Block',
                                    'quote' => 'Quote Block',
                                    'gallery' => 'Gallery Block',
                                    default => 'Content Block'
                                }
                            )
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Tags & Settings')
                    ->schema([
                        Forms\Components\Repeater::make('tags')
                            ->relationship('tags')
                            ->schema([
                                Forms\Components\TextInput::make('tag')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['tag'] ?? null),
                        Forms\Components\Toggle::make('is_top_news')
                            ->label('Top News')
                            ->helperText('Feature this article as top news'),
                        Forms\Components\Toggle::make('is_published')
                            ->label('Published')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->size(50),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Updates' => 'info',
                        'Movies' => 'success',
                        'TV Shows' => 'warning',
                        'Industry' => 'danger',
                        'Platform' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_top_news')
                    ->label('Top News')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Updates' => 'Updates',
                        'Movies' => 'Movies',
                        'TV Shows' => 'TV Shows',
                        'Industry' => 'Industry',
                        'Platform' => 'Platform',
                    ]),
                Tables\Filters\TernaryFilter::make('is_top_news')
                    ->label('Top News'),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
