<?php

namespace App\Filament\Resources\OperationalAuditLogResource\Pages;

use App\Filament\Resources\OperationalAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListOperationalAuditLogs extends ListRecords
{
    protected static string $resource = OperationalAuditLogResource::class;
}
