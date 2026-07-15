<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityAlert extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'payload',
        'status',
        'emailed_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'emailed_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
