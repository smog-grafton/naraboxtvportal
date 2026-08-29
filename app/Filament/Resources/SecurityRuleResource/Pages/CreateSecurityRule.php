<?php
namespace App\Filament\Resources\SecurityRuleResource\Pages;
use App\Filament\Resources\SecurityRuleResource;
use Filament\Resources\Pages\CreateRecord;
class CreateSecurityRule extends CreateRecord { protected static string $resource = SecurityRuleResource::class; protected function mutateFormDataBeforeCreate(array $data): array { $data['created_by'] = auth()->id(); return $data; } }
