<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentReplyCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Comment $comment)
    {
    }
}
