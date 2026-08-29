<?php
namespace App\Filament\Resources\MaintenanceWindowResource\Pages;
use App\Filament\Resources\MaintenanceWindowResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListMaintenanceWindows extends ListRecords
{
    protected static string $resource = MaintenanceWindowResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
