<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    protected $fillable = ['key', 'value', 'description', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
