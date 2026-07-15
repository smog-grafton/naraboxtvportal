<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContaboObjectStorageBucket extends Model
{
    protected $fillable = [
        'name',
        'bucket',
        'endpoint',
        'public_url',
        'path_prefix',
        'disk',
        'object_storage_id',
        's3_tenant_id',
        'user_id',
        'is_default',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
