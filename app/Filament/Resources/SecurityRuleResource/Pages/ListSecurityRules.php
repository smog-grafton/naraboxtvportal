<?php
namespace App\Filament\Resources\SecurityRuleResource\Pages;
use App\Filament\Resources\SecurityRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListSecurityRules extends ListRecords { protected static string $resource = SecurityRuleResource::class; protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; } }
