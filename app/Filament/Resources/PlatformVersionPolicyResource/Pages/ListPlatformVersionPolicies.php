<?php
namespace App\Filament\Resources\PlatformVersionPolicyResource\Pages;
use App\Filament\Resources\PlatformVersionPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListPlatformVersionPolicies extends ListRecords
{
    protected static string $resource = PlatformVersionPolicyResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
