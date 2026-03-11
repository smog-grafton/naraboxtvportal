<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Crew extends Model
{
    protected $fillable = [
        'crewable_type',
        'crewable_id',
        'tmdb_id',
        'name',
        'job',
        'department',
        'profile_image',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'order' => 'integer',
        ];
    }

    public function crewable(): MorphTo
    {
        return $this->morphTo();
    }
}
