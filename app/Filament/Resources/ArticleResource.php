<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VJ;
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
    protected static ?string $navigationLabel = 'Posts';
    protected static ?string $modelLabel = 'Post';
    protected static ?string $pluralModelLabel = 'Posts';
    protected static ?string $navigationGroup = 'Editorial';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Post Basics')
                    ->schema([
                        Forms\Components\Select::make('post_type')
                            ->options([
                                'news' => 'News',
                                'review' => 'Review',
                                'movie_spotlight' => 'Movie Spotlight',
                                'vj_profile' => 'VJ Profile',
                                'feature' => 'Feature',
                            ])
                            ->required()
                            ->default('news')
                            ->live(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated from title, but can be customized.'),
                        Forms\Components\Select::make('primary_category_id')
                            ->label('Primary Category')
                            ->relationship(
                                name: 'primaryCategory',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('name')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\TextInput::make('color'),
                                Forms\Components\Textarea::make('description')->rows(3),
                                Forms\Components\Toggle::make('is_active')->default(true),
                            ]),
                        Forms\Components\Textarea::make('excerpt')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('author_snapshot')
                            ->label('Author')
                            ->content(fn (?Article $record) => $record?->author ?: auth()->user()?->name ?: 'Assigned automatically on create'),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        Forms\Components\Toggle::make('is_top_news')
                            ->label('Top Story')
                            ->helperText('Feature this post prominently on the homepage.'),
                        Forms\Components\Toggle::make('is_published')
                            ->label('Published')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Review and Linked Content')
                    ->schema([
                        Forms\Components\TextInput::make('score')
                            ->numeric()
                            ->step('0.1')
                            ->minValue(0)
                            ->maxValue(10)
                            ->visible(fn (Forms\Get $get) => $get('post_type') === 'review')
                            ->required(fn (Forms\Get $get) => $get('post_type') === 'review'),
                        Forms\Components\Textarea::make('verdict')
                            ->rows(3)
                            ->visible(fn (Forms\Get $get) => $get('post_type') === 'review')
                            ->required(fn (Forms\Get $get) => $get('post_type') === 'review')
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('pros')
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label('Pro')
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->minItems(1)
                            ->visible(fn (Forms\Get $get) => $get('post_type') === 'review')
                            ->required(fn (Forms\Get $get) => $get('post_type') === 'review')
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('cons')
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label('Con')
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->minItems(1)
                            ->visible(fn (Forms\Get $get) => $get('post_type') === 'review')
                            ->required(fn (Forms\Get $get) => $get('post_type') === 'review')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('movie_id')
                            ->label('Linked Movie')
                            ->relationship(name: 'movie', titleAttribute: 'title', modifyQueryUsing: fn ($query) => $query->orderBy('title'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => in_array($get('post_type'), ['review', 'movie_spotlight', 'feature'], true))
                            ->required(fn (Forms\Get $get) => in_array($get('post_type'), ['review', 'movie_spotlight'], true) && blank($get('tv_show_id'))),
                        Forms\Components\Select::make('tv_show_id')
                            ->label('Linked TV Show')
                            ->relationship(name: 'tvShow', titleAttribute: 'title', modifyQueryUsing: fn ($query) => $query->orderBy('title'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => in_array($get('post_type'), ['review', 'movie_spotlight', 'feature'], true))
                            ->required(fn (Forms\Get $get) => in_array($get('post_type'), ['review', 'movie_spotlight'], true) && blank($get('movie_id'))),
                        Forms\Components\Select::make('vj_id')
                            ->label('Linked VJ')
                            ->relationship(name: 'vj', titleAttribute: 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get) => in_array($get('post_type'), ['vj_profile', 'feature'], true))
                            ->required(fn (Forms\Get $get) => $get('post_type') === 'vj_profile'),
                    ])->columns(2),

                Forms\Components\Section::make('Media and SEO')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Featured Image')
                            ->image()
                            ->directory('articles')
                            ->maxSize(5120),
                        Forms\Components\TextInput::make('image_url')
                            ->label('Featured Image URL')
                            ->url()
                            ->maxLength(2048),
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
                            ->maxSize(102400)
                            ->visible(fn (Forms\Get $get) => $get('video_type') === 'upload')
                            ->required(fn (Forms\Get $get) => $get('video_type') === 'upload')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('seo_title')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('seo_description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('og_image')
                            ->label('OG Image URL')
                            ->maxLength(2048),
                    ])->columns(2),

                Forms\Components\Section::make('Content Blocks')
                    ->description('Build rich editorial layouts with linked movies, shows, VJs, and calls to action.')
                    ->schema([
                        Forms\Components\Repeater::make('blocks')
                            ->relationship('blocks')
                            ->orderColumn('order')
                            ->reorderable()
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'rich_text' => 'Rich Text',
                                        'image' => 'Image',
                                        'quote' => 'Quote',
                                        'gallery' => 'Gallery',
                                        'movie_embed' => 'Movie Card',
                                        'tv_show_embed' => 'TV Show Card',
                                        'vj_embed' => 'VJ Card',
                                        'cta' => 'Call To Action',
                                    ])
                                    ->required()
                                    ->default('rich_text')
                                    ->live()
                                    ->columnSpanFull(),

                                Forms\Components\Hidden::make('value')
                                    ->dehydrated(true),

                                Forms\Components\RichEditor::make('rich_text_value')
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
                                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['rich_text', 'text'], true))
                                    ->dehydrated(fn (Forms\Get $get) => in_array($get('type'), ['rich_text', 'text'], true))
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('quote_value')
                                    ->label('Quote Text')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'link',
                                    ])
                                    ->required()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'quote')
                                    ->dehydrated(fn (Forms\Get $get) => $get('type') === 'quote')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('author')
                                    ->label('Quote Author')
                                    ->maxLength(255)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'quote'),
                                Forms\Components\TextInput::make('author_title')
                                    ->label('Author Title/Role')
                                    ->maxLength(255)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'quote'),
                                Forms\Components\FileUpload::make('image_file')
                                    ->label('Image Upload')
                                    ->image()
                                    ->directory('articles/blocks')
                                    ->maxSize(5120)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'image')
                                    ->dehydrated(fn (Forms\Get $get) => $get('type') === 'image')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'image' && blank($get('image_url_value'))),
                                Forms\Components\TextInput::make('image_url_value')
                                    ->label('Image URL')
                                    ->url()
                                    ->maxLength(2048)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'image')
                                    ->dehydrated(fn (Forms\Get $get) => $get('type') === 'image')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'image' && blank($get('image_file'))),
                                Forms\Components\Textarea::make('caption')
                                    ->label('Image Caption')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'image')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('alt_text')
                                    ->label('Alt Text')
                                    ->maxLength(255)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'image'),
                                Forms\Components\Textarea::make('gallery_input')
                                    ->label('Gallery Image URLs')
                                    ->helperText('Enter one image URL per line.')
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'gallery')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'gallery')
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('movie_id')
                                    ->label('Movie')
                                    ->relationship(name: 'movie', titleAttribute: 'title', modifyQueryUsing: fn ($query) => $query->orderBy('title'))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'movie_embed')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'movie_embed'),
                                Forms\Components\Select::make('tv_show_id')
                                    ->label('TV Show')
                                    ->relationship(name: 'tvShow', titleAttribute: 'title', modifyQueryUsing: fn ($query) => $query->orderBy('title'))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'tv_show_embed')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'tv_show_embed'),
                                Forms\Components\Select::make('vj_id')
                                    ->label('VJ')
                                    ->relationship(name: 'vj', titleAttribute: 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'vj_embed')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'vj_embed'),
                                Forms\Components\Textarea::make('cta_value')
                                    ->label('CTA Copy')
                                    ->rows(2)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'cta')
                                    ->dehydrated(fn (Forms\Get $get) => $get('type') === 'cta')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'cta')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('cta_label')
                                    ->label('CTA Button Label')
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'cta')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'cta'),
                                Forms\Components\TextInput::make('cta_url')
                                    ->label('CTA URL')
                                    ->url()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'cta')
                                    ->required(fn (Forms\Get $get) => $get('type') === 'cta'),
                            ])
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string =>
                                match($state['type'] ?? null) {
                                    'rich_text', 'text' => 'Rich Text',
                                    'image' => 'Image Block',
                                    'quote' => 'Quote Block',
                                    'gallery' => 'Gallery Block',
                                    'movie_embed' => 'Movie Card',
                                    'tv_show_embed' => 'TV Show Card',
                                    'vj_embed' => 'VJ Card',
                                    'cta' => 'CTA',
                                    default => 'Block'
                                }
                            )
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Tags')
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
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->size(50),
                Tables\Columns\TextColumn::make('post_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'review' => 'warning',
                        'movie_spotlight' => 'success',
                        'vj_profile' => 'info',
                        'feature' => 'gray',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),
                Tables\Columns\TextColumn::make('primaryCategory.name')
                    ->badge()
                    ->label('Category'),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->numeric(decimalPlaces: 1)
                    ->badge()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_top_news')
                    ->label('Top Story')
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
                Tables\Filters\SelectFilter::make('post_type')
                    ->options([
                        'news' => 'News',
                        'review' => 'Review',
                        'movie_spotlight' => 'Movie Spotlight',
                        'vj_profile' => 'VJ Profile',
                        'feature' => 'Feature',
                    ]),
                Tables\Filters\SelectFilter::make('primary_category_id')
                    ->relationship('primaryCategory', 'name')
                    ->label('Category'),
                Tables\Filters\TernaryFilter::make('is_top_news')
                    ->label('Top Story'),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->actions([
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
