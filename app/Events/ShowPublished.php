<?php

namespace App\Events;

use App\Models\TVShow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShowPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public TVShow $show, public array $options = [])
    {
    }
}
