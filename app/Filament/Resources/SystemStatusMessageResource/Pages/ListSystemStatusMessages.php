<?php
namespace App\Filament\Resources\SystemStatusMessageResource\Pages;
use App\Filament\Resources\SystemStatusMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListSystemStatusMessages extends ListRecords
{
    protected static string $resource = SystemStatusMessageResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
