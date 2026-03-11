<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $fillable = [
        'tmdb_id',
        'name',
        'poster_path',
        'backdrop_path',
        'overview',
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
        ];
    }

    public function movies(): HasMany
    {
        return $this->hasMany(Movie::class, 'collection_id');
    }
}
