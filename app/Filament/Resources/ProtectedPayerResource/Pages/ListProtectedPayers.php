<?php
namespace App\Filament\Resources\ProtectedPayerResource\Pages;
use App\Filament\Resources\ProtectedPayerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListProtectedPayers extends ListRecords { protected static string $resource = ProtectedPayerResource::class; protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; } }
