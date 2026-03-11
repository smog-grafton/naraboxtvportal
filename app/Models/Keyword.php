<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Keyword extends Model
{
    protected $fillable = [
        'keywordable_type',
        'keywordable_id',
        'name',
        'tmdb_id',
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
        ];
    }

    public function keywordable(): MorphTo
    {
        return $this->morphTo();
    }
}
