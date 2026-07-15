<?php

namespace App\Providers;

use App\Events\CommentCreated;
use App\Events\CommentReplyCreated;
use App\Events\ContentRequested;
use App\Events\EpisodePublished;
use App\Events\MoviePublished;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Events\PlaybackIssueReported;
use App\Events\ShowPublished;
use App\Events\UserRegistered;
use App\Listeners\HandlePlaybackIssueReported;
use App\Listeners\HandlePublishedContent;
use App\Listeners\SendCommentNotifications;
use App\Listeners\SendContentRequestNotifications;
use App\Listeners\SendPaymentCommunications;
use App\Listeners\SendWelcomeCommunication;
use App\Models\CreatorApplication;
use App\Models\Movie;
use App\Models\TVShow;
use App\Observers\CreatorApplicationObserver;
use App\Policies\MoviePolicy;
use App\Policies\TVShowPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Movie::class => MoviePolicy::class,
        TVShow::class => TVShowPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();

        CreatorApplication::observe(CreatorApplicationObserver::class);

        Event::listen(UserRegistered::class, SendWelcomeCommunication::class);
        Event::listen(PaymentSucceeded::class, [SendPaymentCommunications::class, 'handleSuccess']);
        Event::listen(PaymentFailed::class, [SendPaymentCommunications::class, 'handleFailure']);
        Event::listen(CommentCreated::class, [SendCommentNotifications::class, 'handleComment']);
        Event::listen(CommentReplyCreated::class, [SendCommentNotifications::class, 'handleReply']);
        Event::listen(ContentRequested::class, SendContentRequestNotifications::class);
        Event::listen(PlaybackIssueReported::class, HandlePlaybackIssueReported::class);
        Event::listen(MoviePublished::class, [HandlePublishedContent::class, 'handleMovie']);
        Event::listen(ShowPublished::class, [HandlePublishedContent::class, 'handleShow']);
        Event::listen(EpisodePublished::class, [HandlePublishedContent::class, 'handleEpisode']);
    }
}
