<?php

namespace App\Listeners;

use App\Events\EpisodePublished;
use App\Events\MoviePublished;
use App\Events\ShowPublished;
use App\Services\UserNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandlePublishedContent implements ShouldQueue
{
    public function handleMovie(MoviePublished $event): void
    {
        app(UserNotificationService::class)->createGlobal([
            'title' => 'New movie added',
            'message' => $event->movie->title . ' is now on Narabox TV.',
            'type' => 'new_movie',
            'image_url' => $event->movie->thumbnail,
            'action_url' => 'app://movie/' . $event->movie->id,
            'media_type' => 'movie',
            'media_id' => $event->movie->id,
        ]);
    }

    public function handleShow(ShowPublished $event): void
    {
        app(UserNotificationService::class)->createGlobal([
            'title' => 'New TV show added',
            'message' => $event->show->title . ' is now on Narabox TV.',
            'type' => 'new_show',
            'image_url' => $event->show->thumbnail,
            'action_url' => 'app://tv-show/' . $event->show->id,
            'media_type' => 'show',
            'media_id' => $event->show->id,
        ]);
    }

    public function handleEpisode(EpisodePublished $event): void
    {
        app(UserNotificationService::class)->createGlobal([
            'title' => 'New episode added',
            'message' => $event->episode->title . ' is now available.',
            'type' => 'new_episode',
            'action_url' => 'app://episode/' . $event->episode->id,
            'media_type' => 'episode',
            'media_id' => $event->episode->id,
        ]);
    }
}
