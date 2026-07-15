<?php

namespace App\Events;

use App\Models\MediaPlaybackReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaybackIssueReported
{
    use Dispatchable, SerializesModels;

    public function __construct(public MediaPlaybackReport $report)
    {
    }
}
