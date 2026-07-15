<?php

namespace App\Events;

use App\Models\Episode;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EpisodePublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public Episode $episode, public array $options = [])
    {
    }
}
