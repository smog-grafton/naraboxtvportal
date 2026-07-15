<?php

namespace App\Events;

use App\Models\Movie;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoviePublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public Movie $movie, public array $options = [])
    {
    }
}
