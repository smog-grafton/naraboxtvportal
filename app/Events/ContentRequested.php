<?php

namespace App\Events;

use App\Models\ContentRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public ContentRequest $contentRequest)
    {
    }
}
