<?php
namespace App\Filament\Resources\ProtectedPayerResource\Pages;
use App\Filament\Resources\ProtectedPayerResource;
use Filament\Resources\Pages\CreateRecord;
class CreateProtectedPayer extends CreateRecord { protected static string $resource = ProtectedPayerResource::class; protected function mutateFormDataBeforeCreate(array $data): array { $data['created_by'] = auth()->id(); return $data; } }
