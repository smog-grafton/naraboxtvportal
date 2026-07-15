<?php

namespace App\Filament\Resources\MediaLibraryResource\Pages;

use App\Filament\Resources\MediaLibraryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\IconEntry;

class ViewMediaLibrary extends ViewRecord
{
    protected static string $resource = MediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('user.name')
                            ->label('Linked User')
                            ->placeholder('—'),
                        TextEntry::make('bio')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Media')
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Profile Image')
                            ->size(150)
                            ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name ?? '')),
                        ImageEntry::make('banner')
                            ->label('Banner')
                            ->height(120),
                    ])->columns(2),

                Section::make('Status')
                    ->schema([
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        IconEntry::make('is_verified')
                            ->label('Verified')
                            ->boolean(),
                        IconEntry::make('is_featured')
                            ->label('Featured')
                            ->boolean(),
                        TextEntry::make('featured_order')
                            ->label('Featured Order')
                            ->placeholder('—'),
                        TextEntry::make('movies_count')
                            ->label('Movies')
                            ->state(fn ($record) => $record->movies()->count()),
                        TextEntry::make('tv_shows_count')
                            ->label('TV Shows')
                            ->state(fn ($record) => $record->tvShows()->count()),
                    ])->columns(3),
            ]);
    }
}
