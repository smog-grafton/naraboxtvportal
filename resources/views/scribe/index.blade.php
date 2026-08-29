<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>NaraBox TV Portal API v1</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://127.0.0.1:8000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.8.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.8.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authentication" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authentication">
                    <a href="#authentication">Authentication</a>
                </li>
                                    <ul id="tocify-subheader-authentication" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-register">
                                <a href="#authentication-POSTapi-v1-auth-register">Register a new user</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-login">
                                <a href="#authentication-POSTapi-v1-auth-login">Login user with email and password</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-phone-request-otp">
                                <a href="#authentication-POSTapi-v1-auth-phone-request-otp">Request phone login OTP.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-phone-verify-otp">
                                <a href="#authentication-POSTapi-v1-auth-phone-verify-otp">Verify phone OTP and login/register user.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-google-mobile">
                                <a href="#authentication-POSTapi-v1-auth-google-mobile">Handle mobile Google login/register using an access token.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-apple-mobile">
                                <a href="#authentication-POSTapi-v1-auth-apple-mobile">Handle Apple login/register (mobile).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-verify-email">
                                <a href="#authentication-POSTapi-v1-auth-verify-email">Verify email with code</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-resend-verification">
                                <a href="#authentication-POSTapi-v1-auth-resend-verification">Resend verification code</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-forgot-password">
                                <a href="#authentication-POSTapi-v1-auth-forgot-password">Request password reset</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-reset-password">
                                <a href="#authentication-POSTapi-v1-auth-reset-password">Reset password with token</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-GETapi-v1-auth-google-url">
                                <a href="#authentication-GETapi-v1-auth-google-url">Get Google OAuth URL (for frontend web)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-GETapi-v1-auth-google">
                                <a href="#authentication-GETapi-v1-auth-google">Redirect to Google OAuth (web flow)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-GETapi-v1-auth-google-callback">
                                <a href="#authentication-GETapi-v1-auth-google-callback">Handle Google OAuth callback (web flow)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-GETapi-v1-auth-me">
                                <a href="#authentication-GETapi-v1-auth-me">Get current authenticated user</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-PUTapi-v1-auth-profile">
                                <a href="#authentication-PUTapi-v1-auth-profile">Update user profile</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-DELETEapi-v1-auth-account">
                                <a href="#authentication-DELETEapi-v1-auth-account">Delete user account</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="authentication-POSTapi-v1-auth-logout">
                                <a href="#authentication-POSTapi-v1-auth-logout">Logout user</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-hero" class="tocify-header">
                <li class="tocify-item level-1" data-unique="hero">
                    <a href="#hero">Hero</a>
                </li>
                                    <ul id="tocify-subheader-hero" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="hero-GETapi-v1-hero">
                                <a href="#hero-GETapi-v1-hero">Get hero slides for homepage</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-movies" class="tocify-header">
                <li class="tocify-item level-1" data-unique="movies">
                    <a href="#movies">Movies</a>
                </li>
                                    <ul id="tocify-subheader-movies" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="movies-GETapi-v1-movies">
                                <a href="#movies-GETapi-v1-movies">List movies</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="movies-GETapi-v1-movies-selected-today">
                                <a href="#movies-GETapi-v1-movies-selected-today">Return up to 14 movies selected once per day.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="movies-GETapi-v1-movies--id-">
                                <a href="#movies-GETapi-v1-movies--id-">GET api/v1/movies/{id}</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-tv-shows" class="tocify-header">
                <li class="tocify-item level-1" data-unique="tv-shows">
                    <a href="#tv-shows">TV Shows</a>
                </li>
                                    <ul id="tocify-subheader-tv-shows" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="tv-shows-GETapi-v1-tv-shows">
                                <a href="#tv-shows-GETapi-v1-tv-shows">GET api/v1/tv-shows</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="tv-shows-GETapi-v1-tv-shows--id-">
                                <a href="#tv-shows-GETapi-v1-tv-shows--id-">GET api/v1/tv-shows/{id}</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-search" class="tocify-header">
                <li class="tocify-item level-1" data-unique="search">
                    <a href="#search">Search</a>
                </li>
                                    <ul id="tocify-subheader-search" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="search-GETapi-v1-search">
                                <a href="#search-GETapi-v1-search">Search. Query: q (required). Response: data.archives, data.people, data.intel.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-vjs" class="tocify-header">
                <li class="tocify-item level-1" data-unique="vjs">
                    <a href="#vjs">VJs</a>
                </li>
                                    <ul id="tocify-subheader-vjs" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="vjs-GETapi-v1-vjs">
                                <a href="#vjs-GETapi-v1-vjs">List VJs. Query: featured (1), order_by (movies_count), limit.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="vjs-GETapi-v1-vjs--id-">
                                <a href="#vjs-GETapi-v1-vjs--id-">GET api/v1/vjs/{id}</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-articles" class="tocify-header">
                <li class="tocify-item level-1" data-unique="articles">
                    <a href="#articles">Articles</a>
                </li>
                                    <ul id="tocify-subheader-articles" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="articles-GETapi-v1-articles">
                                <a href="#articles-GETapi-v1-articles">List articles. Query: category, top_news, per_page.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="articles-GETapi-v1-articles--id-">
                                <a href="#articles-GETapi-v1-articles--id-">GET api/v1/articles/{id}</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-contact" class="tocify-header">
                <li class="tocify-item level-1" data-unique="contact">
                    <a href="#contact">Contact</a>
                </li>
                                    <ul id="tocify-subheader-contact" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="contact-POSTapi-v1-contact">
                                <a href="#contact-POSTapi-v1-contact">Submit contact form. Body: name, email, subject, message.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-live-streams" class="tocify-header">
                <li class="tocify-item level-1" data-unique="live-streams">
                    <a href="#live-streams">Live Streams</a>
                </li>
                                    <ul id="tocify-subheader-live-streams" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="live-streams-GETapi-v1-live-streams">
                                <a href="#live-streams-GETapi-v1-live-streams">Get active live streams. Query: type (live|archived).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="live-streams-GETapi-v1-live-streams--id-">
                                <a href="#live-streams-GETapi-v1-live-streams--id-">Get a specific live stream</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-actors" class="tocify-header">
                <li class="tocify-item level-1" data-unique="actors">
                    <a href="#actors">Actors</a>
                </li>
                                    <ul id="tocify-subheader-actors" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="actors-GETapi-v1-actors">
                                <a href="#actors-GETapi-v1-actors">List actors. Query: search, trending (1), per_page.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="actors-GETapi-v1-actors-trending">
                                <a href="#actors-GETapi-v1-actors-trending">GET api/v1/actors/trending</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="actors-GETapi-v1-actors--id-">
                                <a href="#actors-GETapi-v1-actors--id-">GET api/v1/actors/{id}</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-player-downloads" class="tocify-header">
                <li class="tocify-item level-1" data-unique="player-downloads">
                    <a href="#player-downloads">Player & Downloads</a>
                </li>
                                    <ul id="tocify-subheader-player-downloads" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="player-downloads-GETapi-v1-player--id-">
                                <a href="#player-downloads-GETapi-v1-player--id-">Get playback manifest for a movie or TV show episode</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="player-downloads-GETapi-v1-downloads--id-">
                                <a href="#player-downloads-GETapi-v1-downloads--id-">Download file. id = download source id. Auth optional for free content; required for paid. Supports ?access_token= for direct links.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="player-downloads-POSTapi-v1-watch-history">
                                <a href="#player-downloads-POSTapi-v1-watch-history">Update watch history for the current user</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="player-downloads-GETapi-v1-watch-history">
                                <a href="#player-downloads-GETapi-v1-watch-history">Get watch history for the current user</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-access-views" class="tocify-header">
                <li class="tocify-item level-1" data-unique="access-views">
                    <a href="#access-views">Access & Views</a>
                </li>
                                    <ul id="tocify-subheader-access-views" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="access-views-POSTapi-v1-access-check">
                                <a href="#access-views-POSTapi-v1-access-check">Check if user has access to a movie or TV show</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="access-views-POSTapi-v1-views-track">
                                <a href="#access-views-POSTapi-v1-views-track">Track a view. Body: media_id, media_type (MOVIE|TV_SHOW).</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-subscription-plans" class="tocify-header">
                <li class="tocify-item level-1" data-unique="subscription-plans">
                    <a href="#subscription-plans">Subscription plans</a>
                </li>
                                    <ul id="tocify-subheader-subscription-plans" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="subscription-plans-GETapi-v1-subscription-plans">
                                <a href="#subscription-plans-GETapi-v1-subscription-plans">Get all active subscription plans</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="subscription-plans-GETapi-v1-subscription-plans--id-">
                                <a href="#subscription-plans-GETapi-v1-subscription-plans--id-">Get a specific subscription plan</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-payments" class="tocify-header">
                <li class="tocify-item level-1" data-unique="payments">
                    <a href="#payments">Payments</a>
                </li>
                                    <ul id="tocify-subheader-payments" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="payments-GETapi-v1-payment-gateways">
                                <a href="#payments-GETapi-v1-payment-gateways">Get all active payment gateways</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments-initiate">
                                <a href="#payments-POSTapi-v1-payments-initiate">Initiate a payment (rent, buy, or subscription)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments-upload-proof">
                                <a href="#payments-POSTapi-v1-payments-upload-proof">Upload payment proof for manual payments</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments-verify">
                                <a href="#payments-POSTapi-v1-payments-verify">Verify payment (for automatic gateways)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-flutterwave-initiate">
                                <a href="#payments-POSTapi-v1-flutterwave-initiate">Initiate Flutterwave payment</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-flutterwave-verify">
                                <a href="#payments-POSTapi-v1-flutterwave-verify">Verify Flutterwave payment</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-iotec-initiate">
                                <a href="#payments-POSTapi-v1-iotec-initiate">Initiate ioTec Pay collection (phone prompt, in-site)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-GETapi-v1-iotec-status">
                                <a href="#payments-GETapi-v1-iotec-status">Poll ioTec Pay payment status</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-iotec-status">
                                <a href="#payments-POSTapi-v1-iotec-status">Poll ioTec Pay payment status</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-POSTapi-v1-payments-pawapay-deposit-initiate">
                                <a href="#payments-POSTapi-v1-payments-pawapay-deposit-initiate">Initiate PawaPay deposit</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="payments-GETapi-v1-payments-pawapay-deposit--depositId--status">
                                <a href="#payments-GETapi-v1-payments-pawapay-deposit--depositId--status">Check PawaPay deposit status</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-dashboard-watch-history" class="tocify-header">
                <li class="tocify-item level-1" data-unique="dashboard-watch-history">
                    <a href="#dashboard-watch-history">Dashboard & Watch history</a>
                </li>
                                    <ul id="tocify-subheader-dashboard-watch-history" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="dashboard-watch-history-GETapi-v1-dashboard">
                                <a href="#dashboard-watch-history-GETapi-v1-dashboard">Get current user dashboard</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-comments" class="tocify-header">
                <li class="tocify-item level-1" data-unique="comments">
                    <a href="#comments">Comments</a>
                </li>
                                    <ul id="tocify-subheader-comments" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="comments-GETapi-v1-comments--mediaId-">
                                <a href="#comments-GETapi-v1-comments--mediaId-">Get comments for a media item. Public. mediaId = movies.id.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="comments-POSTapi-v1-comments">
                                <a href="#comments-POSTapi-v1-comments">Store a new comment</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="comments-POSTapi-v1-comments--id--like">
                                <a href="#comments-POSTapi-v1-comments--id--like">Toggle like on a comment</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="comments-DELETEapi-v1-comments--id-">
                                <a href="#comments-DELETEapi-v1-comments--id-">Delete a comment</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-ad-banners" class="tocify-header">
                <li class="tocify-item level-1" data-unique="ad-banners">
                    <a href="#ad-banners">Ad banners</a>
                </li>
                                    <ul id="tocify-subheader-ad-banners" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="ad-banners-GETapi-v1-banners">
                                <a href="#ad-banners-GETapi-v1-banners">List active banners</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-endpoints" class="tocify-header">
                <li class="tocify-item level-1" data-unique="endpoints">
                    <a href="#endpoints">Endpoints</a>
                </li>
                                    <ul id="tocify-subheader-endpoints" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-video-fetch">
                                <a href="#endpoints-POSTapi-v1-video-fetch">Fetch a video from URL and store it on the CDN server.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-subtitle-fetch">
                                <a href="#endpoints-POSTapi-v1-subtitle-fetch">Fetch a subtitle file from a URL and save it to the server</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-push-devices" class="tocify-header">
                <li class="tocify-item level-1" data-unique="push-devices">
                    <a href="#push-devices">Push devices</a>
                </li>
                                    <ul id="tocify-subheader-push-devices" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="push-devices-POSTapi-v1-push-devices-register">
                                <a href="#push-devices-POSTapi-v1-push-devices-register">Register or update a device token</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="push-devices-POSTapi-v1-push-devices-unregister">
                                <a href="#push-devices-POSTapi-v1-push-devices-unregister">Unregister a device token</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: March 12, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>REST API for the NaraBox TV streaming portal. Powers the Next.js frontend with content (movies, TV shows, VJs), authentication, playback, subscriptions, rentals, and payments.</p>
<aside>
    <strong>Base URL</strong>: <code>http://127.0.0.1:8000</code>
</aside>
<pre><code>This documentation describes the **NaraBox TV Portal API v1**. Use it to build or integrate with the portal frontend (e.g. Next.js), understand workflows (homepage, watch page, dashboard, payments), and consume playback and access data.

**Base path:** All documented endpoints are under `/api/v1/`.

**Authentication:** Most listing and playback endpoints are public. Protected endpoints (dashboard, payments, watch history, profile) require a Bearer token from `POST /api/v1/auth/login` or `POST /api/v1/auth/register`.

&lt;aside&gt;As you scroll, you'll see code examples for working with the API. You can switch the language with the tabs. Use "Try It Out" to call endpoints from the docs (CORS permitting).&lt;/aside&gt;

**Workflow and domain docs** (in the repo): See the `docs/` folder for API_OVERVIEW.md, AUTHENTICATION.md, DOMAIN_MODEL.md, PLAYBACK_FLOW.md, FRONTEND_INTEGRATION_GUIDE.md, and related guides for AI designers and frontend developers.</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include an <strong><code>Authorization</code></strong> header with the value <strong><code>"Bearer {YOUR_AUTH_TOKEN}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>Obtain a token via <code>POST /api/v1/auth/login</code> or <code>POST /api/v1/auth/register</code>. Send it as <code>Authorization: Bearer {token}</code> on protected endpoints.</p>

        <h1 id="authentication">Authentication</h1>

    <p>Register, login, profile, OAuth, password reset, and email verification.</p>

                                <h2 id="authentication-POSTapi-v1-auth-register">Register a new user</h2>

<p>
</p>

<p>Returns user and Bearer token. Email is auto-verified by default.</p>

<span id="example-requests-POSTapi-v1-auth-register">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/register" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"name\": \"b\",
    \"email\": \"zbailey@example.net\",
    \"password\": \"-0pBNvYgxw\",
    \"phone\": \"a\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/register"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "name": "b",
    "email": "zbailey@example.net",
    "password": "-0pBNvYgxw",
    "phone": "a"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-register">
</span>
<span id="execution-results-POSTapi-v1-auth-register" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-register"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-register"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-register" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-register">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-register" data-method="POST"
      data-path="api/v1/auth/register"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-register', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-register"
                    onclick="tryItOut('POSTapi-v1-auth-register');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-register"
                    onclick="cancelTryOut('POSTapi-v1-auth-register');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-register"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/register</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-register"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-auth-register"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-register"
               value="zbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 255 characters. Example: <code>zbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-register"
               value="-0pBNvYgxw"
               data-component="body">
    <br>
<p>Must be at least 8 characters. Example: <code>-0pBNvYgxw</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-auth-register"
               value="a"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>a</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-login">Login user with email and password</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-login">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"password\": \"|]|{+-\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "email": "gbailey@example.net",
    "password": "|]|{+-"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-login">
</span>
<span id="execution-results-POSTapi-v1-auth-login" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-login"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-login"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-login" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-login">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-login" data-method="POST"
      data-path="api/v1/auth/login"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-login', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-login"
                    onclick="tryItOut('POSTapi-v1-auth-login');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-login"
                    onclick="cancelTryOut('POSTapi-v1-auth-login');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-login"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/login</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-login"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-login"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-login"
               value="|]|{+-"
               data-component="body">
    <br>
<p>Example: <code>|]|{+-</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-phone-request-otp">Request phone login OTP.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-phone-request-otp">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/phone/request-otp" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"phone\": \"b\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/phone/request-otp"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "phone": "b"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-phone-request-otp">
</span>
<span id="execution-results-POSTapi-v1-auth-phone-request-otp" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-phone-request-otp"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-phone-request-otp"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-phone-request-otp" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-phone-request-otp">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-phone-request-otp" data-method="POST"
      data-path="api/v1/auth/phone/request-otp"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-phone-request-otp', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-phone-request-otp"
                    onclick="tryItOut('POSTapi-v1-auth-phone-request-otp');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-phone-request-otp"
                    onclick="cancelTryOut('POSTapi-v1-auth-phone-request-otp');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-phone-request-otp"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/phone/request-otp</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-phone-request-otp"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-phone-request-otp"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-phone-request-otp"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-auth-phone-request-otp"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>b</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-phone-verify-otp">Verify phone OTP and login/register user.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-phone-verify-otp">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/phone/verify-otp" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"phone\": \"b\",
    \"code\": \"ngzmiy\",
    \"name\": \"v\",
    \"email\": \"jdach@example.org\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/phone/verify-otp"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "phone": "b",
    "code": "ngzmiy",
    "name": "v",
    "email": "jdach@example.org"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-phone-verify-otp">
</span>
<span id="execution-results-POSTapi-v1-auth-phone-verify-otp" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-phone-verify-otp"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-phone-verify-otp"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-phone-verify-otp" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-phone-verify-otp">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-phone-verify-otp" data-method="POST"
      data-path="api/v1/auth/phone/verify-otp"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-phone-verify-otp', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-phone-verify-otp"
                    onclick="tryItOut('POSTapi-v1-auth-phone-verify-otp');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-phone-verify-otp"
                    onclick="cancelTryOut('POSTapi-v1-auth-phone-verify-otp');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-phone-verify-otp"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/phone/verify-otp</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-phone-verify-otp"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-phone-verify-otp"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-phone-verify-otp"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-auth-phone-verify-otp"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="POSTapi-v1-auth-phone-verify-otp"
               value="ngzmiy"
               data-component="body">
    <br>
<p>Must be 6 characters. Example: <code>ngzmiy</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-auth-phone-verify-otp"
               value="v"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>v</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-phone-verify-otp"
               value="jdach@example.org"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 255 characters. Example: <code>jdach@example.org</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-google-mobile">Handle mobile Google login/register using an access token.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-google-mobile">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/google/mobile" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"access_token\": \"architecto\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/google/mobile"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "access_token": "architecto"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-google-mobile">
</span>
<span id="execution-results-POSTapi-v1-auth-google-mobile" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-google-mobile"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-google-mobile"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-google-mobile" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-google-mobile">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-google-mobile" data-method="POST"
      data-path="api/v1/auth/google/mobile"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-google-mobile', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-google-mobile"
                    onclick="tryItOut('POSTapi-v1-auth-google-mobile');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-google-mobile"
                    onclick="cancelTryOut('POSTapi-v1-auth-google-mobile');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-google-mobile"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/google/mobile</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-google-mobile"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-google-mobile"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-google-mobile"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>access_token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="access_token"                data-endpoint="POSTapi-v1-auth-google-mobile"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-apple-mobile">Handle Apple login/register (mobile).</h2>

<p>
</p>

<p>NOTE: This implementation assumes the mobile app has already obtained and
validated the Apple identity token. For production-hardening, wire this
to proper server-side Apple token validation.</p>

<span id="example-requests-POSTapi-v1-auth-apple-mobile">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/apple/mobile" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"apple_user_id\": \"architecto\",
    \"email\": \"zbailey@example.net\",
    \"name\": \"i\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/apple/mobile"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "apple_user_id": "architecto",
    "email": "zbailey@example.net",
    "name": "i"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-apple-mobile">
</span>
<span id="execution-results-POSTapi-v1-auth-apple-mobile" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-apple-mobile"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-apple-mobile"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-apple-mobile" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-apple-mobile">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-apple-mobile" data-method="POST"
      data-path="api/v1/auth/apple/mobile"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-apple-mobile', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-apple-mobile"
                    onclick="tryItOut('POSTapi-v1-auth-apple-mobile');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-apple-mobile"
                    onclick="cancelTryOut('POSTapi-v1-auth-apple-mobile');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-apple-mobile"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/apple/mobile</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-apple-mobile"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-apple-mobile"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-apple-mobile"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>apple_user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="apple_user_id"                data-endpoint="POSTapi-v1-auth-apple-mobile"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-apple-mobile"
               value="zbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 255 characters. Example: <code>zbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-auth-apple-mobile"
               value="i"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>i</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-verify-email">Verify email with code</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-verify-email">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/verify-email" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"code\": \"miyvdl\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/verify-email"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "email": "gbailey@example.net",
    "code": "miyvdl"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-verify-email">
</span>
<span id="execution-results-POSTapi-v1-auth-verify-email" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-verify-email"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-verify-email"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-verify-email" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-verify-email">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-verify-email" data-method="POST"
      data-path="api/v1/auth/verify-email"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-verify-email', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-verify-email"
                    onclick="tryItOut('POSTapi-v1-auth-verify-email');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-verify-email"
                    onclick="cancelTryOut('POSTapi-v1-auth-verify-email');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-verify-email"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/verify-email</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-verify-email"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-verify-email"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-verify-email"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-verify-email"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="POSTapi-v1-auth-verify-email"
               value="miyvdl"
               data-component="body">
    <br>
<p>Must be 6 characters. Example: <code>miyvdl</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-resend-verification">Resend verification code</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-resend-verification">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/resend-verification" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"email\": \"gbailey@example.net\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/resend-verification"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "email": "gbailey@example.net"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-resend-verification">
</span>
<span id="execution-results-POSTapi-v1-auth-resend-verification" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-resend-verification"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-resend-verification"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-resend-verification" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-resend-verification">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-resend-verification" data-method="POST"
      data-path="api/v1/auth/resend-verification"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-resend-verification', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-resend-verification"
                    onclick="tryItOut('POSTapi-v1-auth-resend-verification');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-resend-verification"
                    onclick="cancelTryOut('POSTapi-v1-auth-resend-verification');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-resend-verification"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/resend-verification</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-resend-verification"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-resend-verification"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-resend-verification"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-resend-verification"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>gbailey@example.net</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-forgot-password">Request password reset</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-forgot-password">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/forgot-password" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"email\": \"gbailey@example.net\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/forgot-password"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "email": "gbailey@example.net"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-forgot-password">
</span>
<span id="execution-results-POSTapi-v1-auth-forgot-password" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-forgot-password"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-forgot-password"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-forgot-password" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-forgot-password">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-forgot-password" data-method="POST"
      data-path="api/v1/auth/forgot-password"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-forgot-password', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-forgot-password"
                    onclick="tryItOut('POSTapi-v1-auth-forgot-password');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-forgot-password"
                    onclick="cancelTryOut('POSTapi-v1-auth-forgot-password');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-forgot-password"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/forgot-password</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-forgot-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-forgot-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-forgot-password"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-forgot-password"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>gbailey@example.net</code></p>
        </div>
        </form>

                    <h2 id="authentication-POSTapi-v1-auth-reset-password">Reset password with token</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-reset-password">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/reset-password" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"token\": \"architecto\",
    \"password\": \"]|{+-0pBNvYg\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/reset-password"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "email": "gbailey@example.net",
    "token": "architecto",
    "password": "]|{+-0pBNvYg"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-reset-password">
</span>
<span id="execution-results-POSTapi-v1-auth-reset-password" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-reset-password"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-reset-password"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-reset-password" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-reset-password">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-reset-password" data-method="POST"
      data-path="api/v1/auth/reset-password"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-reset-password', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-reset-password"
                    onclick="tryItOut('POSTapi-v1-auth-reset-password');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-reset-password"
                    onclick="cancelTryOut('POSTapi-v1-auth-reset-password');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-reset-password"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/reset-password</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-reset-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-reset-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-reset-password"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-reset-password"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-v1-auth-reset-password"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-reset-password"
               value="]|{+-0pBNvYg"
               data-component="body">
    <br>
<p>Must be at least 8 characters. Example: <code>]|{+-0pBNvYg</code></p>
        </div>
        </form>

                    <h2 id="authentication-GETapi-v1-auth-google-url">Get Google OAuth URL (for frontend web)</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth-google-url">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/auth/google/url" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/google/url"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-google-url">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;url&quot;: &quot;https://accounts.google.com/o/oauth2/auth?client_id=220478292100-d7u29dhjlmq5kr0e6sc48e5ji9kbncdi.apps.googleusercontent.com&amp;redirect_uri=http%3A%2F%2Flocalhost%3A8000%2Fapi%2Fv1%2Fauth%2Fgoogle%2Fcallback&amp;scope=openid+profile+email&amp;response_type=code&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-google-url" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-google-url"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-google-url"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-google-url" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-google-url">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-google-url" data-method="GET"
      data-path="api/v1/auth/google/url"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-google-url', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-google-url"
                    onclick="tryItOut('GETapi-v1-auth-google-url');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-google-url"
                    onclick="cancelTryOut('GETapi-v1-auth-google-url');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-google-url"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/google/url</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-google-url"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-google-url"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-auth-google-url"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="authentication-GETapi-v1-auth-google">Redirect to Google OAuth (web flow)</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth-google">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/auth/google" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/google"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-google">
            <blockquote>
            <p>Example response (302):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
location: https://accounts.google.com/o/oauth2/auth?client_id=220478292100-d7u29dhjlmq5kr0e6sc48e5ji9kbncdi.apps.googleusercontent.com&amp;redirect_uri=http%3A%2F%2Flocalhost%3A8000%2Fapi%2Fv1%2Fauth%2Fgoogle%2Fcallback&amp;scope=openid+profile+email&amp;response_type=code
content-type: text/html; charset=utf-8
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">&lt;!DOCTYPE html&gt;
&lt;html&gt;
    &lt;head&gt;
        &lt;meta charset=&quot;UTF-8&quot; /&gt;
        &lt;meta http-equiv=&quot;refresh&quot; content=&quot;0;url=&#039;https://accounts.google.com/o/oauth2/auth?client_id=220478292100-d7u29dhjlmq5kr0e6sc48e5ji9kbncdi.apps.googleusercontent.com&amp;amp;redirect_uri=http%3A%2F%2Flocalhost%3A8000%2Fapi%2Fv1%2Fauth%2Fgoogle%2Fcallback&amp;amp;scope=openid+profile+email&amp;amp;response_type=code&#039;&quot; /&gt;

        &lt;title&gt;Redirecting to https://accounts.google.com/o/oauth2/auth?client_id=220478292100-d7u29dhjlmq5kr0e6sc48e5ji9kbncdi.apps.googleusercontent.com&amp;amp;redirect_uri=http%3A%2F%2Flocalhost%3A8000%2Fapi%2Fv1%2Fauth%2Fgoogle%2Fcallback&amp;amp;scope=openid+profile+email&amp;amp;response_type=code&lt;/title&gt;
    &lt;/head&gt;
    &lt;body&gt;
        Redirecting to &lt;a href=&quot;https://accounts.google.com/o/oauth2/auth?client_id=220478292100-d7u29dhjlmq5kr0e6sc48e5ji9kbncdi.apps.googleusercontent.com&amp;amp;redirect_uri=http%3A%2F%2Flocalhost%3A8000%2Fapi%2Fv1%2Fauth%2Fgoogle%2Fcallback&amp;amp;scope=openid+profile+email&amp;amp;response_type=code&quot;&gt;https://accounts.google.com/o/oauth2/auth?client_id=220478292100-d7u29dhjlmq5kr0e6sc48e5ji9kbncdi.apps.googleusercontent.com&amp;amp;redirect_uri=http%3A%2F%2Flocalhost%3A8000%2Fapi%2Fv1%2Fauth%2Fgoogle%2Fcallback&amp;amp;scope=openid+profile+email&amp;amp;response_type=code&lt;/a&gt;.
    &lt;/body&gt;
&lt;/html&gt;</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-google" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-google"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-google"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-google" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-google">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-google" data-method="GET"
      data-path="api/v1/auth/google"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-google', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-google"
                    onclick="tryItOut('GETapi-v1-auth-google');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-google"
                    onclick="cancelTryOut('GETapi-v1-auth-google');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-google"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/google</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-google"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-google"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-auth-google"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="authentication-GETapi-v1-auth-google-callback">Handle Google OAuth callback (web flow)</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth-google-callback">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/auth/google/callback" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/google/callback"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-google-callback">
            <blockquote>
            <p>Example response (302):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
location: http://localhost:3000/auth/callback?error=Client+error%3A+%60POST+https%3A%2F%2Fwww.googleapis.com%2Foauth2%2Fv4%2Ftoken%60+resulted+in+a+%60400+Bad+Request%60+response%3A%0A%7B%0A++%22error%22%3A+%22invalid_request%22%2C%0A++%22error_description%22%3A+%22Missing+required+parameter%3A+code%22%0A%7D%0A
content-type: text/html; charset=utf-8
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">&lt;!DOCTYPE html&gt;
&lt;html&gt;
    &lt;head&gt;
        &lt;meta charset=&quot;UTF-8&quot; /&gt;
        &lt;meta http-equiv=&quot;refresh&quot; content=&quot;0;url=&#039;http://localhost:3000/auth/callback?error=Client+error%3A+%60POST+https%3A%2F%2Fwww.googleapis.com%2Foauth2%2Fv4%2Ftoken%60+resulted+in+a+%60400+Bad+Request%60+response%3A%0A%7B%0A++%22error%22%3A+%22invalid_request%22%2C%0A++%22error_description%22%3A+%22Missing+required+parameter%3A+code%22%0A%7D%0A&#039;&quot; /&gt;

        &lt;title&gt;Redirecting to http://localhost:3000/auth/callback?error=Client+error%3A+%60POST+https%3A%2F%2Fwww.googleapis.com%2Foauth2%2Fv4%2Ftoken%60+resulted+in+a+%60400+Bad+Request%60+response%3A%0A%7B%0A++%22error%22%3A+%22invalid_request%22%2C%0A++%22error_description%22%3A+%22Missing+required+parameter%3A+code%22%0A%7D%0A&lt;/title&gt;
    &lt;/head&gt;
    &lt;body&gt;
        Redirecting to &lt;a href=&quot;http://localhost:3000/auth/callback?error=Client+error%3A+%60POST+https%3A%2F%2Fwww.googleapis.com%2Foauth2%2Fv4%2Ftoken%60+resulted+in+a+%60400+Bad+Request%60+response%3A%0A%7B%0A++%22error%22%3A+%22invalid_request%22%2C%0A++%22error_description%22%3A+%22Missing+required+parameter%3A+code%22%0A%7D%0A&quot;&gt;http://localhost:3000/auth/callback?error=Client+error%3A+%60POST+https%3A%2F%2Fwww.googleapis.com%2Foauth2%2Fv4%2Ftoken%60+resulted+in+a+%60400+Bad+Request%60+response%3A%0A%7B%0A++%22error%22%3A+%22invalid_request%22%2C%0A++%22error_description%22%3A+%22Missing+required+parameter%3A+code%22%0A%7D%0A&lt;/a&gt;.
    &lt;/body&gt;
&lt;/html&gt;</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-google-callback" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-google-callback"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-google-callback"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-google-callback" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-google-callback">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-google-callback" data-method="GET"
      data-path="api/v1/auth/google/callback"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-google-callback', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-google-callback"
                    onclick="tryItOut('GETapi-v1-auth-google-callback');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-google-callback"
                    onclick="cancelTryOut('GETapi-v1-auth-google-callback');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-google-callback"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/google/callback</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-google-callback"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-google-callback"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-auth-google-callback"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="authentication-GETapi-v1-auth-me">Get current authenticated user</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth-me">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/auth/me" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/me"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-me">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-me" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-me"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-me" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-me">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-me" data-method="GET"
      data-path="api/v1/auth/me"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-me', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-me"
                    onclick="tryItOut('GETapi-v1-auth-me');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-me"
                    onclick="cancelTryOut('GETapi-v1-auth-me');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-me"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-auth-me"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="authentication-PUTapi-v1-auth-profile">Update user profile</h2>

<p>
</p>



<span id="example-requests-PUTapi-v1-auth-profile">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://127.0.0.1:8000/api/v1/auth/profile" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"name\": \"b\",
    \"phone\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/profile"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "name": "b",
    "phone": "n"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-auth-profile">
</span>
<span id="execution-results-PUTapi-v1-auth-profile" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-auth-profile"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-auth-profile"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-auth-profile" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-auth-profile">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-auth-profile" data-method="PUT"
      data-path="api/v1/auth/profile"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-auth-profile', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-auth-profile"
                    onclick="tryItOut('PUTapi-v1-auth-profile');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-auth-profile"
                    onclick="cancelTryOut('PUTapi-v1-auth-profile');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-auth-profile"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/auth/profile</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-auth-profile"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-auth-profile"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="PUTapi-v1-auth-profile"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-v1-auth-profile"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="PUTapi-v1-auth-profile"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="PUTapi-v1-auth-profile"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>n</code></p>
        </div>
        </form>

                    <h2 id="authentication-DELETEapi-v1-auth-account">Delete user account</h2>

<p>
</p>



<span id="example-requests-DELETEapi-v1-auth-account">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://127.0.0.1:8000/api/v1/auth/account" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/account"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-auth-account">
</span>
<span id="execution-results-DELETEapi-v1-auth-account" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-auth-account"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-auth-account"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-auth-account" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-auth-account">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-auth-account" data-method="DELETE"
      data-path="api/v1/auth/account"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-auth-account', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-auth-account"
                    onclick="tryItOut('DELETEapi-v1-auth-account');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-auth-account"
                    onclick="cancelTryOut('DELETEapi-v1-auth-account');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-auth-account"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/auth/account</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-auth-account"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-auth-account"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="DELETEapi-v1-auth-account"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="authentication-POSTapi-v1-auth-logout">Logout user</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-logout">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/auth/logout" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/auth/logout"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-logout">
</span>
<span id="execution-results-POSTapi-v1-auth-logout" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-logout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-logout"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-logout" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-logout">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-logout" data-method="POST"
      data-path="api/v1/auth/logout"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-logout', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-logout"
                    onclick="tryItOut('POSTapi-v1-auth-logout');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-logout"
                    onclick="cancelTryOut('POSTapi-v1-auth-logout');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-logout"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/logout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-auth-logout"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                <h1 id="hero">Hero</h1>

    <p>Homepage carousel slides (each slide is a movie).</p>

                                <h2 id="hero-GETapi-v1-hero">Get hero slides for homepage</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-hero">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/hero" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/hero"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-hero">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;title&quot;: &quot;Zootopia 2&quot;,
            &quot;description&quot;: &quot;After cracking the biggest case in Zootopia&#039;s history, rookie cops Judy Hopps and Nick Wilde find themselves on the twisting trail of a great mystery when Gary De&#039;Snake arrives and turns the animal metropolis upside down. To crack the case, Judy and Nick must go undercover to unexpected new parts of town, where their growing partnership is tested like never before.&quot;,
            &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_f158459be819affd8c9f257f75a49f15.jpg&quot;,
            &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_7ee2bf6a30418364a91d7d4cffb592d0.jpg&quot;,
            &quot;rating&quot;: 7.7,
            &quot;releaseDate&quot;: &quot;2025-11-26&quot;,
            &quot;category&quot;: &quot;VJ Translated&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Mark&quot;,
            &quot;genre&quot;: [
                &quot;Adventure&quot;,
                &quot;Comedy&quot;,
                &quot;Mystery&quot;,
                &quot;Animation&quot;,
                &quot;Family&quot;
            ],
            &quot;trendingScore&quot;: 99,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;priceRent&quot;: 1000,
            &quot;priceBuy&quot;: 2200
        },
        {
            &quot;id&quot;: 2,
            &quot;title&quot;: &quot;Kampala Nights&quot;,
            &quot;description&quot;: &quot;High stakes, high speed, and local dialect.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.5,
            &quot;releaseDate&quot;: &quot;2024-05-15&quot;,
            &quot;category&quot;: &quot;VJ Translated&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Crime&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;priceRent&quot;: 3500,
            &quot;priceBuy&quot;: null
        },
        {
            &quot;id&quot;: 25,
            &quot;title&quot;: &quot;The Running Man&quot;,
            &quot;description&quot;: &quot;Desperate to save his sick daughter, working-class Ben Richards is convinced by The Running Man&#039;s charming but ruthless producer to enter the deadly competition game as a last resort. But Ben&#039;s defiance, instincts, and grit turn him into an unexpected fan favorite &mdash; and a threat to the entire system. As ratings skyrocket, so does the danger, and Ben must outwit not just the Hunters, but a nation addicted to watching him fall.&quot;,
            &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_d8e18d261af44a7ce48438f34445aa85.jpg&quot;,
            &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_c68e15b0831a3f7ee6027e13ae9acc08.jpg&quot;,
            &quot;rating&quot;: 6.8,
            &quot;releaseDate&quot;: &quot;2025-11-11&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;,
                &quot;Science Fiction&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-hero" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-hero"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-hero"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-hero" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-hero">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-hero" data-method="GET"
      data-path="api/v1/hero"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-hero', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-hero"
                    onclick="tryItOut('GETapi-v1-hero');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-hero"
                    onclick="cancelTryOut('GETapi-v1-hero');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-hero"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/hero</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-hero"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-hero"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-hero"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                <h1 id="movies">Movies</h1>

    <p>List and fetch movies. Supports filters: category, genre, vj, has_vj, filter (free|rent|purchase|premium|featured), sort (trending|latest|rating).</p>

                                <h2 id="movies-GETapi-v1-movies">List movies</h2>

<p>
</p>

<p>Query params: category, genre, vj, has_vj, filter, sort, order, per_page.</p>

<span id="example-requests-GETapi-v1-movies">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/movies" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/movies"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-movies">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;slug&quot;: &quot;zootopia-2&quot;,
            &quot;title&quot;: &quot;Zootopia 2&quot;,
            &quot;description&quot;: &quot;After cracking the biggest case in Zootopia&#039;s history, rookie cops Judy Hopps and Nick Wilde find themselves on the twisting trail of a great mystery when Gary De&#039;Snake arrives and turns the animal metropolis upside down. To crack the case, Judy and Nick must go undercover to unexpected new parts of town, where their growing partnership is tested like never before.&quot;,
            &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_f158459be819affd8c9f257f75a49f15.jpg&quot;,
            &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_7ee2bf6a30418364a91d7d4cffb592d0.jpg&quot;,
            &quot;rating&quot;: 7.7,
            &quot;releaseDate&quot;: &quot;2025-11-26&quot;,
            &quot;category&quot;: &quot;VJ Translated&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Mark&quot;,
            &quot;genre&quot;: [
                &quot;Adventure&quot;,
                &quot;Comedy&quot;,
                &quot;Mystery&quot;,
                &quot;Animation&quot;,
                &quot;Family&quot;
            ],
            &quot;trendingScore&quot;: 99,
            &quot;viewsCount&quot;: 46,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: true,
            &quot;createdAt&quot;: &quot;2025-12-27T19:16:26+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;1h 47m&quot;,
            &quot;priceRent&quot;: 1000,
            &quot;priceBuy&quot;: 2200,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: 1084242,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 46,
                    &quot;name&quot;: &quot;Ginnifer Goodwin&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_1965015e8a797cb0b19783fff65c70a6.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 47,
                    &quot;name&quot;: &quot;Jason Bateman&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_24af5af5c2a714e8d7e094ce98542331.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 48,
                    &quot;name&quot;: &quot;Ke Huy Quan&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2f557bbf6c12dd58d52cfcde5dbfcb95.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 49,
                    &quot;name&quot;: &quot;Fortune Feimster&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_da74cba983cb465cf09166f6a5ebebcb.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 50,
                    &quot;name&quot;: &quot;Andy Samberg&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_32e26a37052974f33269e6d8b824f8c9.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 51,
                    &quot;name&quot;: &quot;David Strathairn&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_24013c23a6b4c7d4198cfe4d8c871949.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 52,
                    &quot;name&quot;: &quot;Idris Elba&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_d9833e33d178b42c8d7bcce591083b9c.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 53,
                    &quot;name&quot;: &quot;Shakira&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_61342d35794a35095e3945ca22f6caab.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 54,
                    &quot;name&quot;: &quot;Patrick Warburton&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_96c351e316f4b20be6dd7d86ec53ada2.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 55,
                    &quot;name&quot;: &quot;Quinta Brunson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_5b9a14b1d80becc77bac57a929816a3d.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 56,
                    &quot;name&quot;: &quot;Danny Trejo&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_039fc588aa288508bf2671ba46d99810.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 57,
                    &quot;name&quot;: &quot;Nate Torrence&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3190b4c9644a189033c264e8437c6dfd.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 58,
                    &quot;name&quot;: &quot;Bonnie Hunt&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4fd0d847ec18458bc4e64374da0ac181.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 59,
                    &quot;name&quot;: &quot;Don Lake&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_a3efdd188f65551ecd1d1104bd0a4b10.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 60,
                    &quot;name&quot;: &quot;Michelle Gomez&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_ff92945a6f529045e2b357433bd22806.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 61,
                    &quot;name&quot;: &quot;David Fane&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_9afe598a68ee8b88489585722218413d.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 62,
                    &quot;name&quot;: &quot;Joe Anoa&#039;i&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2df01f849d6965c36cfbbf025f657927.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 63,
                    &quot;name&quot;: &quot;Phil Brooks&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_b2e5290073ddd70a68292aff9758d0c8.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 64,
                    &quot;name&quot;: &quot;Stephanie Beatriz&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_ddbac73d87a0884c788a3fbd9acad039.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 65,
                    &quot;name&quot;: &quot;Wilmer Valderrama&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_af6ba169784b0f671bfe13d8dc50cdca.jpg&quot;,
                    &quot;role&quot;: null
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 25,
            &quot;slug&quot;: &quot;the-running-man&quot;,
            &quot;title&quot;: &quot;The Running Man&quot;,
            &quot;description&quot;: &quot;Desperate to save his sick daughter, working-class Ben Richards is convinced by The Running Man&#039;s charming but ruthless producer to enter the deadly competition game as a last resort. But Ben&#039;s defiance, instincts, and grit turn him into an unexpected fan favorite &mdash; and a threat to the entire system. As ratings skyrocket, so does the danger, and Ben must outwit not just the Hunters, but a nation addicted to watching him fall.&quot;,
            &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_d8e18d261af44a7ce48438f34445aa85.jpg&quot;,
            &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_c68e15b0831a3f7ee6027e13ae9acc08.jpg&quot;,
            &quot;rating&quot;: 6.8,
            &quot;releaseDate&quot;: &quot;2025-11-11&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;,
                &quot;Science Fiction&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 38,
            &quot;is_free&quot;: true,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-28T09:13:20+00:00&quot;,
            &quot;videoUrl&quot;: null,
            &quot;duration&quot;: &quot;2h 13m&quot;,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: 798645,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Final Trailer&quot;,
                    &quot;key&quot;: &quot;_iHJHYjq7XI&quot;,
                    &quot;youtubeUrl&quot;: &quot;https://www.youtube.com/watch?v=_iHJHYjq7XI&quot;,
                    &quot;embedUrl&quot;: &quot;https://www.youtube.com/embed/_iHJHYjq7XI&quot;,
                    &quot;type&quot;: &quot;Trailer&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;New Trailer&quot;,
                    &quot;key&quot;: &quot;UsmiRIozzvw&quot;,
                    &quot;youtubeUrl&quot;: &quot;https://www.youtube.com/watch?v=UsmiRIozzvw&quot;,
                    &quot;embedUrl&quot;: &quot;https://www.youtube.com/embed/UsmiRIozzvw&quot;,
                    &quot;type&quot;: &quot;Trailer&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Official Trailer&quot;,
                    &quot;key&quot;: &quot;KD18ddeFuyM&quot;,
                    &quot;youtubeUrl&quot;: &quot;https://www.youtube.com/watch?v=KD18ddeFuyM&quot;,
                    &quot;embedUrl&quot;: &quot;https://www.youtube.com/embed/KD18ddeFuyM&quot;,
                    &quot;type&quot;: &quot;Trailer&quot;
                }
            ],
            &quot;crew&quot;: {
                &quot;directors&quot;: [
                    {
                        &quot;name&quot;: &quot;Edgar Wright&quot;,
                        &quot;profileImage&quot;: &quot;https://image.tmdb.org/t/p/w500/3BqgbeAkNnDcIVtrDvG6LJnEkZK.jpg&quot;
                    }
                ]
            },
            &quot;keywords&quot;: [
                &quot;based on novel or book&quot;,
                &quot;dark comedy&quot;,
                &quot;survival&quot;,
                &quot;on the run&quot;,
                &quot;television network&quot;,
                &quot;near future&quot;,
                &quot;malicious&quot;,
                &quot;depressing&quot;,
                &quot;mean spirited&quot;,
                &quot;dystopian future&quot;,
                &quot;dystopian sci-fi&quot;
            ],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 6,
                    &quot;name&quot;: &quot;Glen Powell&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_dc0231283b20ba9137b89911aa3a86b1.jpg&quot;,
                    &quot;role&quot;: &quot;Ben Richards&quot;
                },
                {
                    &quot;id&quot;: 7,
                    &quot;name&quot;: &quot;Josh Brolin&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_d56240ed42b0004e0580f363b5ba76a6.jpg&quot;,
                    &quot;role&quot;: &quot;Dan Killian&quot;
                },
                {
                    &quot;id&quot;: 8,
                    &quot;name&quot;: &quot;Colman Domingo&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_28483513c305c919bf1bcf698f90d313.jpg&quot;,
                    &quot;role&quot;: &quot;Bobby Thompson&quot;
                },
                {
                    &quot;id&quot;: 9,
                    &quot;name&quot;: &quot;Lee Pace&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_1f4b4e2c85c7872f783cdbdacf6350d2.jpg&quot;,
                    &quot;role&quot;: &quot;Evan McCone&quot;
                },
                {
                    &quot;id&quot;: 10,
                    &quot;name&quot;: &quot;Michael Cera&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_8a587cec8890c44293a70b16083b15ef.jpg&quot;,
                    &quot;role&quot;: &quot;Elton Parrakis&quot;
                },
                {
                    &quot;id&quot;: 11,
                    &quot;name&quot;: &quot;Emilia Jones&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_c7e03d651bba0d2c7d025c405bc10640.jpg&quot;,
                    &quot;role&quot;: &quot;Amelia Williams&quot;
                },
                {
                    &quot;id&quot;: 12,
                    &quot;name&quot;: &quot;William H. Macy&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_6d48da47b28613629f7a1008c3c811ed.jpg&quot;,
                    &quot;role&quot;: &quot;Molie Jernigan&quot;
                },
                {
                    &quot;id&quot;: 13,
                    &quot;name&quot;: &quot;Daniel Ezra&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_ec8c796dcdc6e6d5b205c54936b2956e.jpg&quot;,
                    &quot;role&quot;: &quot;Bradley Throckmorton&quot;
                },
                {
                    &quot;id&quot;: 14,
                    &quot;name&quot;: &quot;Jayme Lawson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_fb17da8e384ce433e30fe122c31c3f89.jpg&quot;,
                    &quot;role&quot;: &quot;Sheila Richards&quot;
                },
                {
                    &quot;id&quot;: 15,
                    &quot;name&quot;: &quot;Katy O&#039;Brian&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_92a1556af20f36c00853107999105e52.jpg&quot;,
                    &quot;role&quot;: &quot;Jenni Laughlin&quot;
                },
                {
                    &quot;id&quot;: 16,
                    &quot;name&quot;: &quot;Martin Herlihy&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_850f0c13386df1f7e4859e84326cfb17.jpg&quot;,
                    &quot;role&quot;: &quot;Tim Jansky&quot;
                },
                {
                    &quot;id&quot;: 17,
                    &quot;name&quot;: &quot;David Zayas&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_0d3e912105b1f893de1cef20d1f58af5.jpg&quot;,
                    &quot;role&quot;: &quot;Richard Manuel&quot;
                },
                {
                    &quot;id&quot;: 18,
                    &quot;name&quot;: &quot;Sean Hayes&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_c5090bd4ceccc4d741f4db6e72877707.jpg&quot;,
                    &quot;role&quot;: &quot;Gary Greenbacks&quot;
                },
                {
                    &quot;id&quot;: 19,
                    &quot;name&quot;: &quot;Karl Glusman&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4e17b630b07940d94ded092926eab643.jpg&quot;,
                    &quot;role&quot;: &quot;Frank&quot;
                },
                {
                    &quot;id&quot;: 20,
                    &quot;name&quot;: &quot;Sandra Dickinson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_19789e63233d2adbc5f006e75fb65535.jpg&quot;,
                    &quot;role&quot;: &quot;Victoria Parrakis&quot;
                },
                {
                    &quot;id&quot;: 21,
                    &quot;name&quot;: &quot;Shelley Conn&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_8808ef890dfb4b538bc541e5f23a7a78.jpg&quot;,
                    &quot;role&quot;: &quot;Dr. Raznor&quot;
                },
                {
                    &quot;id&quot;: 22,
                    &quot;name&quot;: &quot;James Austin Johnson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_9ddd59c382c8195b383ca3a5ae6183f7.jpg&quot;,
                    &quot;role&quot;: &quot;Announcer (Voice)&quot;
                },
                {
                    &quot;id&quot;: 23,
                    &quot;name&quot;: &quot;Debi Mazar&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_02ac9f108c38ae027389ea0c457d482d.jpg&quot;,
                    &quot;role&quot;: &quot;Amor&eacute; Americano&quot;
                },
                {
                    &quot;id&quot;: 24,
                    &quot;name&quot;: &quot;Emma Sidi&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_27f39a2c0dc0189e00c9154d44a3755b.jpg&quot;,
                    &quot;role&quot;: &quot;Adrian&eacute; Americano&quot;
                },
                {
                    &quot;id&quot;: 25,
                    &quot;name&quot;: &quot;Catherine Cohen&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_c50202415708b0985fcae7706a11bea7.jpg&quot;,
                    &quot;role&quot;: &quot;Arian&eacute; Americano&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 8,
            &quot;slug&quot;: &quot;ancient-echoes&quot;,
            &quot;title&quot;: &quot;Ancient Echoes&quot;,
            &quot;description&quot;: &quot;When a team of archaeologists discovers a lost civilization buried beneath the desert sands.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.9,
            &quot;releaseDate&quot;: &quot;2024-09-01&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Junior&quot;,
            &quot;genre&quot;: [
                &quot;Adventure&quot;,
                &quot;Mystery&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 6,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;2h 32m&quot;,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 2,
            &quot;slug&quot;: &quot;kampala-nights&quot;,
            &quot;title&quot;: &quot;Kampala Nights&quot;,
            &quot;description&quot;: &quot;High stakes, high speed, and local dialect.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.5,
            &quot;releaseDate&quot;: &quot;2024-05-15&quot;,
            &quot;category&quot;: &quot;VJ Translated&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Crime&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 2,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: 3500,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 11,
            &quot;slug&quot;: &quot;desert-storm&quot;,
            &quot;title&quot;: &quot;Desert Storm&quot;,
            &quot;description&quot;: &quot;War epic.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1509062522246-3755977927d7?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1509062522246-3755977927d7?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 9.3,
            &quot;releaseDate&quot;: &quot;2024-06-22&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;History&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 2,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: 15000,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 3,
            &quot;slug&quot;: &quot;cyber-enigma&quot;,
            &quot;title&quot;: &quot;Cyber Enigma&quot;,
            &quot;description&quot;: &quot;In a world where digital consciousness has become reality, a rogue AI threatens to merge human minds with the virtual realm.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.9,
            &quot;releaseDate&quot;: &quot;2024-08-20&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Sci-Fi&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;2h 15m&quot;,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 4,
            &quot;slug&quot;: &quot;lost-in-the-rift&quot;,
            &quot;title&quot;: &quot;Lost in the Rift&quot;,
            &quot;description&quot;: &quot;Space exploration.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 9.1,
            &quot;releaseDate&quot;: &quot;2023-12-10&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Adventure&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 5,
            &quot;slug&quot;: &quot;the-iron-fist&quot;,
            &quot;title&quot;: &quot;The Iron Fist&quot;,
            &quot;description&quot;: &quot;Martial arts drama.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1552072092-7f9b8d63efcb?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1552072092-7f9b8d63efcb?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.2,
            &quot;releaseDate&quot;: &quot;2024-01-05&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 6,
            &quot;slug&quot;: &quot;shadow-walker&quot;,
            &quot;title&quot;: &quot;Shadow Walker&quot;,
            &quot;description&quot;: &quot;A master ninja emerges from the shadows to protect a secret that could change the world.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.7,
            &quot;releaseDate&quot;: &quot;2024-02-14&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Mark&quot;,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;1h 58m&quot;,
            &quot;priceRent&quot;: 4000,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 7,
            &quot;slug&quot;: &quot;neon-pulse&quot;,
            &quot;title&quot;: &quot;Neon Pulse&quot;,
            &quot;description&quot;: &quot;Cyberpunk music.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.5,
            &quot;releaseDate&quot;: &quot;2023-11-20&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Music&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 9,
            &quot;slug&quot;: &quot;deep-frost&quot;,
            &quot;title&quot;: &quot;Deep Frost&quot;,
            &quot;description&quot;: &quot;Arctic survival.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1517783999520-f068d7431a60?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1517783999520-f068d7431a60?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.4,
            &quot;releaseDate&quot;: &quot;2024-03-30&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: 4500,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 10,
            &quot;slug&quot;: &quot;midnight-rain&quot;,
            &quot;title&quot;: &quot;Midnight Rain&quot;,
            &quot;description&quot;: &quot;Film noir.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1485846234645-a62644f84728?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1485846234645-a62644f84728?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.8,
            &quot;releaseDate&quot;: &quot;2023-10-15&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Emmy&quot;,
            &quot;genre&quot;: [
                &quot;Crime&quot;,
                &quot;Drama&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 12,
            &quot;slug&quot;: &quot;the-last-stand&quot;,
            &quot;title&quot;: &quot;The Last Stand&quot;,
            &quot;description&quot;: &quot;Western showdown.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.1,
            &quot;releaseDate&quot;: &quot;2024-07-04&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Western&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 13,
            &quot;slug&quot;: &quot;quantum-drift&quot;,
            &quot;title&quot;: &quot;Quantum Drift&quot;,
            &quot;description&quot;: &quot;Time travel racing.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.6,
            &quot;releaseDate&quot;: &quot;2024-08-11&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Racing&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: 5500,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 14,
            &quot;slug&quot;: &quot;siren-call&quot;,
            &quot;title&quot;: &quot;Siren Call&quot;,
            &quot;description&quot;: &quot;Ocean mystery.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.2,
            &quot;releaseDate&quot;: &quot;2024-10-31&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Horror&quot;,
                &quot;Mystery&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        }
    ],
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 20,
        &quot;total&quot;: 15
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-movies" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-movies"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-movies"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-movies" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-movies">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-movies" data-method="GET"
      data-path="api/v1/movies"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-movies', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-movies"
                    onclick="tryItOut('GETapi-v1-movies');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-movies"
                    onclick="cancelTryOut('GETapi-v1-movies');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-movies"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/movies</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-movies"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-movies"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-movies"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="movies-GETapi-v1-movies-selected-today">Return up to 14 movies selected once per day.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-movies-selected-today">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/movies/selected-today" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/movies/selected-today"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-movies-selected-today">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 13,
            &quot;slug&quot;: &quot;quantum-drift&quot;,
            &quot;title&quot;: &quot;Quantum Drift&quot;,
            &quot;description&quot;: &quot;Time travel racing.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.6,
            &quot;releaseDate&quot;: &quot;2024-08-11&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Racing&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: 5500,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 11,
            &quot;slug&quot;: &quot;desert-storm&quot;,
            &quot;title&quot;: &quot;Desert Storm&quot;,
            &quot;description&quot;: &quot;War epic.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1509062522246-3755977927d7?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1509062522246-3755977927d7?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 9.3,
            &quot;releaseDate&quot;: &quot;2024-06-22&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;History&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 2,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: 15000,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 4,
            &quot;slug&quot;: &quot;lost-in-the-rift&quot;,
            &quot;title&quot;: &quot;Lost in the Rift&quot;,
            &quot;description&quot;: &quot;Space exploration.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 9.1,
            &quot;releaseDate&quot;: &quot;2023-12-10&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Adventure&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 6,
            &quot;slug&quot;: &quot;shadow-walker&quot;,
            &quot;title&quot;: &quot;Shadow Walker&quot;,
            &quot;description&quot;: &quot;A master ninja emerges from the shadows to protect a secret that could change the world.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.7,
            &quot;releaseDate&quot;: &quot;2024-02-14&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Mark&quot;,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;1h 58m&quot;,
            &quot;priceRent&quot;: 4000,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 14,
            &quot;slug&quot;: &quot;siren-call&quot;,
            &quot;title&quot;: &quot;Siren Call&quot;,
            &quot;description&quot;: &quot;Ocean mystery.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.2,
            &quot;releaseDate&quot;: &quot;2024-10-31&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Horror&quot;,
                &quot;Mystery&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 7,
            &quot;slug&quot;: &quot;neon-pulse&quot;,
            &quot;title&quot;: &quot;Neon Pulse&quot;,
            &quot;description&quot;: &quot;Cyberpunk music.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.5,
            &quot;releaseDate&quot;: &quot;2023-11-20&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Music&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 10,
            &quot;slug&quot;: &quot;midnight-rain&quot;,
            &quot;title&quot;: &quot;Midnight Rain&quot;,
            &quot;description&quot;: &quot;Film noir.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1485846234645-a62644f84728?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1485846234645-a62644f84728?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.8,
            &quot;releaseDate&quot;: &quot;2023-10-15&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Emmy&quot;,
            &quot;genre&quot;: [
                &quot;Crime&quot;,
                &quot;Drama&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 2,
            &quot;slug&quot;: &quot;kampala-nights&quot;,
            &quot;title&quot;: &quot;Kampala Nights&quot;,
            &quot;description&quot;: &quot;High stakes, high speed, and local dialect.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.5,
            &quot;releaseDate&quot;: &quot;2024-05-15&quot;,
            &quot;category&quot;: &quot;VJ Translated&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Crime&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 2,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: 3500,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 9,
            &quot;slug&quot;: &quot;deep-frost&quot;,
            &quot;title&quot;: &quot;Deep Frost&quot;,
            &quot;description&quot;: &quot;Arctic survival.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1517783999520-f068d7431a60?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1517783999520-f068d7431a60?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.4,
            &quot;releaseDate&quot;: &quot;2024-03-30&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: 4500,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 8,
            &quot;slug&quot;: &quot;ancient-echoes&quot;,
            &quot;title&quot;: &quot;Ancient Echoes&quot;,
            &quot;description&quot;: &quot;When a team of archaeologists discovers a lost civilization buried beneath the desert sands.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.9,
            &quot;releaseDate&quot;: &quot;2024-09-01&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Junior&quot;,
            &quot;genre&quot;: [
                &quot;Adventure&quot;,
                &quot;Mystery&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 6,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;2h 32m&quot;,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 5,
            &quot;slug&quot;: &quot;the-iron-fist&quot;,
            &quot;title&quot;: &quot;The Iron Fist&quot;,
            &quot;description&quot;: &quot;Martial arts drama.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1552072092-7f9b8d63efcb?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1552072092-7f9b8d63efcb?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.2,
            &quot;releaseDate&quot;: &quot;2024-01-05&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 25,
            &quot;slug&quot;: &quot;the-running-man&quot;,
            &quot;title&quot;: &quot;The Running Man&quot;,
            &quot;description&quot;: &quot;Desperate to save his sick daughter, working-class Ben Richards is convinced by The Running Man&#039;s charming but ruthless producer to enter the deadly competition game as a last resort. But Ben&#039;s defiance, instincts, and grit turn him into an unexpected fan favorite &mdash; and a threat to the entire system. As ratings skyrocket, so does the danger, and Ben must outwit not just the Hunters, but a nation addicted to watching him fall.&quot;,
            &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_d8e18d261af44a7ce48438f34445aa85.jpg&quot;,
            &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_c68e15b0831a3f7ee6027e13ae9acc08.jpg&quot;,
            &quot;rating&quot;: 6.8,
            &quot;releaseDate&quot;: &quot;2025-11-11&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;,
                &quot;Science Fiction&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 38,
            &quot;is_free&quot;: true,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-28T09:13:20+00:00&quot;,
            &quot;videoUrl&quot;: null,
            &quot;duration&quot;: &quot;2h 13m&quot;,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: 798645,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Final Trailer&quot;,
                    &quot;key&quot;: &quot;_iHJHYjq7XI&quot;,
                    &quot;youtubeUrl&quot;: &quot;https://www.youtube.com/watch?v=_iHJHYjq7XI&quot;,
                    &quot;embedUrl&quot;: &quot;https://www.youtube.com/embed/_iHJHYjq7XI&quot;,
                    &quot;type&quot;: &quot;Trailer&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;New Trailer&quot;,
                    &quot;key&quot;: &quot;UsmiRIozzvw&quot;,
                    &quot;youtubeUrl&quot;: &quot;https://www.youtube.com/watch?v=UsmiRIozzvw&quot;,
                    &quot;embedUrl&quot;: &quot;https://www.youtube.com/embed/UsmiRIozzvw&quot;,
                    &quot;type&quot;: &quot;Trailer&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Official Trailer&quot;,
                    &quot;key&quot;: &quot;KD18ddeFuyM&quot;,
                    &quot;youtubeUrl&quot;: &quot;https://www.youtube.com/watch?v=KD18ddeFuyM&quot;,
                    &quot;embedUrl&quot;: &quot;https://www.youtube.com/embed/KD18ddeFuyM&quot;,
                    &quot;type&quot;: &quot;Trailer&quot;
                }
            ],
            &quot;crew&quot;: {
                &quot;directors&quot;: [
                    {
                        &quot;name&quot;: &quot;Edgar Wright&quot;,
                        &quot;profileImage&quot;: &quot;https://image.tmdb.org/t/p/w500/3BqgbeAkNnDcIVtrDvG6LJnEkZK.jpg&quot;
                    }
                ]
            },
            &quot;keywords&quot;: [
                &quot;based on novel or book&quot;,
                &quot;dark comedy&quot;,
                &quot;survival&quot;,
                &quot;on the run&quot;,
                &quot;television network&quot;,
                &quot;near future&quot;,
                &quot;malicious&quot;,
                &quot;depressing&quot;,
                &quot;mean spirited&quot;,
                &quot;dystopian future&quot;,
                &quot;dystopian sci-fi&quot;
            ],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 6,
                    &quot;name&quot;: &quot;Glen Powell&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_dc0231283b20ba9137b89911aa3a86b1.jpg&quot;,
                    &quot;role&quot;: &quot;Ben Richards&quot;
                },
                {
                    &quot;id&quot;: 7,
                    &quot;name&quot;: &quot;Josh Brolin&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_d56240ed42b0004e0580f363b5ba76a6.jpg&quot;,
                    &quot;role&quot;: &quot;Dan Killian&quot;
                },
                {
                    &quot;id&quot;: 8,
                    &quot;name&quot;: &quot;Colman Domingo&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_28483513c305c919bf1bcf698f90d313.jpg&quot;,
                    &quot;role&quot;: &quot;Bobby Thompson&quot;
                },
                {
                    &quot;id&quot;: 9,
                    &quot;name&quot;: &quot;Lee Pace&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_1f4b4e2c85c7872f783cdbdacf6350d2.jpg&quot;,
                    &quot;role&quot;: &quot;Evan McCone&quot;
                },
                {
                    &quot;id&quot;: 10,
                    &quot;name&quot;: &quot;Michael Cera&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_8a587cec8890c44293a70b16083b15ef.jpg&quot;,
                    &quot;role&quot;: &quot;Elton Parrakis&quot;
                },
                {
                    &quot;id&quot;: 11,
                    &quot;name&quot;: &quot;Emilia Jones&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_c7e03d651bba0d2c7d025c405bc10640.jpg&quot;,
                    &quot;role&quot;: &quot;Amelia Williams&quot;
                },
                {
                    &quot;id&quot;: 12,
                    &quot;name&quot;: &quot;William H. Macy&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_6d48da47b28613629f7a1008c3c811ed.jpg&quot;,
                    &quot;role&quot;: &quot;Molie Jernigan&quot;
                },
                {
                    &quot;id&quot;: 13,
                    &quot;name&quot;: &quot;Daniel Ezra&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_ec8c796dcdc6e6d5b205c54936b2956e.jpg&quot;,
                    &quot;role&quot;: &quot;Bradley Throckmorton&quot;
                },
                {
                    &quot;id&quot;: 14,
                    &quot;name&quot;: &quot;Jayme Lawson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_fb17da8e384ce433e30fe122c31c3f89.jpg&quot;,
                    &quot;role&quot;: &quot;Sheila Richards&quot;
                },
                {
                    &quot;id&quot;: 15,
                    &quot;name&quot;: &quot;Katy O&#039;Brian&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_92a1556af20f36c00853107999105e52.jpg&quot;,
                    &quot;role&quot;: &quot;Jenni Laughlin&quot;
                },
                {
                    &quot;id&quot;: 16,
                    &quot;name&quot;: &quot;Martin Herlihy&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_850f0c13386df1f7e4859e84326cfb17.jpg&quot;,
                    &quot;role&quot;: &quot;Tim Jansky&quot;
                },
                {
                    &quot;id&quot;: 17,
                    &quot;name&quot;: &quot;David Zayas&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_0d3e912105b1f893de1cef20d1f58af5.jpg&quot;,
                    &quot;role&quot;: &quot;Richard Manuel&quot;
                },
                {
                    &quot;id&quot;: 18,
                    &quot;name&quot;: &quot;Sean Hayes&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_c5090bd4ceccc4d741f4db6e72877707.jpg&quot;,
                    &quot;role&quot;: &quot;Gary Greenbacks&quot;
                },
                {
                    &quot;id&quot;: 19,
                    &quot;name&quot;: &quot;Karl Glusman&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4e17b630b07940d94ded092926eab643.jpg&quot;,
                    &quot;role&quot;: &quot;Frank&quot;
                },
                {
                    &quot;id&quot;: 20,
                    &quot;name&quot;: &quot;Sandra Dickinson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_19789e63233d2adbc5f006e75fb65535.jpg&quot;,
                    &quot;role&quot;: &quot;Victoria Parrakis&quot;
                },
                {
                    &quot;id&quot;: 21,
                    &quot;name&quot;: &quot;Shelley Conn&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_8808ef890dfb4b538bc541e5f23a7a78.jpg&quot;,
                    &quot;role&quot;: &quot;Dr. Raznor&quot;
                },
                {
                    &quot;id&quot;: 22,
                    &quot;name&quot;: &quot;James Austin Johnson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_9ddd59c382c8195b383ca3a5ae6183f7.jpg&quot;,
                    &quot;role&quot;: &quot;Announcer (Voice)&quot;
                },
                {
                    &quot;id&quot;: 23,
                    &quot;name&quot;: &quot;Debi Mazar&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_02ac9f108c38ae027389ea0c457d482d.jpg&quot;,
                    &quot;role&quot;: &quot;Amor&eacute; Americano&quot;
                },
                {
                    &quot;id&quot;: 24,
                    &quot;name&quot;: &quot;Emma Sidi&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_27f39a2c0dc0189e00c9154d44a3755b.jpg&quot;,
                    &quot;role&quot;: &quot;Adrian&eacute; Americano&quot;
                },
                {
                    &quot;id&quot;: 25,
                    &quot;name&quot;: &quot;Catherine Cohen&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_c50202415708b0985fcae7706a11bea7.jpg&quot;,
                    &quot;role&quot;: &quot;Arian&eacute; Americano&quot;
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 1,
            &quot;slug&quot;: &quot;zootopia-2&quot;,
            &quot;title&quot;: &quot;Zootopia 2&quot;,
            &quot;description&quot;: &quot;After cracking the biggest case in Zootopia&#039;s history, rookie cops Judy Hopps and Nick Wilde find themselves on the twisting trail of a great mystery when Gary De&#039;Snake arrives and turns the animal metropolis upside down. To crack the case, Judy and Nick must go undercover to unexpected new parts of town, where their growing partnership is tested like never before.&quot;,
            &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_f158459be819affd8c9f257f75a49f15.jpg&quot;,
            &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_7ee2bf6a30418364a91d7d4cffb592d0.jpg&quot;,
            &quot;rating&quot;: 7.7,
            &quot;releaseDate&quot;: &quot;2025-11-26&quot;,
            &quot;category&quot;: &quot;VJ Translated&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Mark&quot;,
            &quot;genre&quot;: [
                &quot;Adventure&quot;,
                &quot;Comedy&quot;,
                &quot;Mystery&quot;,
                &quot;Animation&quot;,
                &quot;Family&quot;
            ],
            &quot;trendingScore&quot;: 99,
            &quot;viewsCount&quot;: 46,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: true,
            &quot;createdAt&quot;: &quot;2025-12-27T19:16:26+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;1h 47m&quot;,
            &quot;priceRent&quot;: 1000,
            &quot;priceBuy&quot;: 2200,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: 1084242,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 46,
                    &quot;name&quot;: &quot;Ginnifer Goodwin&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_1965015e8a797cb0b19783fff65c70a6.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 47,
                    &quot;name&quot;: &quot;Jason Bateman&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_24af5af5c2a714e8d7e094ce98542331.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 48,
                    &quot;name&quot;: &quot;Ke Huy Quan&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2f557bbf6c12dd58d52cfcde5dbfcb95.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 49,
                    &quot;name&quot;: &quot;Fortune Feimster&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_da74cba983cb465cf09166f6a5ebebcb.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 50,
                    &quot;name&quot;: &quot;Andy Samberg&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_32e26a37052974f33269e6d8b824f8c9.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 51,
                    &quot;name&quot;: &quot;David Strathairn&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_24013c23a6b4c7d4198cfe4d8c871949.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 52,
                    &quot;name&quot;: &quot;Idris Elba&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_d9833e33d178b42c8d7bcce591083b9c.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 53,
                    &quot;name&quot;: &quot;Shakira&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_61342d35794a35095e3945ca22f6caab.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 54,
                    &quot;name&quot;: &quot;Patrick Warburton&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_96c351e316f4b20be6dd7d86ec53ada2.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 55,
                    &quot;name&quot;: &quot;Quinta Brunson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_5b9a14b1d80becc77bac57a929816a3d.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 56,
                    &quot;name&quot;: &quot;Danny Trejo&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_039fc588aa288508bf2671ba46d99810.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 57,
                    &quot;name&quot;: &quot;Nate Torrence&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3190b4c9644a189033c264e8437c6dfd.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 58,
                    &quot;name&quot;: &quot;Bonnie Hunt&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4fd0d847ec18458bc4e64374da0ac181.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 59,
                    &quot;name&quot;: &quot;Don Lake&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_a3efdd188f65551ecd1d1104bd0a4b10.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 60,
                    &quot;name&quot;: &quot;Michelle Gomez&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_ff92945a6f529045e2b357433bd22806.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 61,
                    &quot;name&quot;: &quot;David Fane&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_9afe598a68ee8b88489585722218413d.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 62,
                    &quot;name&quot;: &quot;Joe Anoa&#039;i&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2df01f849d6965c36cfbbf025f657927.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 63,
                    &quot;name&quot;: &quot;Phil Brooks&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_b2e5290073ddd70a68292aff9758d0c8.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 64,
                    &quot;name&quot;: &quot;Stephanie Beatriz&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_ddbac73d87a0884c788a3fbd9acad039.jpg&quot;,
                    &quot;role&quot;: null
                },
                {
                    &quot;id&quot;: 65,
                    &quot;name&quot;: &quot;Wilmer Valderrama&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_af6ba169784b0f671bfe13d8dc50cdca.jpg&quot;,
                    &quot;role&quot;: null
                }
            ],
            &quot;seasons&quot;: []
        },
        {
            &quot;id&quot;: 12,
            &quot;slug&quot;: &quot;the-last-stand&quot;,
            &quot;title&quot;: &quot;The Last Stand&quot;,
            &quot;description&quot;: &quot;Western showdown.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.1,
            &quot;releaseDate&quot;: &quot;2024-07-04&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Western&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;viewsCount&quot;: 0,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: false,
            &quot;createdAt&quot;: &quot;2025-12-27T19:17:37+00:00&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;priceRent&quot;: null,
            &quot;priceBuy&quot;: null,
            &quot;downloadEnabled&quot;: true,
            &quot;tmdbId&quot;: null,
            &quot;imdbId&quot;: null,
            &quot;tagline&quot;: null,
            &quot;trailers&quot;: [],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 1,
                    &quot;name&quot;: &quot;Tom Hardy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 4,
                    &quot;name&quot;: &quot;Florence Pugh&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 5,
                    &quot;name&quot;: &quot;Austin Butler&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 1&quot;
                },
                {
                    &quot;id&quot;: 2,
                    &quot;name&quot;: &quot;Zendaya&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 2&quot;
                },
                {
                    &quot;id&quot;: 3,
                    &quot;name&quot;: &quot;Cillian Murphy&quot;,
                    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
                    &quot;role&quot;: &quot;Character 3&quot;
                }
            ],
            &quot;seasons&quot;: []
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-movies-selected-today" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-movies-selected-today"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-movies-selected-today"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-movies-selected-today" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-movies-selected-today">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-movies-selected-today" data-method="GET"
      data-path="api/v1/movies/selected-today"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-movies-selected-today', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-movies-selected-today"
                    onclick="tryItOut('GETapi-v1-movies-selected-today');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-movies-selected-today"
                    onclick="cancelTryOut('GETapi-v1-movies-selected-today');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-movies-selected-today"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/movies/selected-today</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-movies-selected-today"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-movies-selected-today"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-movies-selected-today"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="movies-GETapi-v1-movies--id-">GET api/v1/movies/{id}</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-movies--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/movies/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/movies/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-movies--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;slug&quot;: &quot;zootopia-2&quot;,
    &quot;title&quot;: &quot;Zootopia 2&quot;,
    &quot;description&quot;: &quot;After cracking the biggest case in Zootopia&#039;s history, rookie cops Judy Hopps and Nick Wilde find themselves on the twisting trail of a great mystery when Gary De&#039;Snake arrives and turns the animal metropolis upside down. To crack the case, Judy and Nick must go undercover to unexpected new parts of town, where their growing partnership is tested like never before.&quot;,
    &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_f158459be819affd8c9f257f75a49f15.jpg&quot;,
    &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_7ee2bf6a30418364a91d7d4cffb592d0.jpg&quot;,
    &quot;rating&quot;: 7.7,
    &quot;releaseDate&quot;: &quot;2025-11-26&quot;,
    &quot;category&quot;: &quot;VJ Translated&quot;,
    &quot;mediaType&quot;: &quot;MOVIE&quot;,
    &quot;vj&quot;: &quot;VJ Mark&quot;,
    &quot;genre&quot;: [
        &quot;Adventure&quot;,
        &quot;Comedy&quot;,
        &quot;Mystery&quot;,
        &quot;Animation&quot;,
        &quot;Family&quot;
    ],
    &quot;trendingScore&quot;: 99,
    &quot;viewsCount&quot;: 46,
    &quot;is_free&quot;: false,
    &quot;is_premium&quot;: true,
    &quot;createdAt&quot;: &quot;2025-12-27T19:16:26+00:00&quot;,
    &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
    &quot;duration&quot;: &quot;1h 47m&quot;,
    &quot;priceRent&quot;: 1000,
    &quot;priceBuy&quot;: 2200,
    &quot;downloadEnabled&quot;: true,
    &quot;tmdbId&quot;: 1084242,
    &quot;imdbId&quot;: null,
    &quot;tagline&quot;: null,
    &quot;trailers&quot;: [],
    &quot;crew&quot;: {
        &quot;directors&quot;: []
    },
    &quot;keywords&quot;: [],
    &quot;cast&quot;: [
        {
            &quot;id&quot;: 46,
            &quot;name&quot;: &quot;Ginnifer Goodwin&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_1965015e8a797cb0b19783fff65c70a6.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 47,
            &quot;name&quot;: &quot;Jason Bateman&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_24af5af5c2a714e8d7e094ce98542331.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 48,
            &quot;name&quot;: &quot;Ke Huy Quan&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2f557bbf6c12dd58d52cfcde5dbfcb95.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 49,
            &quot;name&quot;: &quot;Fortune Feimster&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_da74cba983cb465cf09166f6a5ebebcb.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 50,
            &quot;name&quot;: &quot;Andy Samberg&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_32e26a37052974f33269e6d8b824f8c9.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 51,
            &quot;name&quot;: &quot;David Strathairn&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_24013c23a6b4c7d4198cfe4d8c871949.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 52,
            &quot;name&quot;: &quot;Idris Elba&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_d9833e33d178b42c8d7bcce591083b9c.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 53,
            &quot;name&quot;: &quot;Shakira&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_61342d35794a35095e3945ca22f6caab.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 54,
            &quot;name&quot;: &quot;Patrick Warburton&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_96c351e316f4b20be6dd7d86ec53ada2.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 55,
            &quot;name&quot;: &quot;Quinta Brunson&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_5b9a14b1d80becc77bac57a929816a3d.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 56,
            &quot;name&quot;: &quot;Danny Trejo&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_039fc588aa288508bf2671ba46d99810.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 57,
            &quot;name&quot;: &quot;Nate Torrence&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3190b4c9644a189033c264e8437c6dfd.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 58,
            &quot;name&quot;: &quot;Bonnie Hunt&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4fd0d847ec18458bc4e64374da0ac181.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 59,
            &quot;name&quot;: &quot;Don Lake&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_a3efdd188f65551ecd1d1104bd0a4b10.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 60,
            &quot;name&quot;: &quot;Michelle Gomez&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_ff92945a6f529045e2b357433bd22806.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 61,
            &quot;name&quot;: &quot;David Fane&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_9afe598a68ee8b88489585722218413d.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 62,
            &quot;name&quot;: &quot;Joe Anoa&#039;i&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2df01f849d6965c36cfbbf025f657927.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 63,
            &quot;name&quot;: &quot;Phil Brooks&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_b2e5290073ddd70a68292aff9758d0c8.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 64,
            &quot;name&quot;: &quot;Stephanie Beatriz&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_ddbac73d87a0884c788a3fbd9acad039.jpg&quot;,
            &quot;role&quot;: null
        },
        {
            &quot;id&quot;: 65,
            &quot;name&quot;: &quot;Wilmer Valderrama&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_af6ba169784b0f671bfe13d8dc50cdca.jpg&quot;,
            &quot;role&quot;: null
        }
    ],
    &quot;seasons&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-movies--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-movies--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-movies--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-movies--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-movies--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-movies--id-" data-method="GET"
      data-path="api/v1/movies/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-movies--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-movies--id-"
                    onclick="tryItOut('GETapi-v1-movies--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-movies--id-"
                    onclick="cancelTryOut('GETapi-v1-movies--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-movies--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/movies/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-movies--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-movies--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-movies--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-v1-movies--id-"
               value="1"
               data-component="url">
    <br>
<p>The ID of the movie. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="tv-shows">TV Shows</h1>

    <p>List and fetch TV shows with seasons and episodes.</p>

                                <h2 id="tv-shows-GETapi-v1-tv-shows">GET api/v1/tv-shows</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-tv-shows">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/tv-shows" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/tv-shows"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-tv-shows">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 2,
            &quot;slug&quot;: &quot;supernatural&quot;,
            &quot;title&quot;: &quot;Supernatural&quot;,
            &quot;description&quot;: &quot;When they were boys, Sam and Dean Winchester lost their mother to a mysterious and demonic supernatural force. Subsequently, their father raised them to be soldiers. He taught them about the paranormal evil that lives in the dark corners and on the back roads of America ... and he taught them how to kill it. Now, the Winchester brothers crisscross the country in their &#039;67 Chevy Impala, battling every kind of supernatural threat they encounter along the way. &quot;,
            &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_8e53180bf9dbb4b712ee2a5e5bbb2987.jpg&quot;,
            &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_0892f7b57f783ca8f2a2ac269b843f7b.jpg&quot;,
            &quot;rating&quot;: 8.3,
            &quot;releaseDate&quot;: &quot;2005-09-13&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Drama&quot;,
                &quot;Mystery&quot;,
                &quot;Sci-Fi &amp; Fantasy&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: true,
            &quot;duration&quot;: &quot;45m&quot;,
            &quot;priceRent&quot;: 1000,
            &quot;priceBuy&quot;: 3000,
            &quot;downloadEnabled&quot;: true,
            &quot;seasons&quot;: [],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 94,
                    &quot;name&quot;: &quot;Jared Padalecki&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_6e8096be9df5f4dddc8e69d4d78a49ad.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 95,
                    &quot;name&quot;: &quot;Jensen Ackles&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_0409aad854c4e103b5034d15fc529c05.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 96,
                    &quot;name&quot;: &quot;Misha Collins&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_05718096e14f828cd9a69e83fe6b4148.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                }
            ],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;trailers&quot;: []
        },
        {
            &quot;id&quot;: 1,
            &quot;slug&quot;: &quot;stranger-things&quot;,
            &quot;title&quot;: &quot;Stranger Things&quot;,
            &quot;description&quot;: &quot;When a young boy vanishes, a small town uncovers a mystery involving secret experiments, terrifying supernatural forces, and one strange little girl.&quot;,
            &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_b8e9d22dd10e5fed9d0f33f3b59170d0.jpg&quot;,
            &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_e2c04435d51353a3b20da52de687c500.jpg&quot;,
            &quot;rating&quot;: 8.6,
            &quot;releaseDate&quot;: &quot;2016-07-15&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Mystery&quot;,
                &quot;Sci-Fi &amp; Fantasy&quot;,
                &quot;Action &amp; Adventure&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;is_free&quot;: false,
            &quot;is_premium&quot;: true,
            &quot;duration&quot;: &quot;45m&quot;,
            &quot;priceRent&quot;: 1000,
            &quot;priceBuy&quot;: 2000,
            &quot;downloadEnabled&quot;: true,
            &quot;seasons&quot;: [
                {
                    &quot;id&quot;: 17,
                    &quot;number&quot;: 1,
                    &quot;title&quot;: &quot;Season 1&quot;,
                    &quot;description&quot;: &quot;Strange things are afoot in Hawkins, Indiana, where a young boy&#039;s sudden disappearance unearths a young girl with otherworldly powers.&quot;,
                    &quot;episodes&quot;: [
                        {
                            &quot;id&quot;: 329,
                            &quot;number&quot;: 1,
                            &quot;title&quot;: &quot;Chapter One: The Vanishing of Will Byers&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/uLES7sRpy7Ih6Kr6XCaYj1GyfTw.jpg&quot;,
                            &quot;duration&quot;: &quot;48m&quot;,
                            &quot;description&quot;: &quot;On his way home from a friend&#039;s house, young Will sees something terrifying. Nearby, a sinister secret lurks in the depths of a government lab.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 330,
                            &quot;number&quot;: 2,
                            &quot;title&quot;: &quot;Chapter Two: The Weirdo on Maple Street&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/8iA56ugQyHZmX81wSsNqwXjCE6F.jpg&quot;,
                            &quot;duration&quot;: &quot;55m&quot;,
                            &quot;description&quot;: &quot;Lucas, Mike and Dustin try to talk to the girl they found in the woods. Hopper questions an anxious Joyce about an unsettling phone call.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 331,
                            &quot;number&quot;: 3,
                            &quot;title&quot;: &quot;Chapter Three: Holly, Jolly&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/5snULpWQWp7aqFto7UbRcEkEyyS.jpg&quot;,
                            &quot;duration&quot;: &quot;51m&quot;,
                            &quot;description&quot;: &quot;An increasingly concerned Nancy looks for Barb and finds out what Jonathan&#039;s been up to. Joyce is convinced Will is trying to talk to her.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 332,
                            &quot;number&quot;: 4,
                            &quot;title&quot;: &quot;Chapter Four: The Body&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/60wmC1e20HV8gu688GAhsWxqxPx.jpg&quot;,
                            &quot;duration&quot;: &quot;50m&quot;,
                            &quot;description&quot;: &quot;Refusing to believe Will is dead, Joyce tries to connect with her son. The boys give Eleven a makeover. Nancy and Jonathan form an unlikely alliance.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 333,
                            &quot;number&quot;: 5,
                            &quot;title&quot;: &quot;Chapter Five: The Flea and the Acrobat&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/exT4NW9EdXG1qLZHKJnRpq3gh1H.jpg&quot;,
                            &quot;duration&quot;: &quot;52m&quot;,
                            &quot;description&quot;: &quot;Hopper breaks into the lab while Nancy and Jonathan confront the force that took Will. The boys ask Mr. Clarke how to travel to another dimension.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 334,
                            &quot;number&quot;: 6,
                            &quot;title&quot;: &quot;Chapter Six: The Monster&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/lNS6qycyucewz3duTr1tf1LU688.jpg&quot;,
                            &quot;duration&quot;: &quot;46m&quot;,
                            &quot;description&quot;: &quot;A frantic Jonathan looks for Nancy in the darkness, but Steve&#039;s looking for her, too. Hopper and Joyce uncover the truth about the lab&#039;s experiments.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 335,
                            &quot;number&quot;: 7,
                            &quot;title&quot;: &quot;Chapter Seven: The Bathtub&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/fjsTVEnqTKUO0GJSpiWKBZRUBcx.jpg&quot;,
                            &quot;duration&quot;: &quot;42m&quot;,
                            &quot;description&quot;: &quot;Eleven struggles to reach Will, while Lucas warns that \&quot;the bad men are coming.\&quot; Nancy and Jonathan show the police what Jonathan caught on camera.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 336,
                            &quot;number&quot;: 8,
                            &quot;title&quot;: &quot;Chapter Eight: The Upside Down&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/1teJ5dbuepfqOOs9uYhYTUjr2qs.jpg&quot;,
                            &quot;duration&quot;: &quot;54m&quot;,
                            &quot;description&quot;: &quot;Dr. Brenner holds Hopper and Joyce for questioning while the boys wait with Eleven in the gym. Back at Will&#039;s, Nancy and Jonathan prepare for battle.&quot;,
                            &quot;videoUrl&quot;: null
                        }
                    ]
                },
                {
                    &quot;id&quot;: 18,
                    &quot;number&quot;: 2,
                    &quot;title&quot;: &quot;Stranger Things 2&quot;,
                    &quot;description&quot;: &quot;It&#039;s been nearly a year since Will&#039;s strange disappearance. But life&#039;s hardly back to normal in Hawkins. Not even close.&quot;,
                    &quot;episodes&quot;: [
                        {
                            &quot;id&quot;: 337,
                            &quot;number&quot;: 1,
                            &quot;title&quot;: &quot;Chapter One: MADMAX&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/efz0MgPAxPw11PIeAJNgKKg3Paa.jpg&quot;,
                            &quot;duration&quot;: &quot;48m&quot;,
                            &quot;description&quot;: &quot;As the town preps for Halloween, a high-scoring rival shakes things up at the arcade, and a skeptical Hopper inspects a field of rotting pumpkins.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 338,
                            &quot;number&quot;: 2,
                            &quot;title&quot;: &quot;Chapter Two: Trick or Treat, Freak&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/3gzPnRilyASmcoSyUKXhaD5ofhr.jpg&quot;,
                            &quot;duration&quot;: &quot;56m&quot;,
                            &quot;description&quot;: &quot;After Will sees something terrible on trick-or-treat night, Mike wonders whether Eleven&#039;s still out there. Nancy wrestles with the truth about Barb.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 339,
                            &quot;number&quot;: 3,
                            &quot;title&quot;: &quot;Chapter Three: The Pollywog&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/792NQjFydcr5ucb1sga55LS6Vt3.jpg&quot;,
                            &quot;duration&quot;: &quot;51m&quot;,
                            &quot;description&quot;: &quot;Dustin adopts a strange new pet, and Eleven grows increasingly impatient. A well-meaning Bob urges Will to stand up to his fears.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 340,
                            &quot;number&quot;: 4,
                            &quot;title&quot;: &quot;Chapter Four: Will the Wise&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/wZXeV5cWC1RuaCls2mm6imzchnN.jpg&quot;,
                            &quot;duration&quot;: &quot;46m&quot;,
                            &quot;description&quot;: &quot;An ailing Will opens up to Joyce -- with disturbing results. While Hopper digs for the truth, Eleven unearths a surprising discovery.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 341,
                            &quot;number&quot;: 5,
                            &quot;title&quot;: &quot;Chapter Five: Dig Dug&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/oshY3LAwQRzhcjmEEr7EbOnypuU.jpg&quot;,
                            &quot;duration&quot;: &quot;58m&quot;,
                            &quot;description&quot;: &quot;Nancy and Jonathan swap conspiracy theories with a new ally as Eleven searches for someone from her past. &ldquo;Bob the Brain&rdquo; tackles a difficult problem.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 342,
                            &quot;number&quot;: 6,
                            &quot;title&quot;: &quot;Chapter Six: The Spy&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/d90nCiTEACFEUd3fcX8DrLBi5DL.jpg&quot;,
                            &quot;duration&quot;: &quot;52m&quot;,
                            &quot;description&quot;: &quot;Will&#039;s connection to a shadowy evil grows stronger, but no one&#039;s quite sure how to stop it. Elsewhere, Dustin and Steve forge an unlikely bond.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 343,
                            &quot;number&quot;: 7,
                            &quot;title&quot;: &quot;Chapter Seven: The Lost Sister&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/kgOaaTbAutwAoA7tkVzYCfTjPXn.jpg&quot;,
                            &quot;duration&quot;: &quot;46m&quot;,
                            &quot;description&quot;: &quot;Psychic visions draw Eleven to a band of violent outcasts and an angry girl with a shadowy past.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 344,
                            &quot;number&quot;: 8,
                            &quot;title&quot;: &quot;Chapter Eight: The Mind Flayer&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/vuLea0DA9rYc9Y4M2Ic28CXL4Al.jpg&quot;,
                            &quot;duration&quot;: &quot;48m&quot;,
                            &quot;description&quot;: &quot;An unlikely hero steps forward when a deadly development puts the Hawkins Lab on lockdown, trapping Will and several others inside.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 345,
                            &quot;number&quot;: 9,
                            &quot;title&quot;: &quot;Chapter Nine: The Gate&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/cxCf7O5WPHfyXb7PLSJCG6EvXc3.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 2m&quot;,
                            &quot;description&quot;: &quot;Eleven makes plans to finish what she started while the survivors turn up the heat on the monstrous force that&#039;s holding Will hostage.&quot;,
                            &quot;videoUrl&quot;: null
                        }
                    ]
                },
                {
                    &quot;id&quot;: 19,
                    &quot;number&quot;: 3,
                    &quot;title&quot;: &quot;Stranger Things 3&quot;,
                    &quot;description&quot;: &quot;Budding romance. A brand-new mall. And rabid rats running toward danger. It&#039;s the summer of 1985 in Hawkins ... and one summer can change everything.&quot;,
                    &quot;episodes&quot;: [
                        {
                            &quot;id&quot;: 346,
                            &quot;number&quot;: 1,
                            &quot;title&quot;: &quot;Chapter One: Suzie, Do You Copy?&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/g1iZmyn42qSFkXhw6gjoNE0diKb.jpg&quot;,
                            &quot;duration&quot;: &quot;51m&quot;,
                            &quot;description&quot;: &quot;Summer brings new jobs and budding romance. But the mood shifts when Dustin&#039;s radio picks up a Russian broadcast, and Will senses something is wrong.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 347,
                            &quot;number&quot;: 2,
                            &quot;title&quot;: &quot;Chapter Two: The Mall Rats&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/qDlMvdlRGzIGtKYdV9lktEUM4vj.jpg&quot;,
                            &quot;duration&quot;: &quot;51m&quot;,
                            &quot;description&quot;: &quot;Nancy and Jonathan follow a lead, Steve and Robin sign on to a secret mission, and Max and Eleven go shopping. A rattled Billy has troubling visions.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 348,
                            &quot;number&quot;: 3,
                            &quot;title&quot;: &quot;Chapter Three: The Case of the Missing Lifeguard&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/oaYdVvYwnvoQ7SLelKUcaAt0HKJ.jpg&quot;,
                            &quot;duration&quot;: &quot;50m&quot;,
                            &quot;description&quot;: &quot;With El and Max looking for Billy, Will declares a day without girls. Steve and Dustin go on a stakeout, and Joyce and Hopper return to Hawkins Lab.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 349,
                            &quot;number&quot;: 4,
                            &quot;title&quot;: &quot;Chapter Four: The Sauna Test&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/tEgd6fXKngAG8eC92dH7ey6Y4eA.jpg&quot;,
                            &quot;duration&quot;: &quot;53m&quot;,
                            &quot;description&quot;: &quot;A code red brings the gang back together to face a frighteningly familiar evil. Karen urges Nancy to keep digging, and Robin finds a useful map.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 350,
                            &quot;number&quot;: 5,
                            &quot;title&quot;: &quot;Chapter Five: The Flayed&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/cxIZ4btU6h9GQM1Fn0kotuaXlwo.jpg&quot;,
                            &quot;duration&quot;: &quot;52m&quot;,
                            &quot;description&quot;: &quot;Strange surprises lurk inside an old farmhouse and deep beneath the Starcourt Mall. Meanwhile, the Mind Flayer is gathering strength.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 351,
                            &quot;number&quot;: 6,
                            &quot;title&quot;: &quot;Chapter Six: E Pluribus Unum&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/dZKsMHJm8DPdBuqFcZxdIulrpNs.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 0m&quot;,
                            &quot;description&quot;: &quot;Dr. Alexei reveals what the Russians have been building, and Eleven sees where Billy has been. Dustin and Erica stage a daring rescue.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 352,
                            &quot;number&quot;: 7,
                            &quot;title&quot;: &quot;Chapter Seven: The Bite&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/6QA91GJK2ze1EaGPEKhil9MJIXx.jpg&quot;,
                            &quot;duration&quot;: &quot;56m&quot;,
                            &quot;description&quot;: &quot;With time running out -- and an assassin close behind -- Hopper&#039;s crew races back to Hawkins, where El and the kids are preparing for war.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 353,
                            &quot;number&quot;: 8,
                            &quot;title&quot;: &quot;Chapter Eight: The Battle of Starcourt&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/5YcjTWas07RteM9lssOzL9UhmJh.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 18m&quot;,
                            &quot;description&quot;: &quot;Terror reigns in the food court when the Mind Flayer comes to collect. But down below, in the dark, the future of the world is at stake.&quot;,
                            &quot;videoUrl&quot;: null
                        }
                    ]
                },
                {
                    &quot;id&quot;: 20,
                    &quot;number&quot;: 4,
                    &quot;title&quot;: &quot;Stranger Things 4&quot;,
                    &quot;description&quot;: &quot;Darkness returns to Hawkins just in time for spring break, igniting fresh terror, disturbing memories &mdash; and an ominous new threat.&quot;,
                    &quot;episodes&quot;: [
                        {
                            &quot;id&quot;: 354,
                            &quot;number&quot;: 1,
                            &quot;title&quot;: &quot;Chapter One: The Hellfire Club&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/xeNKubDmPiMraW4hXqzEBrN6f4A.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 19m&quot;,
                            &quot;description&quot;: &quot;El is bullied at school. Joyce opens a mysterious package. A scrappy player shakes up D&amp;D night.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 355,
                            &quot;number&quot;: 2,
                            &quot;title&quot;: &quot;Chapter Two: Vecna&#039;s Curse&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/lSSWqv7XrO51uY2uc4lql9Hub3f.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 18m&quot;,
                            &quot;description&quot;: &quot;A plane brings Mike to California &mdash; and a dead body brings Hawkins to a halt. Nancy goes looking for leads. A shaken Eddie tells the gang what he saw.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 356,
                            &quot;number&quot;: 3,
                            &quot;title&quot;: &quot;Chapter Three: The Monster and the Superhero&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/6q1UM63T5tiQcWrbsJvY3bunkyZ.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 4m&quot;,
                            &quot;description&quot;: &quot;Murray and Joyce fly to Alaska, and El faces serious consequences. Robin and Nancy dig up dirt on Hawkins&#039; demons. Dr. Owens delivers sobering news.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 357,
                            &quot;number&quot;: 4,
                            &quot;title&quot;: &quot;Chapter Four: Dear Billy&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/u3LeFRR7AhyPp4y0Ii7hpkD488b.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 19m&quot;,
                            &quot;description&quot;: &quot;Max is in grave danger... and running out of time. A patient at Pennhurst asylum has visitors. Elsewhere, in Russia, Hopper is hard at work.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 358,
                            &quot;number&quot;: 5,
                            &quot;title&quot;: &quot;Chapter Five: The Nina Project&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/yvXXeBv4zfDgwcZyxqH5LJAe4oV.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 15m&quot;,
                            &quot;description&quot;: &quot;Owens takes El to Nevada, where she&#039;s forced to confront her past, while the Hawkins kids comb a crumbling house for clues. Vecna claims another victim.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 359,
                            &quot;number&quot;: 6,
                            &quot;title&quot;: &quot;Chapter Six: The Dive&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/9EbhReDcbfmqLhDBg0Rn97z4lT.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 14m&quot;,
                            &quot;description&quot;: &quot;Behind the Iron Curtain, a risky rescue mission gets underway. The California crew seeks help from a hacker. Steve takes one for the team.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 360,
                            &quot;number&quot;: 7,
                            &quot;title&quot;: &quot;Chapter Seven: The Massacre at Hawkins Lab&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/n64hMYFaVunT8jqSVgYq5qt0Vbn.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 40m&quot;,
                            &quot;description&quot;: &quot;As Hopper braces to battle a monster, Dustin dissects Vecna&#039;s motives &mdash; and decodes a message from beyond. El finds strength in a distant memory.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 361,
                            &quot;number&quot;: 8,
                            &quot;title&quot;: &quot;Chapter Eight: Papa&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/sdNa4Z49RTZDkezFAMg00hciFZZ.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 26m&quot;,
                            &quot;description&quot;: &quot;Nancy has sobering visions, and El passes an important test. Back in Hawkins, the gang gathers supplies and prepares for battle.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 362,
                            &quot;number&quot;: 9,
                            &quot;title&quot;: &quot;Chapter Nine: The Piggyback&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/fvoa0Hosu4yK7TUiHglV8TvjMUB.jpg&quot;,
                            &quot;duration&quot;: &quot;2h 23m&quot;,
                            &quot;description&quot;: &quot;With selfless hearts and a clash of metal, heroes fight from every corner of the battlefield to save Hawkins &mdash; and the world itself.&quot;,
                            &quot;videoUrl&quot;: null
                        }
                    ]
                },
                {
                    &quot;id&quot;: 21,
                    &quot;number&quot;: 5,
                    &quot;title&quot;: &quot;Stranger Things 5&quot;,
                    &quot;description&quot;: &quot;The fall of 1987. Hawkins is scarred by rifts. Vecna has vanished and the government has placed the town under military quarantine, forcing Eleven back into hiding. To end this nightmare, they&#039;ll need everyone together, one last time.&quot;,
                    &quot;episodes&quot;: [
                        {
                            &quot;id&quot;: 363,
                            &quot;number&quot;: 1,
                            &quot;title&quot;: &quot;Chapter One: The Crawl&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/jnpSxSMdFAj4dtF59agzgmKM9fg.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 12m&quot;,
                            &quot;description&quot;: &quot;November, 1987. The gang evades the military to scour the Upside Down for Vecna &mdash; but fails to notice a threat lurking closer to home.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 364,
                            &quot;number&quot;: 2,
                            &quot;title&quot;: &quot;Chapter Two: The Vanishing of...&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/dI8N4IQpZNKloK4Dw6MugpSrwMS.jpg&quot;,
                            &quot;duration&quot;: &quot;58m&quot;,
                            &quot;description&quot;: &quot;After a vicious attack at the Wheeler home, Mike and Nancy confront the cost of secrecy, while El and Hopper embark on a rescue mission.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 365,
                            &quot;number&quot;: 3,
                            &quot;title&quot;: &quot;Chapter Three: The Turnbow Trap&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/vBLzxoyZTbT0ImHXKWG2fe7j2om.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 10m&quot;,
                            &quot;description&quot;: &quot;Will gains unique insight into Vecna&#039;s next move, giving the crew an opportunity to set a trap. Holly explores her new surroundings.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 366,
                            &quot;number&quot;: 4,
                            &quot;title&quot;: &quot;Chapter Four: Sorcerer&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/mp6nFiganZCbieJ0wSjIHz7bS8r.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 27m&quot;,
                            &quot;description&quot;: &quot;The military tightens its grip on the town. Mike, Lucas and Robin orchestrate a daring escape. El comes face-to-face with the enemy.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 367,
                            &quot;number&quot;: 5,
                            &quot;title&quot;: &quot;Chapter Five: Shock Jock&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/4dnRX3S1II3NGkrnwCFZkYpgK83.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 8m&quot;,
                            &quot;description&quot;: &quot;The gang hatches an electrifying plan to reconnect Will to the hive mind. Tensions flare during a search of the Upside Down&#039;s Hawkins Lab.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 368,
                            &quot;number&quot;: 6,
                            &quot;title&quot;: &quot;Chapter Six: Escape from Camazotz&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/4joGuE1zoHIyGta2tlcSBI5XiV8.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 16m&quot;,
                            &quot;description&quot;: &quot;As Holly and Max fight to escape Vecna&#039;s mind, El must find a way into Will&#039;s. Joyce wrestles with guilt. Jonathan and Nancy face a turning point.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 369,
                            &quot;number&quot;: 7,
                            &quot;title&quot;: &quot;Chapter Seven: The Bridge&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/dJiUttONHE4jjEjPDEOU2hIQBWO.jpg&quot;,
                            &quot;duration&quot;: &quot;1h 6m&quot;,
                            &quot;description&quot;: &quot;On the anniversary of Will&#039;s disappearance, the party reunites to prepare for a battle with world-altering implications.&quot;,
                            &quot;videoUrl&quot;: null
                        },
                        {
                            &quot;id&quot;: 370,
                            &quot;number&quot;: 8,
                            &quot;title&quot;: &quot;Chapter Eight: The Rightside Up&quot;,
                            &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/kP23RWbUWM6vGhT9PxFyP5VT3y4.jpg&quot;,
                            &quot;duration&quot;: &quot;2h 9m&quot;,
                            &quot;description&quot;: &quot;As Vecna prepares to destroy the world as we know it, the party must put everything on the line to defeat him once and for all.&quot;,
                            &quot;videoUrl&quot;: null
                        }
                    ]
                }
            ],
            &quot;cast&quot;: [
                {
                    &quot;id&quot;: 76,
                    &quot;name&quot;: &quot;Millie Bobby Brown&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_d11fa16fc92e7d2d38c7bcc98bc45c12.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 77,
                    &quot;name&quot;: &quot;Winona Ryder&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_0ea637a3afaf067d551155817619b800.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 78,
                    &quot;name&quot;: &quot;David Harbour&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_aa47014f57653805580488d1bea5666a.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 79,
                    &quot;name&quot;: &quot;Finn Wolfhard&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_e90f9df98183b8f53e4d55eab0ddfe52.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 80,
                    &quot;name&quot;: &quot;Gaten Matarazzo&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_5c57d3372a77f036a6a867fbf72f6c10.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 81,
                    &quot;name&quot;: &quot;Caleb McLaughlin&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2ca79b257871c56a16cba3acf91f6b74.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 82,
                    &quot;name&quot;: &quot;Noah Schnapp&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3267efae461f1539ae11ae65aaf1748e.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 83,
                    &quot;name&quot;: &quot;Sadie Sink&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_6402a1f20e68d274ba4a1bd1e6b26de1.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 84,
                    &quot;name&quot;: &quot;Charlie Heaton&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_92ae32e506cd2527e95d0847d372bf50.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 85,
                    &quot;name&quot;: &quot;Natalia Dyer&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_8e7a4486f7fcd19d455109f3d421111c.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 86,
                    &quot;name&quot;: &quot;Joe Keery&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3effa5927187ac83c18f9be1bb4aa608.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 87,
                    &quot;name&quot;: &quot;Maya Hawke&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_9b81fbd6db7177ef8214d17dcb8e0a1b.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 88,
                    &quot;name&quot;: &quot;Cara Buono&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_37ffad63fc52b119bcb71c9a4b3b0e91.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 89,
                    &quot;name&quot;: &quot;Priah Ferguson&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_38498bfe42d8b2473333b7ba8474aec8.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 90,
                    &quot;name&quot;: &quot;Brett Gelman&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3102e3d8d5bddb1e0e70c5474d5d97e0.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 91,
                    &quot;name&quot;: &quot;Jamie Campbell Bower&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4d08bc9ef20c3f3e1e9c25e874898015.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 92,
                    &quot;name&quot;: &quot;Linda Hamilton&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_15909a19c24eee8c0f53f576bd9e1fdf.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                },
                {
                    &quot;id&quot;: 93,
                    &quot;name&quot;: &quot;Nell Fisher&quot;,
                    &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4e89f2a830b3a5acce09b06d0d4b19dc.jpg&quot;,
                    &quot;role&quot;: &quot;Actor&quot;
                }
            ],
            &quot;crew&quot;: {
                &quot;directors&quot;: []
            },
            &quot;keywords&quot;: [],
            &quot;trailers&quot;: []
        }
    ],
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 20,
        &quot;total&quot;: 2
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-tv-shows" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-tv-shows"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-tv-shows"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-tv-shows" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-tv-shows">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-tv-shows" data-method="GET"
      data-path="api/v1/tv-shows"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-tv-shows', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-tv-shows"
                    onclick="tryItOut('GETapi-v1-tv-shows');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-tv-shows"
                    onclick="cancelTryOut('GETapi-v1-tv-shows');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-tv-shows"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/tv-shows</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-tv-shows"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-tv-shows"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-tv-shows"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="tv-shows-GETapi-v1-tv-shows--id-">GET api/v1/tv-shows/{id}</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-tv-shows--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/tv-shows/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/tv-shows/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-tv-shows--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;slug&quot;: &quot;stranger-things&quot;,
    &quot;title&quot;: &quot;Stranger Things&quot;,
    &quot;description&quot;: &quot;When a young boy vanishes, a small town uncovers a mystery involving secret experiments, terrifying supernatural forces, and one strange little girl.&quot;,
    &quot;thumbnail&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/posters/tmdb_b8e9d22dd10e5fed9d0f33f3b59170d0.jpg&quot;,
    &quot;backdrop&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/backdrops/tmdb_e2c04435d51353a3b20da52de687c500.jpg&quot;,
    &quot;rating&quot;: 8.6,
    &quot;releaseDate&quot;: &quot;2016-07-15&quot;,
    &quot;category&quot;: &quot;Series&quot;,
    &quot;mediaType&quot;: &quot;SERIES&quot;,
    &quot;vj&quot;: null,
    &quot;genre&quot;: [
        &quot;Mystery&quot;,
        &quot;Sci-Fi &amp; Fantasy&quot;,
        &quot;Action &amp; Adventure&quot;
    ],
    &quot;trendingScore&quot;: 0,
    &quot;accessType&quot;: null,
    &quot;is_free&quot;: false,
    &quot;is_premium&quot;: true,
    &quot;duration&quot;: &quot;45m&quot;,
    &quot;priceRent&quot;: 1000,
    &quot;priceBuy&quot;: 2000,
    &quot;downloadEnabled&quot;: true,
    &quot;seasons&quot;: [
        {
            &quot;id&quot;: 17,
            &quot;number&quot;: 1,
            &quot;title&quot;: &quot;Season 1&quot;,
            &quot;description&quot;: &quot;Strange things are afoot in Hawkins, Indiana, where a young boy&#039;s sudden disappearance unearths a young girl with otherworldly powers.&quot;,
            &quot;episodes&quot;: [
                {
                    &quot;id&quot;: 329,
                    &quot;number&quot;: 1,
                    &quot;title&quot;: &quot;Chapter One: The Vanishing of Will Byers&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/uLES7sRpy7Ih6Kr6XCaYj1GyfTw.jpg&quot;,
                    &quot;duration&quot;: &quot;48m&quot;,
                    &quot;description&quot;: &quot;On his way home from a friend&#039;s house, young Will sees something terrifying. Nearby, a sinister secret lurks in the depths of a government lab.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 330,
                    &quot;number&quot;: 2,
                    &quot;title&quot;: &quot;Chapter Two: The Weirdo on Maple Street&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/8iA56ugQyHZmX81wSsNqwXjCE6F.jpg&quot;,
                    &quot;duration&quot;: &quot;55m&quot;,
                    &quot;description&quot;: &quot;Lucas, Mike and Dustin try to talk to the girl they found in the woods. Hopper questions an anxious Joyce about an unsettling phone call.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 331,
                    &quot;number&quot;: 3,
                    &quot;title&quot;: &quot;Chapter Three: Holly, Jolly&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/5snULpWQWp7aqFto7UbRcEkEyyS.jpg&quot;,
                    &quot;duration&quot;: &quot;51m&quot;,
                    &quot;description&quot;: &quot;An increasingly concerned Nancy looks for Barb and finds out what Jonathan&#039;s been up to. Joyce is convinced Will is trying to talk to her.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 332,
                    &quot;number&quot;: 4,
                    &quot;title&quot;: &quot;Chapter Four: The Body&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/60wmC1e20HV8gu688GAhsWxqxPx.jpg&quot;,
                    &quot;duration&quot;: &quot;50m&quot;,
                    &quot;description&quot;: &quot;Refusing to believe Will is dead, Joyce tries to connect with her son. The boys give Eleven a makeover. Nancy and Jonathan form an unlikely alliance.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 333,
                    &quot;number&quot;: 5,
                    &quot;title&quot;: &quot;Chapter Five: The Flea and the Acrobat&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/exT4NW9EdXG1qLZHKJnRpq3gh1H.jpg&quot;,
                    &quot;duration&quot;: &quot;52m&quot;,
                    &quot;description&quot;: &quot;Hopper breaks into the lab while Nancy and Jonathan confront the force that took Will. The boys ask Mr. Clarke how to travel to another dimension.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 334,
                    &quot;number&quot;: 6,
                    &quot;title&quot;: &quot;Chapter Six: The Monster&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/lNS6qycyucewz3duTr1tf1LU688.jpg&quot;,
                    &quot;duration&quot;: &quot;46m&quot;,
                    &quot;description&quot;: &quot;A frantic Jonathan looks for Nancy in the darkness, but Steve&#039;s looking for her, too. Hopper and Joyce uncover the truth about the lab&#039;s experiments.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 335,
                    &quot;number&quot;: 7,
                    &quot;title&quot;: &quot;Chapter Seven: The Bathtub&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/fjsTVEnqTKUO0GJSpiWKBZRUBcx.jpg&quot;,
                    &quot;duration&quot;: &quot;42m&quot;,
                    &quot;description&quot;: &quot;Eleven struggles to reach Will, while Lucas warns that \&quot;the bad men are coming.\&quot; Nancy and Jonathan show the police what Jonathan caught on camera.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 336,
                    &quot;number&quot;: 8,
                    &quot;title&quot;: &quot;Chapter Eight: The Upside Down&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/1teJ5dbuepfqOOs9uYhYTUjr2qs.jpg&quot;,
                    &quot;duration&quot;: &quot;54m&quot;,
                    &quot;description&quot;: &quot;Dr. Brenner holds Hopper and Joyce for questioning while the boys wait with Eleven in the gym. Back at Will&#039;s, Nancy and Jonathan prepare for battle.&quot;,
                    &quot;videoUrl&quot;: null
                }
            ]
        },
        {
            &quot;id&quot;: 18,
            &quot;number&quot;: 2,
            &quot;title&quot;: &quot;Stranger Things 2&quot;,
            &quot;description&quot;: &quot;It&#039;s been nearly a year since Will&#039;s strange disappearance. But life&#039;s hardly back to normal in Hawkins. Not even close.&quot;,
            &quot;episodes&quot;: [
                {
                    &quot;id&quot;: 337,
                    &quot;number&quot;: 1,
                    &quot;title&quot;: &quot;Chapter One: MADMAX&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/efz0MgPAxPw11PIeAJNgKKg3Paa.jpg&quot;,
                    &quot;duration&quot;: &quot;48m&quot;,
                    &quot;description&quot;: &quot;As the town preps for Halloween, a high-scoring rival shakes things up at the arcade, and a skeptical Hopper inspects a field of rotting pumpkins.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 338,
                    &quot;number&quot;: 2,
                    &quot;title&quot;: &quot;Chapter Two: Trick or Treat, Freak&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/3gzPnRilyASmcoSyUKXhaD5ofhr.jpg&quot;,
                    &quot;duration&quot;: &quot;56m&quot;,
                    &quot;description&quot;: &quot;After Will sees something terrible on trick-or-treat night, Mike wonders whether Eleven&#039;s still out there. Nancy wrestles with the truth about Barb.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 339,
                    &quot;number&quot;: 3,
                    &quot;title&quot;: &quot;Chapter Three: The Pollywog&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/792NQjFydcr5ucb1sga55LS6Vt3.jpg&quot;,
                    &quot;duration&quot;: &quot;51m&quot;,
                    &quot;description&quot;: &quot;Dustin adopts a strange new pet, and Eleven grows increasingly impatient. A well-meaning Bob urges Will to stand up to his fears.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 340,
                    &quot;number&quot;: 4,
                    &quot;title&quot;: &quot;Chapter Four: Will the Wise&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/wZXeV5cWC1RuaCls2mm6imzchnN.jpg&quot;,
                    &quot;duration&quot;: &quot;46m&quot;,
                    &quot;description&quot;: &quot;An ailing Will opens up to Joyce -- with disturbing results. While Hopper digs for the truth, Eleven unearths a surprising discovery.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 341,
                    &quot;number&quot;: 5,
                    &quot;title&quot;: &quot;Chapter Five: Dig Dug&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/oshY3LAwQRzhcjmEEr7EbOnypuU.jpg&quot;,
                    &quot;duration&quot;: &quot;58m&quot;,
                    &quot;description&quot;: &quot;Nancy and Jonathan swap conspiracy theories with a new ally as Eleven searches for someone from her past. &ldquo;Bob the Brain&rdquo; tackles a difficult problem.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 342,
                    &quot;number&quot;: 6,
                    &quot;title&quot;: &quot;Chapter Six: The Spy&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/d90nCiTEACFEUd3fcX8DrLBi5DL.jpg&quot;,
                    &quot;duration&quot;: &quot;52m&quot;,
                    &quot;description&quot;: &quot;Will&#039;s connection to a shadowy evil grows stronger, but no one&#039;s quite sure how to stop it. Elsewhere, Dustin and Steve forge an unlikely bond.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 343,
                    &quot;number&quot;: 7,
                    &quot;title&quot;: &quot;Chapter Seven: The Lost Sister&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/kgOaaTbAutwAoA7tkVzYCfTjPXn.jpg&quot;,
                    &quot;duration&quot;: &quot;46m&quot;,
                    &quot;description&quot;: &quot;Psychic visions draw Eleven to a band of violent outcasts and an angry girl with a shadowy past.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 344,
                    &quot;number&quot;: 8,
                    &quot;title&quot;: &quot;Chapter Eight: The Mind Flayer&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/vuLea0DA9rYc9Y4M2Ic28CXL4Al.jpg&quot;,
                    &quot;duration&quot;: &quot;48m&quot;,
                    &quot;description&quot;: &quot;An unlikely hero steps forward when a deadly development puts the Hawkins Lab on lockdown, trapping Will and several others inside.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 345,
                    &quot;number&quot;: 9,
                    &quot;title&quot;: &quot;Chapter Nine: The Gate&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/cxCf7O5WPHfyXb7PLSJCG6EvXc3.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 2m&quot;,
                    &quot;description&quot;: &quot;Eleven makes plans to finish what she started while the survivors turn up the heat on the monstrous force that&#039;s holding Will hostage.&quot;,
                    &quot;videoUrl&quot;: null
                }
            ]
        },
        {
            &quot;id&quot;: 19,
            &quot;number&quot;: 3,
            &quot;title&quot;: &quot;Stranger Things 3&quot;,
            &quot;description&quot;: &quot;Budding romance. A brand-new mall. And rabid rats running toward danger. It&#039;s the summer of 1985 in Hawkins ... and one summer can change everything.&quot;,
            &quot;episodes&quot;: [
                {
                    &quot;id&quot;: 346,
                    &quot;number&quot;: 1,
                    &quot;title&quot;: &quot;Chapter One: Suzie, Do You Copy?&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/g1iZmyn42qSFkXhw6gjoNE0diKb.jpg&quot;,
                    &quot;duration&quot;: &quot;51m&quot;,
                    &quot;description&quot;: &quot;Summer brings new jobs and budding romance. But the mood shifts when Dustin&#039;s radio picks up a Russian broadcast, and Will senses something is wrong.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 347,
                    &quot;number&quot;: 2,
                    &quot;title&quot;: &quot;Chapter Two: The Mall Rats&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/qDlMvdlRGzIGtKYdV9lktEUM4vj.jpg&quot;,
                    &quot;duration&quot;: &quot;51m&quot;,
                    &quot;description&quot;: &quot;Nancy and Jonathan follow a lead, Steve and Robin sign on to a secret mission, and Max and Eleven go shopping. A rattled Billy has troubling visions.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 348,
                    &quot;number&quot;: 3,
                    &quot;title&quot;: &quot;Chapter Three: The Case of the Missing Lifeguard&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/oaYdVvYwnvoQ7SLelKUcaAt0HKJ.jpg&quot;,
                    &quot;duration&quot;: &quot;50m&quot;,
                    &quot;description&quot;: &quot;With El and Max looking for Billy, Will declares a day without girls. Steve and Dustin go on a stakeout, and Joyce and Hopper return to Hawkins Lab.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 349,
                    &quot;number&quot;: 4,
                    &quot;title&quot;: &quot;Chapter Four: The Sauna Test&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/tEgd6fXKngAG8eC92dH7ey6Y4eA.jpg&quot;,
                    &quot;duration&quot;: &quot;53m&quot;,
                    &quot;description&quot;: &quot;A code red brings the gang back together to face a frighteningly familiar evil. Karen urges Nancy to keep digging, and Robin finds a useful map.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 350,
                    &quot;number&quot;: 5,
                    &quot;title&quot;: &quot;Chapter Five: The Flayed&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/cxIZ4btU6h9GQM1Fn0kotuaXlwo.jpg&quot;,
                    &quot;duration&quot;: &quot;52m&quot;,
                    &quot;description&quot;: &quot;Strange surprises lurk inside an old farmhouse and deep beneath the Starcourt Mall. Meanwhile, the Mind Flayer is gathering strength.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 351,
                    &quot;number&quot;: 6,
                    &quot;title&quot;: &quot;Chapter Six: E Pluribus Unum&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/dZKsMHJm8DPdBuqFcZxdIulrpNs.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 0m&quot;,
                    &quot;description&quot;: &quot;Dr. Alexei reveals what the Russians have been building, and Eleven sees where Billy has been. Dustin and Erica stage a daring rescue.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 352,
                    &quot;number&quot;: 7,
                    &quot;title&quot;: &quot;Chapter Seven: The Bite&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/6QA91GJK2ze1EaGPEKhil9MJIXx.jpg&quot;,
                    &quot;duration&quot;: &quot;56m&quot;,
                    &quot;description&quot;: &quot;With time running out -- and an assassin close behind -- Hopper&#039;s crew races back to Hawkins, where El and the kids are preparing for war.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 353,
                    &quot;number&quot;: 8,
                    &quot;title&quot;: &quot;Chapter Eight: The Battle of Starcourt&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/5YcjTWas07RteM9lssOzL9UhmJh.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 18m&quot;,
                    &quot;description&quot;: &quot;Terror reigns in the food court when the Mind Flayer comes to collect. But down below, in the dark, the future of the world is at stake.&quot;,
                    &quot;videoUrl&quot;: null
                }
            ]
        },
        {
            &quot;id&quot;: 20,
            &quot;number&quot;: 4,
            &quot;title&quot;: &quot;Stranger Things 4&quot;,
            &quot;description&quot;: &quot;Darkness returns to Hawkins just in time for spring break, igniting fresh terror, disturbing memories &mdash; and an ominous new threat.&quot;,
            &quot;episodes&quot;: [
                {
                    &quot;id&quot;: 354,
                    &quot;number&quot;: 1,
                    &quot;title&quot;: &quot;Chapter One: The Hellfire Club&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/xeNKubDmPiMraW4hXqzEBrN6f4A.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 19m&quot;,
                    &quot;description&quot;: &quot;El is bullied at school. Joyce opens a mysterious package. A scrappy player shakes up D&amp;D night.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 355,
                    &quot;number&quot;: 2,
                    &quot;title&quot;: &quot;Chapter Two: Vecna&#039;s Curse&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/lSSWqv7XrO51uY2uc4lql9Hub3f.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 18m&quot;,
                    &quot;description&quot;: &quot;A plane brings Mike to California &mdash; and a dead body brings Hawkins to a halt. Nancy goes looking for leads. A shaken Eddie tells the gang what he saw.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 356,
                    &quot;number&quot;: 3,
                    &quot;title&quot;: &quot;Chapter Three: The Monster and the Superhero&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/6q1UM63T5tiQcWrbsJvY3bunkyZ.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 4m&quot;,
                    &quot;description&quot;: &quot;Murray and Joyce fly to Alaska, and El faces serious consequences. Robin and Nancy dig up dirt on Hawkins&#039; demons. Dr. Owens delivers sobering news.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 357,
                    &quot;number&quot;: 4,
                    &quot;title&quot;: &quot;Chapter Four: Dear Billy&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/u3LeFRR7AhyPp4y0Ii7hpkD488b.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 19m&quot;,
                    &quot;description&quot;: &quot;Max is in grave danger... and running out of time. A patient at Pennhurst asylum has visitors. Elsewhere, in Russia, Hopper is hard at work.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 358,
                    &quot;number&quot;: 5,
                    &quot;title&quot;: &quot;Chapter Five: The Nina Project&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/yvXXeBv4zfDgwcZyxqH5LJAe4oV.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 15m&quot;,
                    &quot;description&quot;: &quot;Owens takes El to Nevada, where she&#039;s forced to confront her past, while the Hawkins kids comb a crumbling house for clues. Vecna claims another victim.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 359,
                    &quot;number&quot;: 6,
                    &quot;title&quot;: &quot;Chapter Six: The Dive&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/9EbhReDcbfmqLhDBg0Rn97z4lT.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 14m&quot;,
                    &quot;description&quot;: &quot;Behind the Iron Curtain, a risky rescue mission gets underway. The California crew seeks help from a hacker. Steve takes one for the team.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 360,
                    &quot;number&quot;: 7,
                    &quot;title&quot;: &quot;Chapter Seven: The Massacre at Hawkins Lab&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/n64hMYFaVunT8jqSVgYq5qt0Vbn.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 40m&quot;,
                    &quot;description&quot;: &quot;As Hopper braces to battle a monster, Dustin dissects Vecna&#039;s motives &mdash; and decodes a message from beyond. El finds strength in a distant memory.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 361,
                    &quot;number&quot;: 8,
                    &quot;title&quot;: &quot;Chapter Eight: Papa&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/sdNa4Z49RTZDkezFAMg00hciFZZ.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 26m&quot;,
                    &quot;description&quot;: &quot;Nancy has sobering visions, and El passes an important test. Back in Hawkins, the gang gathers supplies and prepares for battle.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 362,
                    &quot;number&quot;: 9,
                    &quot;title&quot;: &quot;Chapter Nine: The Piggyback&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/fvoa0Hosu4yK7TUiHglV8TvjMUB.jpg&quot;,
                    &quot;duration&quot;: &quot;2h 23m&quot;,
                    &quot;description&quot;: &quot;With selfless hearts and a clash of metal, heroes fight from every corner of the battlefield to save Hawkins &mdash; and the world itself.&quot;,
                    &quot;videoUrl&quot;: null
                }
            ]
        },
        {
            &quot;id&quot;: 21,
            &quot;number&quot;: 5,
            &quot;title&quot;: &quot;Stranger Things 5&quot;,
            &quot;description&quot;: &quot;The fall of 1987. Hawkins is scarred by rifts. Vecna has vanished and the government has placed the town under military quarantine, forcing Eleven back into hiding. To end this nightmare, they&#039;ll need everyone together, one last time.&quot;,
            &quot;episodes&quot;: [
                {
                    &quot;id&quot;: 363,
                    &quot;number&quot;: 1,
                    &quot;title&quot;: &quot;Chapter One: The Crawl&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/jnpSxSMdFAj4dtF59agzgmKM9fg.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 12m&quot;,
                    &quot;description&quot;: &quot;November, 1987. The gang evades the military to scour the Upside Down for Vecna &mdash; but fails to notice a threat lurking closer to home.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 364,
                    &quot;number&quot;: 2,
                    &quot;title&quot;: &quot;Chapter Two: The Vanishing of...&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/dI8N4IQpZNKloK4Dw6MugpSrwMS.jpg&quot;,
                    &quot;duration&quot;: &quot;58m&quot;,
                    &quot;description&quot;: &quot;After a vicious attack at the Wheeler home, Mike and Nancy confront the cost of secrecy, while El and Hopper embark on a rescue mission.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 365,
                    &quot;number&quot;: 3,
                    &quot;title&quot;: &quot;Chapter Three: The Turnbow Trap&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/vBLzxoyZTbT0ImHXKWG2fe7j2om.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 10m&quot;,
                    &quot;description&quot;: &quot;Will gains unique insight into Vecna&#039;s next move, giving the crew an opportunity to set a trap. Holly explores her new surroundings.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 366,
                    &quot;number&quot;: 4,
                    &quot;title&quot;: &quot;Chapter Four: Sorcerer&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/mp6nFiganZCbieJ0wSjIHz7bS8r.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 27m&quot;,
                    &quot;description&quot;: &quot;The military tightens its grip on the town. Mike, Lucas and Robin orchestrate a daring escape. El comes face-to-face with the enemy.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 367,
                    &quot;number&quot;: 5,
                    &quot;title&quot;: &quot;Chapter Five: Shock Jock&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/4dnRX3S1II3NGkrnwCFZkYpgK83.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 8m&quot;,
                    &quot;description&quot;: &quot;The gang hatches an electrifying plan to reconnect Will to the hive mind. Tensions flare during a search of the Upside Down&#039;s Hawkins Lab.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 368,
                    &quot;number&quot;: 6,
                    &quot;title&quot;: &quot;Chapter Six: Escape from Camazotz&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/4joGuE1zoHIyGta2tlcSBI5XiV8.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 16m&quot;,
                    &quot;description&quot;: &quot;As Holly and Max fight to escape Vecna&#039;s mind, El must find a way into Will&#039;s. Joyce wrestles with guilt. Jonathan and Nancy face a turning point.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 369,
                    &quot;number&quot;: 7,
                    &quot;title&quot;: &quot;Chapter Seven: The Bridge&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/dJiUttONHE4jjEjPDEOU2hIQBWO.jpg&quot;,
                    &quot;duration&quot;: &quot;1h 6m&quot;,
                    &quot;description&quot;: &quot;On the anniversary of Will&#039;s disappearance, the party reunites to prepare for a battle with world-altering implications.&quot;,
                    &quot;videoUrl&quot;: null
                },
                {
                    &quot;id&quot;: 370,
                    &quot;number&quot;: 8,
                    &quot;title&quot;: &quot;Chapter Eight: The Rightside Up&quot;,
                    &quot;thumbnail&quot;: &quot;https://image.tmdb.org/t/p/w500/kP23RWbUWM6vGhT9PxFyP5VT3y4.jpg&quot;,
                    &quot;duration&quot;: &quot;2h 9m&quot;,
                    &quot;description&quot;: &quot;As Vecna prepares to destroy the world as we know it, the party must put everything on the line to defeat him once and for all.&quot;,
                    &quot;videoUrl&quot;: null
                }
            ]
        }
    ],
    &quot;cast&quot;: [
        {
            &quot;id&quot;: 76,
            &quot;name&quot;: &quot;Millie Bobby Brown&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_d11fa16fc92e7d2d38c7bcc98bc45c12.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 77,
            &quot;name&quot;: &quot;Winona Ryder&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_0ea637a3afaf067d551155817619b800.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 78,
            &quot;name&quot;: &quot;David Harbour&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_aa47014f57653805580488d1bea5666a.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 79,
            &quot;name&quot;: &quot;Finn Wolfhard&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_e90f9df98183b8f53e4d55eab0ddfe52.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 80,
            &quot;name&quot;: &quot;Gaten Matarazzo&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_5c57d3372a77f036a6a867fbf72f6c10.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 81,
            &quot;name&quot;: &quot;Caleb McLaughlin&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2ca79b257871c56a16cba3acf91f6b74.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 82,
            &quot;name&quot;: &quot;Noah Schnapp&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3267efae461f1539ae11ae65aaf1748e.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 83,
            &quot;name&quot;: &quot;Sadie Sink&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_6402a1f20e68d274ba4a1bd1e6b26de1.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 84,
            &quot;name&quot;: &quot;Charlie Heaton&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_92ae32e506cd2527e95d0847d372bf50.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 85,
            &quot;name&quot;: &quot;Natalia Dyer&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_8e7a4486f7fcd19d455109f3d421111c.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 86,
            &quot;name&quot;: &quot;Joe Keery&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3effa5927187ac83c18f9be1bb4aa608.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 87,
            &quot;name&quot;: &quot;Maya Hawke&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_9b81fbd6db7177ef8214d17dcb8e0a1b.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 88,
            &quot;name&quot;: &quot;Cara Buono&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_37ffad63fc52b119bcb71c9a4b3b0e91.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 89,
            &quot;name&quot;: &quot;Priah Ferguson&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_38498bfe42d8b2473333b7ba8474aec8.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 90,
            &quot;name&quot;: &quot;Brett Gelman&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3102e3d8d5bddb1e0e70c5474d5d97e0.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 91,
            &quot;name&quot;: &quot;Jamie Campbell Bower&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4d08bc9ef20c3f3e1e9c25e874898015.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 92,
            &quot;name&quot;: &quot;Linda Hamilton&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_15909a19c24eee8c0f53f576bd9e1fdf.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        },
        {
            &quot;id&quot;: 93,
            &quot;name&quot;: &quot;Nell Fisher&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4e89f2a830b3a5acce09b06d0d4b19dc.jpg&quot;,
            &quot;role&quot;: &quot;Actor&quot;
        }
    ],
    &quot;crew&quot;: {
        &quot;directors&quot;: []
    },
    &quot;keywords&quot;: [],
    &quot;trailers&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-tv-shows--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-tv-shows--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-tv-shows--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-tv-shows--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-tv-shows--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-tv-shows--id-" data-method="GET"
      data-path="api/v1/tv-shows/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-tv-shows--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-tv-shows--id-"
                    onclick="tryItOut('GETapi-v1-tv-shows--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-tv-shows--id-"
                    onclick="cancelTryOut('GETapi-v1-tv-shows--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-tv-shows--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/tv-shows/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-tv-shows--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-tv-shows--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-tv-shows--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-v1-tv-shows--id-"
               value="1"
               data-component="url">
    <br>
<p>The ID of the tv show. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="search">Search</h1>

    <p>Global search. Query: q. Returns archives (movies/TV), people (VJs), intel (articles).</p>

                                <h2 id="search-GETapi-v1-search">Search. Query: q (required). Response: data.archives, data.people, data.intel.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-search">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/search" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/search"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-search">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;archives&quot;: [],
        &quot;people&quot;: [],
        &quot;intel&quot;: []
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-search" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-search"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-search"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-search" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-search">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-search" data-method="GET"
      data-path="api/v1/search"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-search', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-search"
                    onclick="tryItOut('GETapi-v1-search');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-search"
                    onclick="cancelTryOut('GETapi-v1-search');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-search"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/search</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-search"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-search"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-search"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                <h1 id="vjs">VJs</h1>

    <p>List and fetch VJs (translators/presenters). Filter by featured; order by rating or movies count.</p>

                                <h2 id="vjs-GETapi-v1-vjs">List VJs. Query: featured (1), order_by (movies_count), limit.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-vjs">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/vjs" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/vjs"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-vjs">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;VJ Junior&quot;,
            &quot;slug&quot;: &quot;vj-junior&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
            &quot;banner&quot;: &quot;https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1920&amp;h=600&amp;fit=crop&quot;,
            &quot;rating&quot;: 4.9,
            &quot;specialty&quot;: [
                &quot;Action&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;bio&quot;: &quot;The legend of Luganda translation. Over two decades of bringing global cinema to local hearts.&quot;,
            &quot;translatedCount&quot;: 1542,
            &quot;moviesCount&quot;: 1
        },
        {
            &quot;id&quot;: 2,
            &quot;name&quot;: &quot;VJ Jingo&quot;,
            &quot;slug&quot;: &quot;vj-jingo&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=400&amp;fit=crop&quot;,
            &quot;banner&quot;: null,
            &quot;rating&quot;: 4.8,
            &quot;specialty&quot;: [
                &quot;Comedy&quot;,
                &quot;Drama&quot;
            ],
            &quot;bio&quot;: &quot;Expert storytelling through voice. Known for his unique comedic timing.&quot;,
            &quot;translatedCount&quot;: 980,
            &quot;moviesCount&quot;: 5
        },
        {
            &quot;id&quot;: 3,
            &quot;name&quot;: &quot;VJ Mark&quot;,
            &quot;slug&quot;: &quot;vj-mark&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=400&amp;h=400&amp;fit=crop&quot;,
            &quot;banner&quot;: null,
            &quot;rating&quot;: 4.7,
            &quot;specialty&quot;: [
                &quot;Horror&quot;,
                &quot;Mystery&quot;
            ],
            &quot;bio&quot;: &quot;The voice of tension. Specialized in high-stakes thrillers and horror.&quot;,
            &quot;translatedCount&quot;: 650,
            &quot;moviesCount&quot;: 4
        },
        {
            &quot;id&quot;: 4,
            &quot;name&quot;: &quot;VJ Emmy&quot;,
            &quot;slug&quot;: &quot;vj-emmy&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=400&amp;h=400&amp;fit=crop&quot;,
            &quot;banner&quot;: null,
            &quot;rating&quot;: 4.6,
            &quot;specialty&quot;: [
                &quot;Action&quot;,
                &quot;Romance&quot;
            ],
            &quot;bio&quot;: &quot;The voice of the youth. Combining modern slang with traditional storytelling.&quot;,
            &quot;translatedCount&quot;: 420,
            &quot;moviesCount&quot;: 1
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-vjs" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-vjs"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-vjs"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-vjs" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-vjs">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-vjs" data-method="GET"
      data-path="api/v1/vjs"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-vjs', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-vjs"
                    onclick="tryItOut('GETapi-v1-vjs');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-vjs"
                    onclick="cancelTryOut('GETapi-v1-vjs');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-vjs"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/vjs</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-vjs"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-vjs"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-vjs"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="vjs-GETapi-v1-vjs--id-">GET api/v1/vjs/{id}</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-vjs--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/vjs/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/vjs/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-vjs--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;slug&quot;: &quot;vj-junior&quot;,
    &quot;name&quot;: &quot;VJ Junior&quot;,
    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
    &quot;banner&quot;: &quot;https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1920&amp;h=600&amp;fit=crop&quot;,
    &quot;rating&quot;: 4.9,
    &quot;specialty&quot;: [
        &quot;Action&quot;,
        &quot;Sci-Fi&quot;
    ],
    &quot;bio&quot;: &quot;The legend of Luganda translation. Over two decades of bringing global cinema to local hearts.&quot;,
    &quot;translatedCount&quot;: 1542,
    &quot;movies&quot;: [
        {
            &quot;id&quot;: 8,
            &quot;slug&quot;: &quot;ancient-echoes&quot;,
            &quot;title&quot;: &quot;Ancient Echoes&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.9,
            &quot;genre&quot;: [
                &quot;Adventure&quot;,
                &quot;Mystery&quot;
            ]
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-vjs--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-vjs--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-vjs--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-vjs--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-vjs--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-vjs--id-" data-method="GET"
      data-path="api/v1/vjs/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-vjs--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-vjs--id-"
                    onclick="tryItOut('GETapi-v1-vjs--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-vjs--id-"
                    onclick="cancelTryOut('GETapi-v1-vjs--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-vjs--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/vjs/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-vjs--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-vjs--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-vjs--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-v1-vjs--id-"
               value="1"
               data-component="url">
    <br>
<p>The ID of the vj. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="articles">Articles</h1>

    <p>News/editorial. List and fetch by id or slug; filter by category, top_news.</p>

                                <h2 id="articles-GETapi-v1-articles">List articles. Query: category, top_news, per_page.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-articles">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/articles" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/articles"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-articles">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 5,
            &quot;slug&quot;: &quot;platform-maintenance-hub-synchronization&quot;,
            &quot;title&quot;: &quot;Platform Maintenance: Hub Synchronization&quot;,
            &quot;excerpt&quot;: &quot;Scheduled maintenance for our central identity servers coming this Sunday.&quot;,
            &quot;author&quot;: &quot;Ops Command&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;videoUrl&quot;: null,
            &quot;date&quot;: &quot;Nov 05, 2024&quot;,
            &quot;category&quot;: &quot;Platform&quot;,
            &quot;tags&quot;: [
                &quot;Technical&quot;,
                &quot;Maintenance&quot;
            ],
            &quot;isTopNews&quot;: false,
            &quot;content&quot;: [
                {
                    &quot;type&quot;: &quot;text&quot;,
                    &quot;value&quot;: &quot;We will be updating our core security protocols to ensure your archive data remains encrypted.&quot;
                }
            ]
        },
        {
            &quot;id&quot;: 4,
            &quot;slug&quot;: &quot;the-rise-of-digital-cinemas-in-east-africa&quot;,
            &quot;title&quot;: &quot;The Rise of Digital Cinemas in East Africa&quot;,
            &quot;excerpt&quot;: &quot;Industry analysis on how streaming platforms are disrupting traditional theatre models.&quot;,
            &quot;author&quot;: &quot;Economic Hub&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1517604931442-7e0c8ed0963c?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;videoUrl&quot;: null,
            &quot;date&quot;: &quot;Nov 04, 2024&quot;,
            &quot;category&quot;: &quot;Industry&quot;,
            &quot;tags&quot;: [
                &quot;Business&quot;,
                &quot;Regional&quot;
            ],
            &quot;isTopNews&quot;: false,
            &quot;content&quot;: [
                {
                    &quot;type&quot;: &quot;text&quot;,
                    &quot;value&quot;: &quot;East Africa is witnessing a digital renaissance. Mobile money integration has made premium content accessible to everyone.&quot;
                }
            ]
        },
        {
            &quot;id&quot;: 3,
            &quot;slug&quot;: &quot;top-5-series-to-binge-this-weekend&quot;,
            &quot;title&quot;: &quot;Top 5 Series to Binge This Weekend&quot;,
            &quot;excerpt&quot;: &quot;From Concrete Jungle to Neon Shadows, here is your weekend archive survival guide.&quot;,
            &quot;author&quot;: &quot;Sarah J.&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;videoUrl&quot;: null,
            &quot;date&quot;: &quot;Nov 01, 2024&quot;,
            &quot;category&quot;: &quot;TV Shows&quot;,
            &quot;tags&quot;: [
                &quot;Curated&quot;,
                &quot;Binge-Watch&quot;
            ],
            &quot;isTopNews&quot;: false,
            &quot;content&quot;: [
                {
                    &quot;type&quot;: &quot;text&quot;,
                    &quot;value&quot;: &quot;The archive is expanding. Here are the top series currently trending in the Luganda Masters section.&quot;
                }
            ]
        },
        {
            &quot;id&quot;: 2,
            &quot;slug&quot;: &quot;inside-the-booth-vj-junior-on-translating-sci-fi&quot;,
            &quot;title&quot;: &quot;Inside the Booth: VJ Junior on Translating Sci-Fi&quot;,
            &quot;excerpt&quot;: &quot;The legend sits down to discuss the challenges of translating futuristic concepts into local dialect.&quot;,
            &quot;author&quot;: &quot;Mark S.&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;videoUrl&quot;: null,
            &quot;date&quot;: &quot;Oct 28, 2024&quot;,
            &quot;category&quot;: &quot;Industry&quot;,
            &quot;tags&quot;: [
                &quot;VJ Culture&quot;,
                &quot;Luganda&quot;,
                &quot;Masterclass&quot;
            ],
            &quot;isTopNews&quot;: true,
            &quot;content&quot;: [
                {
                    &quot;type&quot;: &quot;text&quot;,
                    &quot;value&quot;: &quot;VJ Junior has been the voice of cinema for over two decades. In our latest exclusive, he breaks down the process of creating \&quot;Cyber-Luganda\&quot; terms for movies like Cyber Enigma.&quot;
                }
            ]
        },
        {
            &quot;id&quot;: 1,
            &quot;slug&quot;: &quot;narabox-20-the-future-of-african-streaming-architecture&quot;,
            &quot;title&quot;: &quot;NaraBox 2.0: The Future of African Streaming Architecture&quot;,
            &quot;excerpt&quot;: &quot;We are rebuilding the core relay network to support 8K VR streams for VJ translated content.&quot;,
            &quot;author&quot;: &quot;Nara Editorial Team&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1485846234645-a62644f84728?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;date&quot;: &quot;Oct 24, 2024&quot;,
            &quot;category&quot;: &quot;Updates&quot;,
            &quot;tags&quot;: [
                &quot;Architecture&quot;,
                &quot;8K&quot;,
                &quot;V2.0&quot;
            ],
            &quot;isTopNews&quot;: true,
            &quot;content&quot;: [
                {
                    &quot;type&quot;: &quot;text&quot;,
                    &quot;value&quot;: &quot;Today marks a historic pivot for the NaraBox ecosystem. As we scale our narrative relay modules across the continent, the need for a more robust data pipeline has become critical.&quot;
                },
                {
                    &quot;type&quot;: &quot;quote&quot;,
                    &quot;value&quot;: &quot;Streaming isn&#039;t just about data; it&#039;s about the emotional latency between the VJ&#039;s voice and the viewer&#039;s heart.&quot;,
                    &quot;author&quot;: &quot;Chief Systems Architect&quot;
                },
                {
                    &quot;type&quot;: &quot;image&quot;,
                    &quot;value&quot;: &quot;https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&amp;h=600&amp;fit=crop&quot;,
                    &quot;caption&quot;: &quot;Technical visualization of the new 8K fiber optic nodes in Kampala.&quot;
                },
                {
                    &quot;type&quot;: &quot;text&quot;,
                    &quot;value&quot;: &quot;The new V2.0 architecture reduces latency by 45% while doubling the audio fidelity of our Luganda voice-overs.&quot;
                },
                {
                    &quot;type&quot;: &quot;gallery&quot;,
                    &quot;images&quot;: [
                        &quot;https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&amp;h=400&amp;fit=crop&quot;,
                        &quot;https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&amp;h=400&amp;fit=crop&quot;,
                        &quot;https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&amp;h=400&amp;fit=crop&quot;
                    ]
                }
            ]
        }
    ],
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 10,
        &quot;total&quot;: 5
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-articles" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-articles"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-articles"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-articles" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-articles">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-articles" data-method="GET"
      data-path="api/v1/articles"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-articles', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-articles"
                    onclick="tryItOut('GETapi-v1-articles');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-articles"
                    onclick="cancelTryOut('GETapi-v1-articles');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-articles"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/articles</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-articles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-articles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-articles"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="articles-GETapi-v1-articles--id-">GET api/v1/articles/{id}</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-articles--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/articles/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/articles/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-articles--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;slug&quot;: &quot;narabox-20-the-future-of-african-streaming-architecture&quot;,
    &quot;title&quot;: &quot;NaraBox 2.0: The Future of African Streaming Architecture&quot;,
    &quot;excerpt&quot;: &quot;We are rebuilding the core relay network to support 8K VR streams for VJ translated content.&quot;,
    &quot;author&quot;: &quot;Nara Editorial Team&quot;,
    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1485846234645-a62644f84728?w=1920&amp;h=1080&amp;fit=crop&quot;,
    &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
    &quot;date&quot;: &quot;Oct 24, 2024&quot;,
    &quot;category&quot;: &quot;Updates&quot;,
    &quot;tags&quot;: [
        &quot;8K&quot;,
        &quot;Architecture&quot;,
        &quot;V2.0&quot;
    ],
    &quot;isTopNews&quot;: true,
    &quot;content&quot;: [
        {
            &quot;type&quot;: &quot;text&quot;,
            &quot;value&quot;: &quot;Today marks a historic pivot for the NaraBox ecosystem. As we scale our narrative relay modules across the continent, the need for a more robust data pipeline has become critical.&quot;
        },
        {
            &quot;type&quot;: &quot;quote&quot;,
            &quot;value&quot;: &quot;Streaming isn&#039;t just about data; it&#039;s about the emotional latency between the VJ&#039;s voice and the viewer&#039;s heart.&quot;,
            &quot;author&quot;: &quot;Chief Systems Architect&quot;
        },
        {
            &quot;type&quot;: &quot;image&quot;,
            &quot;value&quot;: &quot;https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&amp;h=600&amp;fit=crop&quot;,
            &quot;caption&quot;: &quot;Technical visualization of the new 8K fiber optic nodes in Kampala.&quot;
        },
        {
            &quot;type&quot;: &quot;text&quot;,
            &quot;value&quot;: &quot;The new V2.0 architecture reduces latency by 45% while doubling the audio fidelity of our Luganda voice-overs.&quot;
        },
        {
            &quot;type&quot;: &quot;gallery&quot;,
            &quot;images&quot;: [
                &quot;https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&amp;h=400&amp;fit=crop&quot;,
                &quot;https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&amp;h=400&amp;fit=crop&quot;,
                &quot;https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&amp;h=400&amp;fit=crop&quot;
            ]
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-articles--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-articles--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-articles--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-articles--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-articles--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-articles--id-" data-method="GET"
      data-path="api/v1/articles/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-articles--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-articles--id-"
                    onclick="tryItOut('GETapi-v1-articles--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-articles--id-"
                    onclick="cancelTryOut('GETapi-v1-articles--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-articles--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/articles/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-articles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-articles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-articles--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-v1-articles--id-"
               value="1"
               data-component="url">
    <br>
<p>The ID of the article. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="contact">Contact</h1>

    <p>Submit contact form (name, email, subject, message). No auth.</p>

                                <h2 id="contact-POSTapi-v1-contact">Submit contact form. Body: name, email, subject, message.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-contact">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/contact" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"name\": \"b\",
    \"email\": \"zbailey@example.net\",
    \"subject\": \"i\",
    \"message\": \"y\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/contact"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "name": "b",
    "email": "zbailey@example.net",
    "subject": "i",
    "message": "y"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-contact">
</span>
<span id="execution-results-POSTapi-v1-contact" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-contact"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-contact"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-contact" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-contact">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-contact" data-method="POST"
      data-path="api/v1/contact"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-contact', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-contact"
                    onclick="tryItOut('POSTapi-v1-contact');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-contact"
                    onclick="cancelTryOut('POSTapi-v1-contact');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-contact"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/contact</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-contact"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-contact"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-contact"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-contact"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-contact"
               value="zbailey@example.net"
               data-component="body">
    <br>
<p>Must be a valid email address. Must not be greater than 255 characters. Example: <code>zbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>subject</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="subject"                data-endpoint="POSTapi-v1-contact"
               value="i"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>i</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>message</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="message"                data-endpoint="POSTapi-v1-contact"
               value="y"
               data-component="body">
    <br>
<p>Must not be greater than 5000 characters. Example: <code>y</code></p>
        </div>
        </form>

                <h1 id="live-streams">Live Streams</h1>

    <p>Live channels. List with optional type=live|archived; get by id.</p>

                                <h2 id="live-streams-GETapi-v1-live-streams">Get active live streams. Query: type (live|archived).</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-live-streams">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/live-streams" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/live-streams"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-live-streams">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-live-streams" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-live-streams"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-live-streams"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-live-streams" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-live-streams">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-live-streams" data-method="GET"
      data-path="api/v1/live-streams"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-live-streams', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-live-streams"
                    onclick="tryItOut('GETapi-v1-live-streams');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-live-streams"
                    onclick="cancelTryOut('GETapi-v1-live-streams');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-live-streams"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/live-streams</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-live-streams"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-live-streams"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-live-streams"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="live-streams-GETapi-v1-live-streams--id-">Get a specific live stream</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-live-streams--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/live-streams/16" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/live-streams/16"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-live-streams--id-">
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Models\\LiveStream] 16&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-live-streams--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-live-streams--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-live-streams--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-live-streams--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-live-streams--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-live-streams--id-" data-method="GET"
      data-path="api/v1/live-streams/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-live-streams--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-live-streams--id-"
                    onclick="tryItOut('GETapi-v1-live-streams--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-live-streams--id-"
                    onclick="cancelTryOut('GETapi-v1-live-streams--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-live-streams--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/live-streams/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-live-streams--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-live-streams--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-live-streams--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-v1-live-streams--id-"
               value="16"
               data-component="url">
    <br>
<p>The ID of the live stream. Example: <code>16</code></p>
            </div>
                    </form>

                <h1 id="actors">Actors</h1>

    <p>Cast. List with search/trending; get by id or slug with movies.</p>

                                <h2 id="actors-GETapi-v1-actors">List actors. Query: search, trending (1), per_page.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-actors">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/actors" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/actors"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-actors">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 96,
            &quot;name&quot;: &quot;Misha Collins&quot;,
            &quot;slug&quot;: &quot;misha-collins&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_05718096e14f828cd9a69e83fe6b4148.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 95,
            &quot;name&quot;: &quot;Jensen Ackles&quot;,
            &quot;slug&quot;: &quot;jensen-ackles&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_0409aad854c4e103b5034d15fc529c05.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 94,
            &quot;name&quot;: &quot;Jared Padalecki&quot;,
            &quot;slug&quot;: &quot;jared-padalecki&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_6e8096be9df5f4dddc8e69d4d78a49ad.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 93,
            &quot;name&quot;: &quot;Nell Fisher&quot;,
            &quot;slug&quot;: &quot;nell-fisher&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4e89f2a830b3a5acce09b06d0d4b19dc.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 92,
            &quot;name&quot;: &quot;Linda Hamilton&quot;,
            &quot;slug&quot;: &quot;linda-hamilton&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_15909a19c24eee8c0f53f576bd9e1fdf.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 91,
            &quot;name&quot;: &quot;Jamie Campbell Bower&quot;,
            &quot;slug&quot;: &quot;jamie-campbell-bower&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_4d08bc9ef20c3f3e1e9c25e874898015.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 90,
            &quot;name&quot;: &quot;Brett Gelman&quot;,
            &quot;slug&quot;: &quot;brett-gelman&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3102e3d8d5bddb1e0e70c5474d5d97e0.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 89,
            &quot;name&quot;: &quot;Priah Ferguson&quot;,
            &quot;slug&quot;: &quot;priah-ferguson&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_38498bfe42d8b2473333b7ba8474aec8.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 88,
            &quot;name&quot;: &quot;Cara Buono&quot;,
            &quot;slug&quot;: &quot;cara-buono&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_37ffad63fc52b119bcb71c9a4b3b0e91.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 87,
            &quot;name&quot;: &quot;Maya Hawke&quot;,
            &quot;slug&quot;: &quot;maya-hawke&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_9b81fbd6db7177ef8214d17dcb8e0a1b.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 86,
            &quot;name&quot;: &quot;Joe Keery&quot;,
            &quot;slug&quot;: &quot;joe-keery&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3effa5927187ac83c18f9be1bb4aa608.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 85,
            &quot;name&quot;: &quot;Natalia Dyer&quot;,
            &quot;slug&quot;: &quot;natalia-dyer&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_8e7a4486f7fcd19d455109f3d421111c.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 84,
            &quot;name&quot;: &quot;Charlie Heaton&quot;,
            &quot;slug&quot;: &quot;charlie-heaton&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_92ae32e506cd2527e95d0847d372bf50.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 83,
            &quot;name&quot;: &quot;Sadie Sink&quot;,
            &quot;slug&quot;: &quot;sadie-sink&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_6402a1f20e68d274ba4a1bd1e6b26de1.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 82,
            &quot;name&quot;: &quot;Noah Schnapp&quot;,
            &quot;slug&quot;: &quot;noah-schnapp&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_3267efae461f1539ae11ae65aaf1748e.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 81,
            &quot;name&quot;: &quot;Caleb McLaughlin&quot;,
            &quot;slug&quot;: &quot;caleb-mclaughlin&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_2ca79b257871c56a16cba3acf91f6b74.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 80,
            &quot;name&quot;: &quot;Gaten Matarazzo&quot;,
            &quot;slug&quot;: &quot;gaten-matarazzo&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_5c57d3372a77f036a6a867fbf72f6c10.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 79,
            &quot;name&quot;: &quot;Finn Wolfhard&quot;,
            &quot;slug&quot;: &quot;finn-wolfhard&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_e90f9df98183b8f53e4d55eab0ddfe52.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 78,
            &quot;name&quot;: &quot;David Harbour&quot;,
            &quot;slug&quot;: &quot;david-harbour&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_aa47014f57653805580488d1bea5666a.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        },
        {
            &quot;id&quot;: 77,
            &quot;name&quot;: &quot;Winona Ryder&quot;,
            &quot;slug&quot;: &quot;winona-ryder&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_0ea637a3afaf067d551155817619b800.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 0
        }
    ],
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 5,
        &quot;per_page&quot;: 20,
        &quot;total&quot;: 96
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-actors" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-actors"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-actors"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-actors" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-actors">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-actors" data-method="GET"
      data-path="api/v1/actors"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-actors', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-actors"
                    onclick="tryItOut('GETapi-v1-actors');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-actors"
                    onclick="cancelTryOut('GETapi-v1-actors');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-actors"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/actors</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-actors"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-actors"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-actors"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="actors-GETapi-v1-actors-trending">GET api/v1/actors/trending</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-actors-trending">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/actors/trending" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/actors/trending"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-actors-trending">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Tom Hardy&quot;,
            &quot;slug&quot;: &quot;tom-hardy&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;bio&quot;: &quot;Acclaimed actor known for intense performances&quot;,
            &quot;moviesCount&quot;: 22
        },
        {
            &quot;id&quot;: 2,
            &quot;name&quot;: &quot;Zendaya&quot;,
            &quot;slug&quot;: &quot;zendaya&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;bio&quot;: &quot;Rising star in modern cinema&quot;,
            &quot;moviesCount&quot;: 21
        },
        {
            &quot;id&quot;: 4,
            &quot;name&quot;: &quot;Florence Pugh&quot;,
            &quot;slug&quot;: &quot;florence-pugh&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;bio&quot;: &quot;Award-winning actress&quot;,
            &quot;moviesCount&quot;: 21
        },
        {
            &quot;id&quot;: 5,
            &quot;name&quot;: &quot;Austin Butler&quot;,
            &quot;slug&quot;: &quot;austin-butler&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;bio&quot;: &quot;Talented performer&quot;,
            &quot;moviesCount&quot;: 21
        },
        {
            &quot;id&quot;: 3,
            &quot;name&quot;: &quot;Cillian Murphy&quot;,
            &quot;slug&quot;: &quot;cillian-murphy&quot;,
            &quot;image&quot;: &quot;https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&amp;h=400&amp;fit=crop&quot;,
            &quot;bio&quot;: &quot;Versatile actor with remarkable range&quot;,
            &quot;moviesCount&quot;: 20
        },
        {
            &quot;id&quot;: 6,
            &quot;name&quot;: &quot;Glen Powell&quot;,
            &quot;slug&quot;: &quot;glen-powell&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_dc0231283b20ba9137b89911aa3a86b1.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 1
        },
        {
            &quot;id&quot;: 7,
            &quot;name&quot;: &quot;Josh Brolin&quot;,
            &quot;slug&quot;: &quot;josh-brolin&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_d56240ed42b0004e0580f363b5ba76a6.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 1
        },
        {
            &quot;id&quot;: 8,
            &quot;name&quot;: &quot;Colman Domingo&quot;,
            &quot;slug&quot;: &quot;colman-domingo&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_28483513c305c919bf1bcf698f90d313.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 1
        },
        {
            &quot;id&quot;: 9,
            &quot;name&quot;: &quot;Lee Pace&quot;,
            &quot;slug&quot;: &quot;lee-pace&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_1f4b4e2c85c7872f783cdbdacf6350d2.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 1
        },
        {
            &quot;id&quot;: 10,
            &quot;name&quot;: &quot;Michael Cera&quot;,
            &quot;slug&quot;: &quot;michael-cera&quot;,
            &quot;image&quot;: &quot;http://127.0.0.1:8000/storage/tmdb/actors/tmdb_8a587cec8890c44293a70b16083b15ef.jpg&quot;,
            &quot;bio&quot;: null,
            &quot;moviesCount&quot;: 1
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-actors-trending" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-actors-trending"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-actors-trending"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-actors-trending" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-actors-trending">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-actors-trending" data-method="GET"
      data-path="api/v1/actors/trending"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-actors-trending', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-actors-trending"
                    onclick="tryItOut('GETapi-v1-actors-trending');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-actors-trending"
                    onclick="cancelTryOut('GETapi-v1-actors-trending');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-actors-trending"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/actors/trending</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-actors-trending"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-actors-trending"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-actors-trending"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="actors-GETapi-v1-actors--id-">GET api/v1/actors/{id}</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-actors--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/actors/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/actors/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-actors--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;name&quot;: &quot;Tom Hardy&quot;,
    &quot;slug&quot;: &quot;tom-hardy&quot;,
    &quot;image&quot;: &quot;https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&amp;h=600&amp;fit=crop&quot;,
    &quot;bio&quot;: &quot;Acclaimed actor known for intense performances&quot;,
    &quot;moviesCount&quot;: 22,
    &quot;movies&quot;: [
        {
            &quot;id&quot;: 17,
            &quot;title&quot;: &quot;Void Walkers&quot;,
            &quot;description&quot;: &quot;Interdimensional agents.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 9,
            &quot;releaseDate&quot;: &quot;2024-01-20&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 18,
            &quot;title&quot;: &quot;Bloodlines&quot;,
            &quot;description&quot;: &quot;Family feuds in history.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.4,
            &quot;releaseDate&quot;: &quot;2023-09-15&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Drama&quot;,
                &quot;History&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 19,
            &quot;title&quot;: &quot;Neon Shadows&quot;,
            &quot;description&quot;: &quot;Cyber detective stories.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.7,
            &quot;releaseDate&quot;: &quot;2024-03-10&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: &quot;VJ Mark&quot;,
            &quot;genre&quot;: [
                &quot;Crime&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 20,
            &quot;title&quot;: &quot;The Last Oracle&quot;,
            &quot;description&quot;: &quot;Fantasy epic.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1514539079130-25950c84af65?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1514539079130-25950c84af65?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 9.5,
            &quot;releaseDate&quot;: &quot;2024-07-25&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Adventure&quot;,
                &quot;Fantasy&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 21,
            &quot;title&quot;: &quot;Concrete Jungle&quot;,
            &quot;description&quot;: &quot;Modern day survival.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.6,
            &quot;releaseDate&quot;: &quot;2024-02-05&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Drama&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 23,
            &quot;title&quot;: &quot;Stellar Wind&quot;,
            &quot;description&quot;: &quot;Space colonies.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.8,
            &quot;releaseDate&quot;: &quot;2023-12-01&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 24,
            &quot;title&quot;: &quot;Urban Legend&quot;,
            &quot;description&quot;: &quot;Modern myths.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.9,
            &quot;releaseDate&quot;: &quot;2024-10-12&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: &quot;VJ Mark&quot;,
            &quot;genre&quot;: [
                &quot;Horror&quot;,
                &quot;Mystery&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 16,
            &quot;title&quot;: &quot;Kampala Nights: Series&quot;,
            &quot;description&quot;: &quot;Weekly racing drama.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.5,
            &quot;releaseDate&quot;: &quot;2024-05-15&quot;,
            &quot;category&quot;: &quot;VJ Translated&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Crime&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 22,
            &quot;title&quot;: &quot;Pulse Rate&quot;,
            &quot;description&quot;: &quot;Medical thriller.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.1,
            &quot;releaseDate&quot;: &quot;2024-08-18&quot;,
            &quot;category&quot;: &quot;Series&quot;,
            &quot;mediaType&quot;: &quot;SERIES&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Drama&quot;,
                &quot;Medical&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 5,
            &quot;title&quot;: &quot;The Iron Fist&quot;,
            &quot;description&quot;: &quot;Martial arts drama.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1552072092-7f9b8d63efcb?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1552072092-7f9b8d63efcb?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.2,
            &quot;releaseDate&quot;: &quot;2024-01-05&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 7,
            &quot;title&quot;: &quot;Neon Pulse&quot;,
            &quot;description&quot;: &quot;Cyberpunk music.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.5,
            &quot;releaseDate&quot;: &quot;2023-11-20&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Music&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 11,
            &quot;title&quot;: &quot;Desert Storm&quot;,
            &quot;description&quot;: &quot;War epic.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1509062522246-3755977927d7?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1509062522246-3755977927d7?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 9.3,
            &quot;releaseDate&quot;: &quot;2024-06-22&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;History&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 12,
            &quot;title&quot;: &quot;The Last Stand&quot;,
            &quot;description&quot;: &quot;Western showdown.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.1,
            &quot;releaseDate&quot;: &quot;2024-07-04&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Western&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 13,
            &quot;title&quot;: &quot;Quantum Drift&quot;,
            &quot;description&quot;: &quot;Time travel racing.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.6,
            &quot;releaseDate&quot;: &quot;2024-08-11&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Racing&quot;,
                &quot;Sci-Fi&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 2,
            &quot;title&quot;: &quot;Kampala Nights&quot;,
            &quot;description&quot;: &quot;High stakes, high speed, and local dialect.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.5,
            &quot;releaseDate&quot;: &quot;2024-05-15&quot;,
            &quot;category&quot;: &quot;VJ Translated&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Crime&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 8,
            &quot;title&quot;: &quot;Ancient Echoes&quot;,
            &quot;description&quot;: &quot;When a team of archaeologists discovers a lost civilization buried beneath the desert sands.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.9,
            &quot;releaseDate&quot;: &quot;2024-09-01&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Junior&quot;,
            &quot;genre&quot;: [
                &quot;Adventure&quot;,
                &quot;Mystery&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;2h 32m&quot;,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 9,
            &quot;title&quot;: &quot;Deep Frost&quot;,
            &quot;description&quot;: &quot;Arctic survival.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1517783999520-f068d7431a60?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1517783999520-f068d7431a60?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.4,
            &quot;releaseDate&quot;: &quot;2024-03-30&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 10,
            &quot;title&quot;: &quot;Midnight Rain&quot;,
            &quot;description&quot;: &quot;Film noir.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1485846234645-a62644f84728?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1485846234645-a62644f84728?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.8,
            &quot;releaseDate&quot;: &quot;2023-10-15&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Emmy&quot;,
            &quot;genre&quot;: [
                &quot;Crime&quot;,
                &quot;Drama&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 14,
            &quot;title&quot;: &quot;Siren Call&quot;,
            &quot;description&quot;: &quot;Ocean mystery.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.2,
            &quot;releaseDate&quot;: &quot;2024-10-31&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Horror&quot;,
                &quot;Mystery&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 3,
            &quot;title&quot;: &quot;Cyber Enigma&quot;,
            &quot;description&quot;: &quot;In a world where digital consciousness has become reality, a rogue AI threatens to merge human minds with the virtual realm.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 7.9,
            &quot;releaseDate&quot;: &quot;2024-08-20&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Jingo&quot;,
            &quot;genre&quot;: [
                &quot;Sci-Fi&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;2h 15m&quot;,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 4,
            &quot;title&quot;: &quot;Lost in the Rift&quot;,
            &quot;description&quot;: &quot;Space exploration.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 9.1,
            &quot;releaseDate&quot;: &quot;2023-12-10&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: null,
            &quot;genre&quot;: [
                &quot;Adventure&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: null,
            &quot;role&quot;: &quot;Character 1&quot;
        },
        {
            &quot;id&quot;: 6,
            &quot;title&quot;: &quot;Shadow Walker&quot;,
            &quot;description&quot;: &quot;A master ninja emerges from the shadows to protect a secret that could change the world.&quot;,
            &quot;thumbnail&quot;: &quot;https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=400&amp;h=600&amp;fit=crop&quot;,
            &quot;backdrop&quot;: &quot;https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=1920&amp;h=1080&amp;fit=crop&quot;,
            &quot;rating&quot;: 8.7,
            &quot;releaseDate&quot;: &quot;2024-02-14&quot;,
            &quot;category&quot;: &quot;Movie&quot;,
            &quot;mediaType&quot;: &quot;MOVIE&quot;,
            &quot;vj&quot;: &quot;VJ Mark&quot;,
            &quot;genre&quot;: [
                &quot;Action&quot;,
                &quot;Thriller&quot;
            ],
            &quot;trendingScore&quot;: 0,
            &quot;accessType&quot;: null,
            &quot;videoUrl&quot;: &quot;https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4&quot;,
            &quot;duration&quot;: &quot;1h 58m&quot;,
            &quot;role&quot;: &quot;Character 1&quot;
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-actors--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-actors--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-actors--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-actors--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-actors--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-actors--id-" data-method="GET"
      data-path="api/v1/actors/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-actors--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-actors--id-"
                    onclick="tryItOut('GETapi-v1-actors--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-actors--id-"
                    onclick="cancelTryOut('GETapi-v1-actors--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-actors--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/actors/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-actors--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-actors--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-actors--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-v1-actors--id-"
               value="1"
               data-component="url">
    <br>
<p>The ID of the actor. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="player-downloads">Player & Downloads</h1>

    <p>Playback manifest (video URLs, HLS, subtitles, download sources). Optional auth for premium/rent/buy.</p>

                                <h2 id="player-downloads-GETapi-v1-player--id-">Get playback manifest for a movie or TV show episode</h2>

<p>
</p>

<p>Resolve the best video source, subtitles, and downloads for a movie or TV episode.
This powers the watch page player.</p>
<p>Pass <code>media_type=MOVIE</code> for movies (default) or <code>media_type=TV_SHOW</code> for series.
For TV shows, also pass the <code>episode</code> query param with the episode ID.</p>
<p>Access rules:</p>
<ul>
<li>Free content (<code>is_free = 1</code>) is always playable (no auth required).</li>
<li>Premium content (<code>is_premium = 1</code>) requires an active subscription.</li>
<li>Paid content (<code>price_rent</code>/<code>price_buy</code>) requires a successful rent or purchase.</li>
</ul>
<p>On success, returns:</p>
<ul>
<li><code>movie</code>: minimal media metadata (id, title, images, download_enabled)</li>
<li><code>episode</code>: minimal episode metadata when applicable</li>
<li><code>videoUrl</code>: primary play URL (HLS master or MP4)</li>
<li><code>videoSources</code>: all available qualities and formats</li>
<li><code>subtitles</code>: all available subtitle tracks</li>
<li><code>duration</code>: duration in seconds (best-effort)</li>
<li><code>poster</code>: poster/backdrop URL for the player</li>
<li><code>downloadSources</code>: downloadable variants (if enabled and user has access)</li>
<li><code>playback</code>: normalized playback manifest (<code>type</code>, <code>url</code>, <code>hls_master_url</code>, <code>mp4_play_url</code>, <code>qualities</code>, etc.)</li>
</ul>

<span id="example-requests-GETapi-v1-player--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/player/1?media_type=MOVIE&amp;episode=419" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/player/1"
);

const params = {
    "media_type": "MOVIE",
    "episode": "419",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-player--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;movie&quot;: {
        &quot;id&quot;: 1,
        &quot;title&quot;: &quot;Zootopia 2&quot;,
        &quot;thumbnail&quot;: &quot;https://portal.naraboxtv.com/storage/tmdb/posters/tmdb_f158459be819affd8c9f257f75a49f15.jpg&quot;,
        &quot;backdrop&quot;: &quot;https://portal.naraboxtv.com/storage/tmdb/backdrops/tmdb_7ee2bf6a30418364a91d7d4cffb592d0.jpg&quot;,
        &quot;download_enabled&quot;: true
    },
    &quot;episode&quot;: null,
    &quot;videoUrl&quot;: &quot;https://cdn.narabox.example/hls/master.m3u8&quot;,
    &quot;videoSources&quot;: [
        {
            &quot;id&quot;: 101,
            &quot;url&quot;: &quot;https://cdn.narabox.example/hls/master.m3u8&quot;,
            &quot;quality&quot;: &quot;auto&quot;,
            &quot;format&quot;: &quot;hls&quot;,
            &quot;type&quot;: &quot;fetched&quot;,
            &quot;isPrimary&quot;: true,
            &quot;duration&quot;: 6420
        }
    ],
    &quot;subtitles&quot;: [
        {
            &quot;id&quot;: 12,
            &quot;src&quot;: &quot;https://portal.naraboxtv.com/storage/subtitles/zootopia2-en.vtt&quot;,
            &quot;label&quot;: &quot;English&quot;,
            &quot;language&quot;: &quot;en&quot;,
            &quot;kind&quot;: &quot;subtitles&quot;,
            &quot;default&quot;: true,
            &quot;format&quot;: &quot;vtt&quot;
        }
    ],
    &quot;duration&quot;: 6420,
    &quot;poster&quot;: &quot;https://portal.naraboxtv.com/storage/tmdb/backdrops/tmdb_7ee2bf6a30418364a91d7d4cffb592d0.jpg&quot;,
    &quot;downloadSources&quot;: [
        {
            &quot;id&quot;: 55,
            &quot;type&quot;: &quot;fetched&quot;,
            &quot;quality&quot;: &quot;1080p&quot;,
            &quot;format&quot;: &quot;mp4&quot;,
            &quot;label&quot;: &quot;1080p MP4&quot;,
            &quot;url&quot;: &quot;https://portal.naraboxtv.com/api/v1/downloads/55&quot;,
            &quot;download_url&quot;: &quot;https://portal.naraboxtv.com/api/v1/downloads/55&quot;,
            &quot;file_size&quot;: 2147483648,
            &quot;source_url&quot;: null,
            &quot;file_path&quot;: &quot;downloads/movies/zootopia2-1080p.mp4&quot;
        }
    ],
    &quot;playback&quot;: {
        &quot;type&quot;: &quot;hls&quot;,
        &quot;url&quot;: &quot;https://cdn.narabox.example/hls/master.m3u8&quot;,
        &quot;hls_master_url&quot;: &quot;https://cdn.narabox.example/hls/master.m3u8&quot;,
        &quot;mp4_play_url&quot;: &quot;https://cdn.narabox.example/mp4/zootopia2-1080p.mp4&quot;,
        &quot;mp4_url&quot;: &quot;https://cdn.narabox.example/mp4/zootopia2-1080p.mp4&quot;,
        &quot;download_url&quot;: &quot;https://portal.naraboxtv.com/api/v1/downloads/55&quot;,
        &quot;sources&quot;: [
            {
                &quot;id&quot;: &quot;cdn-auto&quot;,
                &quot;url&quot;: &quot;https://cdn.narabox.example/hls/master.m3u8&quot;,
                &quot;quality&quot;: &quot;auto&quot;,
                &quot;format&quot;: &quot;hls&quot;,
                &quot;type&quot;: &quot;fetched&quot;,
                &quot;isPrimary&quot;: true,
                &quot;duration&quot;: null
            }
        ],
        &quot;subtitles&quot;: [
            {
                &quot;id&quot;: 12,
                &quot;src&quot;: &quot;https://portal.naraboxtv.com/storage/subtitles/zootopia2-en.vtt&quot;,
                &quot;label&quot;: &quot;English&quot;,
                &quot;language&quot;: &quot;en&quot;,
                &quot;kind&quot;: &quot;subtitles&quot;,
                &quot;default&quot;: true,
                &quot;format&quot;: &quot;vtt&quot;
            }
        ],
        &quot;qualities&quot;: [
            {
                &quot;id&quot;: &quot;auto&quot;,
                &quot;label&quot;: &quot;AUTO&quot;,
                &quot;url&quot;: &quot;https://cdn.narabox.example/hls/master.m3u8&quot;,
                &quot;bandwidth&quot;: 4500000,
                &quot;width&quot;: 1920,
                &quot;height&quot;: 1080
            }
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (403):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;This content requires a premium subscription. Subscribe to access premium content.&quot;,
    &quot;message&quot;: &quot;This content requires a premium subscription. Subscribe to access premium content.&quot;,
    &quot;has_access&quot;: false,
    &quot;reason&quot;: &quot;This content requires a premium subscription. Subscribe to access premium content.&quot;,
    &quot;requires_payment&quot;: false,
    &quot;requires_subscription&quot;: true,
    &quot;requires_auth&quot;: true,
    &quot;access_type&quot;: &quot;PREMIUM&quot;,
    &quot;can_rent&quot;: true,
    &quot;can_buy&quot;: true,
    &quot;rent_price&quot;: 1000,
    &quot;buy_price&quot;: 2200,
    &quot;is_free&quot;: false,
    &quot;is_premium&quot;: true,
    &quot;pending_payment&quot;: false,
    &quot;transaction_ref&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Media not found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-player--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-player--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-player--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-player--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-player--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-player--id-" data-method="GET"
      data-path="api/v1/player/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-player--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-player--id-"
                    onclick="tryItOut('GETapi-v1-player--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-player--id-"
                    onclick="cancelTryOut('GETapi-v1-player--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-player--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/player/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-player--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-player--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-player--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-player--id-"
               value="1"
               data-component="url">
    <br>
<p>The movie or TV show identifier (numeric <code>id</code> or <code>slug</code>). Example: <code>1</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>media_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_type"                data-endpoint="GETapi-v1-player--id-"
               value="MOVIE"
               data-component="query">
    <br>
<p>MOVIE or TV_SHOW. Defaults to MOVIE. Example: <code>MOVIE</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>episode</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="episode"                data-endpoint="GETapi-v1-player--id-"
               value="419"
               data-component="query">
    <br>
<p>The <code>id</code> of the episode when <code>media_type</code> is TV_SHOW. Example: <code>419</code></p>
            </div>
                </form>

                    <h2 id="player-downloads-GETapi-v1-downloads--id-">Download file. id = download source id. Auth optional for free content; required for paid. Supports ?access_token= for direct links.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-downloads--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/downloads/architecto" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/downloads/architecto"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-downloads--id-">
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Models\\DownloadSource] architecto&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-downloads--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-downloads--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-downloads--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-downloads--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-downloads--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-downloads--id-" data-method="GET"
      data-path="api/v1/downloads/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-downloads--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-downloads--id-"
                    onclick="tryItOut('GETapi-v1-downloads--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-downloads--id-"
                    onclick="cancelTryOut('GETapi-v1-downloads--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-downloads--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/downloads/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-downloads--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-downloads--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-downloads--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-downloads--id-"
               value="architecto"
               data-component="url">
    <br>
<p>The ID of the download. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="player-downloads-POSTapi-v1-watch-history">Update watch history for the current user</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Called from the player to persist progress for a given movie (and optional episode).
The most recent entry per <code>(user_id, media_id, episode_id)</code> is kept up to date.</p>

<span id="example-requests-POSTapi-v1-watch-history">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/watch-history" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"media_id\": 1,
    \"episode_id\": 419,
    \"progress_seconds\": 1200,
    \"total_seconds\": 6420
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/watch-history"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "media_id": 1,
    "episode_id": 419,
    "progress_seconds": 1200,
    "total_seconds": 6420
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-watch-history">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-watch-history" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-watch-history"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-watch-history"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-watch-history" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-watch-history">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-watch-history" data-method="POST"
      data-path="api/v1/watch-history"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-watch-history', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-watch-history"
                    onclick="tryItOut('POSTapi-v1-watch-history');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-watch-history"
                    onclick="cancelTryOut('POSTapi-v1-watch-history');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-watch-history"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/watch-history</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-watch-history"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-watch-history"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-watch-history"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-watch-history"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="media_id"                data-endpoint="POSTapi-v1-watch-history"
               value="1"
               data-component="body">
    <br>
<p>The <code>id</code> of the movie being watched. Must exist in the <code>movies</code> table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>episode_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="episode_id"                data-endpoint="POSTapi-v1-watch-history"
               value="419"
               data-component="body">
    <br>
<p>nullable The <code>id</code> of the episode when watching a TV show episode. Example: <code>419</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>progress_seconds</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="progress_seconds"                data-endpoint="POSTapi-v1-watch-history"
               value="1200"
               data-component="body">
    <br>
<p>Current playhead position in seconds. Minimum: 0. Example: <code>1200</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>total_seconds</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="total_seconds"                data-endpoint="POSTapi-v1-watch-history"
               value="6420"
               data-component="body">
    <br>
<p>nullable Total duration in seconds (if known). Minimum: 0. Example: <code>6420</code></p>
        </div>
        </form>

                    <h2 id="player-downloads-GETapi-v1-watch-history">Get watch history for the current user</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Returns a flat list of recent watch sessions ordered by <code>lastWatched</code> (most recent first).
Each item references a movie and optionally an episode.</p>

<span id="example-requests-GETapi-v1-watch-history">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/watch-history" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/watch-history"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-watch-history">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 10,
            &quot;mediaId&quot;: 1,
            &quot;episodeId&quot;: null,
            &quot;progressSeconds&quot;: 1200,
            &quot;totalSeconds&quot;: 6420,
            &quot;lastWatched&quot;: &quot;2026-03-10T20:15:30+03:00&quot;
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-watch-history" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-watch-history"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-watch-history"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-watch-history" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-watch-history">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-watch-history" data-method="GET"
      data-path="api/v1/watch-history"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-watch-history', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-watch-history"
                    onclick="tryItOut('GETapi-v1-watch-history');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-watch-history"
                    onclick="cancelTryOut('GETapi-v1-watch-history');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-watch-history"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/watch-history</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-watch-history"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-watch-history"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-watch-history"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-watch-history"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                <h1 id="access-views">Access & Views</h1>

    <p>Check access to content (free/subscription/rent/buy); track views.</p>

                                <h2 id="access-views-POSTapi-v1-access-check">Check if user has access to a movie or TV show</h2>

<p>
</p>

<p>Frontend helper to decide whether to show <strong>Play</strong>, <strong>Rent</strong>, <strong>Buy</strong>, <strong>Subscribe</strong>, or a locked state.</p>
<p>Rules:</p>
<ul>
<li>Free content (<code>is_free = 1</code>) is always accessible (no auth required).</li>
<li>Premium content (<code>is_premium = 1</code>) requires an active subscription.</li>
<li>Paid content with <code>price_rent</code> / <code>price_buy</code> checks rentals and purchases.</li>
<li>Pending transactions are surfaced so the UI can show “Pending approval”.</li>
</ul>
<p>Returns a normalized <code>access_type</code>:</p>
<ul>
<li><code>FREE</code></li>
<li><code>SUBSCRIPTION</code></li>
<li><code>PREMIUM</code> (subscription required, but none active)</li>
<li><code>PURCHASED</code></li>
<li><code>RENTED</code></li>
<li><code>PENDING</code></li>
<li><code>PAID</code> (payment required: rent and/or buy)</li>
</ul>

<span id="example-requests-POSTapi-v1-access-check">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/access/check" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"media_id\": 1,
    \"media_type\": \"MOVIE\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/access/check"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "media_id": 1,
    "media_type": "MOVIE"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-access-check">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;has_access&quot;: true,
    &quot;access_type&quot;: &quot;FREE&quot;,
    &quot;reason&quot;: &quot;Content is free&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Premium with active subscription):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;has_access&quot;: true,
    &quot;access_type&quot;: &quot;SUBSCRIPTION&quot;,
    &quot;reason&quot;: &quot;You have an active subscription&quot;,
    &quot;subscription_expires_at&quot;: &quot;2026-03-31T20:00:00+03:00&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Paid but not yet rented/bought):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;has_access&quot;: false,
    &quot;access_type&quot;: &quot;PAID&quot;,
    &quot;reason&quot;: &quot;Payment required&quot;,
    &quot;can_rent&quot;: true,
    &quot;can_buy&quot;: true,
    &quot;rent_price&quot;: 1000,
    &quot;buy_price&quot;: 2200
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;has_access&quot;: false,
    &quot;access_type&quot;: null,
    &quot;reason&quot;: &quot;Authentication required&quot;,
    &quot;requires_auth&quot;: true
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Media not found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-access-check" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-access-check"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-access-check"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-access-check" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-access-check">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-access-check" data-method="POST"
      data-path="api/v1/access/check"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-access-check', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-access-check"
                    onclick="tryItOut('POSTapi-v1-access-check');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-access-check"
                    onclick="cancelTryOut('POSTapi-v1-access-check');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-access-check"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/access/check</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-access-check"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-access-check"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-access-check"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="media_id"                data-endpoint="POSTapi-v1-access-check"
               value="1"
               data-component="body">
    <br>
<p>The <code>id</code> of the movie or TV show to check. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_type"                data-endpoint="POSTapi-v1-access-check"
               value="MOVIE"
               data-component="body">
    <br>
<p>Must be <code>MOVIE</code> or <code>TV_SHOW</code>. Example: <code>MOVIE</code></p>
        </div>
        </form>

                    <h2 id="access-views-POSTapi-v1-views-track">Track a view. Body: media_id, media_type (MOVIE|TV_SHOW).</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-views-track">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/views/track" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"media_id\": 16,
    \"media_type\": \"MOVIE\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/views/track"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "media_id": 16,
    "media_type": "MOVIE"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-views-track">
</span>
<span id="execution-results-POSTapi-v1-views-track" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-views-track"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-views-track"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-views-track" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-views-track">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-views-track" data-method="POST"
      data-path="api/v1/views/track"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-views-track', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-views-track"
                    onclick="tryItOut('POSTapi-v1-views-track');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-views-track"
                    onclick="cancelTryOut('POSTapi-v1-views-track');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-views-track"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/views/track</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-views-track"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-views-track"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-views-track"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="media_id"                data-endpoint="POSTapi-v1-views-track"
               value="16"
               data-component="body">
    <br>
<p>Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_type"                data-endpoint="POSTapi-v1-views-track"
               value="MOVIE"
               data-component="body">
    <br>
<p>Example: <code>MOVIE</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>MOVIE</code></li> <li><code>TV_SHOW</code></li></ul>
        </div>
        </form>

                <h1 id="subscription-plans">Subscription plans</h1>

    <p>Public list of subscription plans (no auth required).</p>

                                <h2 id="subscription-plans-GETapi-v1-subscription-plans">Get all active subscription plans</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-subscription-plans">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/subscription-plans" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/subscription-plans"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-subscription-plans">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Daily Access&quot;,
        &quot;slug&quot;: &quot;daily-access&quot;,
        &quot;description&quot;: &quot;24-hour access to all premium content&quot;,
        &quot;duration_days&quot;: 1,
        &quot;price&quot;: &quot;2000.00&quot;,
        &quot;features&quot;: [
            {
                &quot;feature&quot;: &quot;Access to all premium movies&quot;
            },
            {
                &quot;feature&quot;: &quot;Access to all premium TV shows&quot;
            },
            {
                &quot;feature&quot;: &quot;HD quality streaming&quot;
            },
            {
                &quot;feature&quot;: &quot;Ad-free experience&quot;
            }
        ]
    },
    {
        &quot;id&quot;: 2,
        &quot;name&quot;: &quot;Weekly Access&quot;,
        &quot;slug&quot;: &quot;weekly-access&quot;,
        &quot;description&quot;: &quot;7-day access to all premium content&quot;,
        &quot;duration_days&quot;: 7,
        &quot;price&quot;: &quot;5000.00&quot;,
        &quot;features&quot;: [
            {
                &quot;feature&quot;: &quot;Access to all premium movies&quot;
            },
            {
                &quot;feature&quot;: &quot;Access to all premium TV shows&quot;
            },
            {
                &quot;feature&quot;: &quot;HD quality streaming&quot;
            },
            {
                &quot;feature&quot;: &quot;Ad-free experience&quot;
            }
        ]
    },
    {
        &quot;id&quot;: 3,
        &quot;name&quot;: &quot;Monthly Access&quot;,
        &quot;slug&quot;: &quot;monthly-access&quot;,
        &quot;description&quot;: &quot;30-day access to all premium content&quot;,
        &quot;duration_days&quot;: 30,
        &quot;price&quot;: &quot;8500.00&quot;,
        &quot;features&quot;: [
            {
                &quot;feature&quot;: &quot;Access to all premium movies&quot;
            },
            {
                &quot;feature&quot;: &quot;Access to all premium TV shows&quot;
            },
            {
                &quot;feature&quot;: &quot;HD quality streaming&quot;
            },
            {
                &quot;feature&quot;: &quot;Ad-free experience&quot;
            }
        ]
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-subscription-plans" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-subscription-plans"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-subscription-plans"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-subscription-plans" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-subscription-plans">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-subscription-plans" data-method="GET"
      data-path="api/v1/subscription-plans"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-subscription-plans', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-subscription-plans"
                    onclick="tryItOut('GETapi-v1-subscription-plans');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-subscription-plans"
                    onclick="cancelTryOut('GETapi-v1-subscription-plans');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-subscription-plans"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/subscription-plans</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-subscription-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-subscription-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-subscription-plans"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="subscription-plans-GETapi-v1-subscription-plans--id-">Get a specific subscription plan</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-subscription-plans--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/subscription-plans/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/subscription-plans/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-subscription-plans--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;name&quot;: &quot;Daily Access&quot;,
    &quot;slug&quot;: &quot;daily-access&quot;,
    &quot;description&quot;: &quot;24-hour access to all premium content&quot;,
    &quot;duration_days&quot;: 1,
    &quot;price&quot;: &quot;2000.00&quot;,
    &quot;features&quot;: [
        {
            &quot;feature&quot;: &quot;Access to all premium movies&quot;
        },
        {
            &quot;feature&quot;: &quot;Access to all premium TV shows&quot;
        },
        {
            &quot;feature&quot;: &quot;HD quality streaming&quot;
        },
        {
            &quot;feature&quot;: &quot;Ad-free experience&quot;
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-subscription-plans--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-subscription-plans--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-subscription-plans--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-subscription-plans--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-subscription-plans--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-subscription-plans--id-" data-method="GET"
      data-path="api/v1/subscription-plans/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-subscription-plans--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-subscription-plans--id-"
                    onclick="tryItOut('GETapi-v1-subscription-plans--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-subscription-plans--id-"
                    onclick="cancelTryOut('GETapi-v1-subscription-plans--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-subscription-plans--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/subscription-plans/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-subscription-plans--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-subscription-plans--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-subscription-plans--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-v1-subscription-plans--id-"
               value="1"
               data-component="url">
    <br>
<p>The ID of the subscription plan. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="payments">Payments</h1>

    <p>Payment gateways (list), initiate payment (rent/buy/subscription), upload proof (manual), verify.</p>

                                <h2 id="payments-GETapi-v1-payment-gateways">Get all active payment gateways</h2>

<p>
</p>

<p>Public list of configured payment gateways. Use this to render the
“Choose payment method” UI.</p>
<p>Gateways can be:</p>
<ul>
<li><code>type = AUTOMATIC</code> (eg. ioTec Pay, PawaPay)</li>
<li><code>type = MANUAL</code> (bank transfer / manual mobile money with proof upload)</li>
</ul>

<span id="example-requests-GETapi-v1-payment-gateways">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/payment-gateways" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/payment-gateways"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-payment-gateways">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: 7,
        &quot;name&quot;: &quot;ioTec Pay&quot;,
        &quot;slug&quot;: &quot;iotec&quot;,
        &quot;code&quot;: &quot;iotec&quot;,
        &quot;displayName&quot;: &quot;Mobile Money - Iotec&quot;,
        &quot;display_name&quot;: &quot;Mobile Money - Iotec&quot;,
        &quot;logoPath&quot;: &quot;payment-gateways/01KJZWAK4P244CXYXF0NQE7QDX.jpeg&quot;,
        &quot;logoUrl&quot;: &quot;https://portal.naraboxtv.com/storage/payment-gateways/01KJZWAK4P244CXYXF0NQE7QDX.jpeg&quot;,
        &quot;helperText&quot;: null,
        &quot;type&quot;: &quot;AUTOMATIC&quot;,
        &quot;instructions&quot;: null,
        &quot;paymentDetails&quot;: null
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-payment-gateways" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-payment-gateways"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-payment-gateways"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-payment-gateways" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-payment-gateways">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-payment-gateways" data-method="GET"
      data-path="api/v1/payment-gateways"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-payment-gateways', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-payment-gateways"
                    onclick="tryItOut('GETapi-v1-payment-gateways');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-payment-gateways"
                    onclick="cancelTryOut('GETapi-v1-payment-gateways');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-payment-gateways"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/payment-gateways</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-payment-gateways"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-payment-gateways"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-payment-gateways"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                    <h2 id="payments-POSTapi-v1-payments-initiate">Initiate a payment (rent, buy, or subscription)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Creates a <code>payment_transactions</code> row and returns a <code>transaction_ref</code>
that the frontend can use with the appropriate gateway flow.</p>
<p>For <strong>manual</strong> gateways:</p>
<ul>
<li>Returns bank/mobile-money instructions and <code>paymentDetails</code></li>
<li>Frontend must call <code>POST /api/v1/payments/upload-proof</code> afterwards</li>
</ul>
<p>For <strong>automatic</strong> gateways:</p>
<ul>
<li>Returns a generic “PENDING” status and <code>transaction_ref</code></li>
<li>The actual collection happens via gateway-specific endpoints
(eg. ioTec, PawaPay) or external UIs.</li>
</ul>

<span id="example-requests-POSTapi-v1-payments-initiate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/payments/initiate" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"media_id\": 1,
    \"media_type\": \"MOVIE\",
    \"type\": \"RENT\",
    \"gateway_id\": 3,
    \"subscription_plan_id\": 2,
    \"phone\": \"256780000000\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/payments/initiate"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "media_id": 1,
    "media_type": "MOVIE",
    "type": "RENT",
    "gateway_id": 3,
    "subscription_plan_id": 2,
    "phone": "256780000000"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments-initiate">
            <blockquote>
            <p>Example response (200, Manual gateway):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;transaction_ref&quot;: &quot;NBX-3Y7Z5K0PQ8LM&quot;,
    &quot;amount&quot;: 1000,
    &quot;status&quot;: &quot;PENDING&quot;,
    &quot;gateway_type&quot;: &quot;MANUAL&quot;,
    &quot;instructions&quot;: &quot;Send UGX 1,000 to MTN 0770 000 000 and include your transaction_ref in the note.&quot;,
    &quot;payment_details&quot;: {
        &quot;account_name&quot;: &quot;NaraBox TV&quot;,
        &quot;account_number&quot;: &quot;0770000000&quot;,
        &quot;provider&quot;: &quot;MTN MoMo&quot;
    },
    &quot;message&quot;: &quot;Please follow the instructions and upload proof of payment&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Automatic gateway):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;transaction_ref&quot;: &quot;NBX-5K8LM3Y7Z0PQ&quot;,
    &quot;amount&quot;: 8500,
    &quot;status&quot;: &quot;PENDING&quot;,
    &quot;gateway_type&quot;: &quot;AUTOMATIC&quot;,
    &quot;message&quot;: &quot;Payment initiated. Please complete on your device.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid amount&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-payments-initiate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments-initiate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments-initiate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments-initiate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments-initiate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments-initiate" data-method="POST"
      data-path="api/v1/payments/initiate"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments-initiate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments-initiate"
                    onclick="tryItOut('POSTapi-v1-payments-initiate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments-initiate"
                    onclick="cancelTryOut('POSTapi-v1-payments-initiate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments-initiate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/initiate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-payments-initiate"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-payments-initiate"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="media_id"                data-endpoint="POSTapi-v1-payments-initiate"
               value="1"
               data-component="body">
    <br>
<p>required_if:type,RENT,BUY The movie/TV show id for rent/buy. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_type"                data-endpoint="POSTapi-v1-payments-initiate"
               value="MOVIE"
               data-component="body">
    <br>
<p>required_if:type,RENT,BUY Must be <code>MOVIE</code> or <code>TV_SHOW</code>. Example: <code>MOVIE</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="POSTapi-v1-payments-initiate"
               value="RENT"
               data-component="body">
    <br>
<p>One of <code>RENT</code>, <code>BUY</code>, <code>SUBSCRIPTION</code>. Example: <code>RENT</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>gateway_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="gateway_id"                data-endpoint="POSTapi-v1-payments-initiate"
               value="3"
               data-component="body">
    <br>
<p>The <code>id</code> of the selected payment gateway. Example: <code>3</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>subscription_plan_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="subscription_plan_id"                data-endpoint="POSTapi-v1-payments-initiate"
               value="2"
               data-component="body">
    <br>
<p>required_if:type,SUBSCRIPTION The <code>id</code> of the subscription plan. Example: <code>2</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-payments-initiate"
               value="256780000000"
               data-component="body">
    <br>
<p>nullable Phone number (used by some mobile money gateways). Example: <code>256780000000</code></p>
        </div>
        </form>

                    <h2 id="payments-POSTapi-v1-payments-upload-proof">Upload payment proof for manual payments</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>After a manual payment (bank or mobile money), the user uploads a screenshot
or PDF. Admins then review and approve/reject in the back office.</p>
<p>This endpoint:</p>
<ul>
<li>Stores the file under <code>storage/app/public/payment-proofs</code></li>
<li>Creates a <code>payments</code> row with status <code>PENDING</code></li>
</ul>

<span id="example-requests-POSTapi-v1-payments-upload-proof">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/payments/upload-proof" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: multipart/form-data" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --form "transaction_ref=NBX-3Y7Z5K0PQ8LM"\
    --form "notes=Paid using MTN at 10:32pm."\
    --form "proof=@/private/var/folders/pq/y9x4s4kn4q3c6mhx76gy5_dw0000gn/T/phpmbs167es13mlbnXrAjF" </code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/payments/upload-proof"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "multipart/form-data",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

const body = new FormData();
body.append('transaction_ref', 'NBX-3Y7Z5K0PQ8LM');
body.append('notes', 'Paid using MTN at 10:32pm.');
body.append('proof', document.querySelector('input[name="proof"]').files[0]);

fetch(url, {
    method: "POST",
    headers,
    body,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments-upload-proof">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;message&quot;: &quot;Payment proof uploaded. Waiting for admin approval.&quot;,
    &quot;payment_id&quot;: 42
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Transaction is not pending&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-payments-upload-proof" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments-upload-proof"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments-upload-proof"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments-upload-proof" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments-upload-proof">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments-upload-proof" data-method="POST"
      data-path="api/v1/payments/upload-proof"
      data-authed="1"
      data-hasfiles="1"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments-upload-proof', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments-upload-proof"
                    onclick="tryItOut('POSTapi-v1-payments-upload-proof');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments-upload-proof"
                    onclick="cancelTryOut('POSTapi-v1-payments-upload-proof');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments-upload-proof"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/upload-proof</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-payments-upload-proof"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments-upload-proof"
               value="multipart/form-data"
               data-component="header">
    <br>
<p>Example: <code>multipart/form-data</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments-upload-proof"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-payments-upload-proof"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>transaction_ref</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="transaction_ref"                data-endpoint="POSTapi-v1-payments-upload-proof"
               value="NBX-3Y7Z5K0PQ8LM"
               data-component="body">
    <br>
<p>The <code>transaction_ref</code> returned from <code>/payments/initiate</code>. Example: <code>NBX-3Y7Z5K0PQ8LM</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>proof</code></b>&nbsp;&nbsp;
<small>file</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="file" style="display: none"
                              name="proof"                data-endpoint="POSTapi-v1-payments-upload-proof"
               value=""
               data-component="body">
    <br>
<p>Image or PDF file up to 10MB. Must be jpeg, jpg, png, or pdf. Example: <code>/private/var/folders/pq/y9x4s4kn4q3c6mhx76gy5_dw0000gn/T/phpmbs167es13mlbnXrAjF</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>notes</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="notes"                data-endpoint="POSTapi-v1-payments-upload-proof"
               value="Paid using MTN at 10:32pm."
               data-component="body">
    <br>
<p>nullable Optional notes from the user. Max 1000 characters. Example: <code>Paid using MTN at 10:32pm.</code></p>
        </div>
        </form>

                    <h2 id="payments-POSTapi-v1-payments-verify">Verify payment (for automatic gateways)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Frontend helper to confirm if a payment has been completed and access granted.</p>
<p>Behaviour:</p>
<ul>
<li>For <strong>manual</strong> gateways: checks the related <code>payments</code> record and returns:<ul>
<li><code>APPROVED</code> when admin has approved (and access has been granted)</li>
<li><code>REJECTED</code> when admin rejected</li>
<li><code>PENDING</code> while awaiting review</li>
</ul>
</li>
<li>For <strong>automatic</strong> gateways: currently simulates a success and calls
<code>PaymentApprovalService::grantAccess()</code>. In production, this should
be wired to the real provider status API.</li>
</ul>

<span id="example-requests-POSTapi-v1-payments-verify">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/payments/verify" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"transaction_ref\": \"NBX-3Y7Z5K0PQ8LM\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/payments/verify"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "transaction_ref": "NBX-3Y7Z5K0PQ8LM"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments-verify">
            <blockquote>
            <p>Example response (200, Manual gateway approved):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;status&quot;: &quot;APPROVED&quot;,
    &quot;message&quot;: &quot;Payment approved. Access granted.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Manual gateway pending):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;status&quot;: &quot;PENDING&quot;,
    &quot;message&quot;: &quot;Payment is pending admin approval&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Automatic gateway success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;transaction&quot;: {
        &quot;ref&quot;: &quot;NBX-5K8LM3Y7Z0PQ&quot;,
        &quot;status&quot;: &quot;SUCCESS&quot;,
        &quot;type&quot;: &quot;RENT&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-payments-verify" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments-verify"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments-verify"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments-verify" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments-verify">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments-verify" data-method="POST"
      data-path="api/v1/payments/verify"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments-verify', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments-verify"
                    onclick="tryItOut('POSTapi-v1-payments-verify');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments-verify"
                    onclick="cancelTryOut('POSTapi-v1-payments-verify');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments-verify"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/verify</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-payments-verify"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-payments-verify"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>transaction_ref</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="transaction_ref"                data-endpoint="POSTapi-v1-payments-verify"
               value="NBX-3Y7Z5K0PQ8LM"
               data-component="body">
    <br>
<p>The <code>transaction_ref</code> to verify. Example: <code>NBX-3Y7Z5K0PQ8LM</code></p>
        </div>
        </form>

                    <h2 id="payments-POSTapi-v1-flutterwave-initiate">Initiate Flutterwave payment</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-flutterwave-initiate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/flutterwave/initiate" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"media_id\": 16,
    \"media_type\": \"TV_SHOW\",
    \"type\": \"SUBSCRIPTION\",
    \"return_url\": \"http:\\/\\/bailey.com\\/\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/flutterwave/initiate"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "media_id": 16,
    "media_type": "TV_SHOW",
    "type": "SUBSCRIPTION",
    "return_url": "http:\/\/bailey.com\/"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-flutterwave-initiate">
</span>
<span id="execution-results-POSTapi-v1-flutterwave-initiate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-flutterwave-initiate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-flutterwave-initiate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-flutterwave-initiate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-flutterwave-initiate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-flutterwave-initiate" data-method="POST"
      data-path="api/v1/flutterwave/initiate"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-flutterwave-initiate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-flutterwave-initiate"
                    onclick="tryItOut('POSTapi-v1-flutterwave-initiate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-flutterwave-initiate"
                    onclick="cancelTryOut('POSTapi-v1-flutterwave-initiate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-flutterwave-initiate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/flutterwave/initiate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-flutterwave-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-flutterwave-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-flutterwave-initiate"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="media_id"                data-endpoint="POSTapi-v1-flutterwave-initiate"
               value="16"
               data-component="body">
    <br>
<p>This field is required when <code>type</code> is <code>RENT</code> or <code>BUY</code>. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_type"                data-endpoint="POSTapi-v1-flutterwave-initiate"
               value="TV_SHOW"
               data-component="body">
    <br>
<p>This field is required when <code>type</code> is <code>RENT</code> or <code>BUY</code>. Example: <code>TV_SHOW</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>MOVIE</code></li> <li><code>TV_SHOW</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="POSTapi-v1-flutterwave-initiate"
               value="SUBSCRIPTION"
               data-component="body">
    <br>
<p>Example: <code>SUBSCRIPTION</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>RENT</code></li> <li><code>BUY</code></li> <li><code>SUBSCRIPTION</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>subscription_plan_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="subscription_plan_id"                data-endpoint="POSTapi-v1-flutterwave-initiate"
               value=""
               data-component="body">
    <br>
<p>This field is required when <code>type</code> is <code>SUBSCRIPTION</code>. The <code>id</code> of an existing record in the subscription_plans table.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>return_url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="return_url"                data-endpoint="POSTapi-v1-flutterwave-initiate"
               value="http://bailey.com/"
               data-component="body">
    <br>
<p>Must not be greater than 500 characters. Example: <code>http://bailey.com/</code></p>
        </div>
        </form>

                    <h2 id="payments-POSTapi-v1-flutterwave-verify">Verify Flutterwave payment</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-flutterwave-verify">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/flutterwave/verify" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"transaction_ref\": \"architecto\",
    \"transaction_id\": \"architecto\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/flutterwave/verify"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "transaction_ref": "architecto",
    "transaction_id": "architecto"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-flutterwave-verify">
</span>
<span id="execution-results-POSTapi-v1-flutterwave-verify" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-flutterwave-verify"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-flutterwave-verify"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-flutterwave-verify" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-flutterwave-verify">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-flutterwave-verify" data-method="POST"
      data-path="api/v1/flutterwave/verify"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-flutterwave-verify', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-flutterwave-verify"
                    onclick="tryItOut('POSTapi-v1-flutterwave-verify');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-flutterwave-verify"
                    onclick="cancelTryOut('POSTapi-v1-flutterwave-verify');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-flutterwave-verify"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/flutterwave/verify</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-flutterwave-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-flutterwave-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-flutterwave-verify"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>transaction_ref</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="transaction_ref"                data-endpoint="POSTapi-v1-flutterwave-verify"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>transaction_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="transaction_id"                data-endpoint="POSTapi-v1-flutterwave-verify"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
        </form>

                    <h2 id="payments-POSTapi-v1-iotec-initiate">Initiate ioTec Pay collection (phone prompt, in-site)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Starts an <strong>in-app</strong> mobile money collection via ioTec Pay:</p>
<ul>
<li>Validates phone number format for Uganda</li>
<li>Creates a <code>payment_transactions</code> row with provider_code <code>IOTEC</code></li>
<li>Sends a mobile money prompt to the user’s phone</li>
</ul>
<p>Frontend should:</p>
<ul>
<li>Call this endpoint when the user chooses ioTec Pay</li>
<li>Then poll <code>POST /api/v1/iotec/status</code> with the returned <code>transaction_ref</code></li>
</ul>
<p><code>type</code> and amount rules are identical to <code>/payments/initiate</code>.</p>

<span id="example-requests-POSTapi-v1-iotec-initiate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/iotec/initiate" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"media_id\": 1,
    \"media_type\": \"MOVIE\",
    \"type\": \"SUBSCRIPTION\",
    \"subscription_plan_id\": 3,
    \"phone\": \"256780000000\",
    \"return_url\": \"\\/dashboard\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/iotec/initiate"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "media_id": 1,
    "media_type": "MOVIE",
    "type": "SUBSCRIPTION",
    "subscription_plan_id": 3,
    "phone": "256780000000",
    "return_url": "\/dashboard"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-iotec-initiate">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;transaction_ref&quot;: &quot;NBX-IOT-ABC123XYZ0-1710240000&quot;,
    &quot;payment_id&quot;: 101,
    &quot;status&quot;: &quot;PENDING&quot;,
    &quot;message&quot;: &quot;Prompt sent to 25678*****000&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;ioTec Pay gateway is not available&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Invalid Uganda phone number. Use 256XXXXXXXXX or 0XXXXXXXXX.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-iotec-initiate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-iotec-initiate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-iotec-initiate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-iotec-initiate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-iotec-initiate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-iotec-initiate" data-method="POST"
      data-path="api/v1/iotec/initiate"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-iotec-initiate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-iotec-initiate"
                    onclick="tryItOut('POSTapi-v1-iotec-initiate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-iotec-initiate"
                    onclick="cancelTryOut('POSTapi-v1-iotec-initiate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-iotec-initiate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/iotec/initiate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-iotec-initiate"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="media_id"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="1"
               data-component="body">
    <br>
<p>required_if:type,RENT,BUY The movie/TV show id for rent/buy. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_type"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="MOVIE"
               data-component="body">
    <br>
<p>required_if:type,RENT,BUY Must be <code>MOVIE</code> or <code>TV_SHOW</code>. Example: <code>MOVIE</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="SUBSCRIPTION"
               data-component="body">
    <br>
<p>One of <code>RENT</code>, <code>BUY</code>, <code>SUBSCRIPTION</code>. Example: <code>SUBSCRIPTION</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>subscription_plan_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="subscription_plan_id"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="3"
               data-component="body">
    <br>
<p>required_if:type,SUBSCRIPTION The subscription plan id. Example: <code>3</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="256780000000"
               data-component="body">
    <br>
<p>Uganda phone in <code>2567XXXXXXXX</code> or <code>07XXXXXXXX</code> format. Example: <code>256780000000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>return_url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="return_url"                data-endpoint="POSTapi-v1-iotec-initiate"
               value="/dashboard"
               data-component="body">
    <br>
<p>nullable Relative or same-origin URL to redirect to after success (eg. <code>/dashboard</code>). Example: <code>/dashboard</code></p>
        </div>
        </form>

                    <h2 id="payments-GETapi-v1-iotec-status">Poll ioTec Pay payment status</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Polls the current status of an ioTec payment created via <code>POST /iotec/initiate</code>.</p>
<p>Normalized statuses:</p>
<ul>
<li><code>success</code>   → payment completed, access granted, <code>redirect_url</code> provided</li>
<li><code>failed</code>    → permanently failed (insufficient funds, timeout, etc.)</li>
<li><code>pending</code>   → still waiting for user/network confirmation</li>
</ul>

<span id="example-requests-GETapi-v1-iotec-status">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/iotec/status" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"transaction_ref\": \"NBX-IOT-ABC123XYZ0-1710240000\",
    \"payment_id\": 101
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/iotec/status"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "transaction_ref": "NBX-IOT-ABC123XYZ0-1710240000",
    "payment_id": 101
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-iotec-status">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;success&quot;,
    &quot;redirect_url&quot;: &quot;/dashboard&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Failed):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;failed&quot;,
    &quot;redirect_url&quot;: &quot;/dashboard&quot;,
    &quot;message&quot;: &quot;Payment failed&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Pending):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;pending&quot;,
    &quot;message&quot;: &quot;Waiting for confirmation&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Transaction not found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-iotec-status" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-iotec-status"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-iotec-status"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-iotec-status" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-iotec-status">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-iotec-status" data-method="GET"
      data-path="api/v1/iotec/status"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-iotec-status', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-iotec-status"
                    onclick="tryItOut('GETapi-v1-iotec-status');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-iotec-status"
                    onclick="cancelTryOut('GETapi-v1-iotec-status');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-iotec-status"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/iotec/status</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-iotec-status"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-iotec-status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-iotec-status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-iotec-status"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>transaction_ref</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="transaction_ref"                data-endpoint="GETapi-v1-iotec-status"
               value="NBX-IOT-ABC123XYZ0-1710240000"
               data-component="body">
    <br>
<p>The <code>transaction_ref</code> from <code>/iotec/initiate</code>. Example: <code>NBX-IOT-ABC123XYZ0-1710240000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>payment_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="payment_id"                data-endpoint="GETapi-v1-iotec-status"
               value="101"
               data-component="body">
    <br>
<p>The internal <code>payment_transactions.id</code> (alternative to transaction_ref). Example: <code>101</code></p>
        </div>
        </form>

                    <h2 id="payments-POSTapi-v1-iotec-status">Poll ioTec Pay payment status</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Polls the current status of an ioTec payment created via <code>POST /iotec/initiate</code>.</p>
<p>Normalized statuses:</p>
<ul>
<li><code>success</code>   → payment completed, access granted, <code>redirect_url</code> provided</li>
<li><code>failed</code>    → permanently failed (insufficient funds, timeout, etc.)</li>
<li><code>pending</code>   → still waiting for user/network confirmation</li>
</ul>

<span id="example-requests-POSTapi-v1-iotec-status">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/iotec/status" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"transaction_ref\": \"NBX-IOT-ABC123XYZ0-1710240000\",
    \"payment_id\": 101
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/iotec/status"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "transaction_ref": "NBX-IOT-ABC123XYZ0-1710240000",
    "payment_id": 101
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-iotec-status">
            <blockquote>
            <p>Example response (200, Success):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;success&quot;,
    &quot;redirect_url&quot;: &quot;/dashboard&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Failed):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;failed&quot;,
    &quot;redirect_url&quot;: &quot;/dashboard&quot;,
    &quot;message&quot;: &quot;Payment failed&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (200, Pending):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;pending&quot;,
    &quot;message&quot;: &quot;Waiting for confirmation&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Transaction not found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-iotec-status" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-iotec-status"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-iotec-status"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-iotec-status" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-iotec-status">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-iotec-status" data-method="POST"
      data-path="api/v1/iotec/status"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-iotec-status', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-iotec-status"
                    onclick="tryItOut('POSTapi-v1-iotec-status');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-iotec-status"
                    onclick="cancelTryOut('POSTapi-v1-iotec-status');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-iotec-status"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/iotec/status</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-iotec-status"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-iotec-status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-iotec-status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-iotec-status"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>transaction_ref</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="transaction_ref"                data-endpoint="POSTapi-v1-iotec-status"
               value="NBX-IOT-ABC123XYZ0-1710240000"
               data-component="body">
    <br>
<p>The <code>transaction_ref</code> from <code>/iotec/initiate</code>. Example: <code>NBX-IOT-ABC123XYZ0-1710240000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>payment_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="payment_id"                data-endpoint="POSTapi-v1-iotec-status"
               value="101"
               data-component="body">
    <br>
<p>The internal <code>payment_transactions.id</code> (alternative to transaction_ref). Example: <code>101</code></p>
        </div>
        </form>

                    <h2 id="payments-POSTapi-v1-payments-pawapay-deposit-initiate">Initiate PawaPay deposit</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Starts a PawaPay mobile money deposit for rent, buy, or subscription.</p>
<p>Behaviour:</p>
<ul>
<li>Normalizes the MSISDN to <code>2567XXXXXXXX</code></li>
<li>Creates a <code>payment_transactions</code> row with an external <code>deposit_id</code></li>
<li>Calls PawaPay’s deposit API and persists the initial provider status</li>
</ul>
<p>Frontend should:</p>
<ul>
<li>Call this endpoint when PawaPay is chosen</li>
<li>Then poll <code>GET /api/v1/payments/pawapay/deposit/{depositId}/status</code>
(where <code>depositId</code> is returned here)</li>
</ul>

<span id="example-requests-POSTapi-v1-payments-pawapay-deposit-initiate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/payments/pawapay/deposit/initiate" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"media_id\": 1,
    \"media_type\": \"MOVIE\",
    \"type\": \"SUBSCRIPTION\",
    \"subscription_plan_id\": 3,
    \"phone\": \"0770000000\",
    \"provider\": \"MTN_MOMO_UGA\",
    \"currency\": \"UGX\",
    \"deposit_id\": \"a232abbe-3006-3f67-bed4-124abab91dce\",
    \"client_reference_id\": \"mobile-app-checkout-123\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/payments/pawapay/deposit/initiate"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "media_id": 1,
    "media_type": "MOVIE",
    "type": "SUBSCRIPTION",
    "subscription_plan_id": 3,
    "phone": "0770000000",
    "provider": "MTN_MOMO_UGA",
    "currency": "UGX",
    "deposit_id": "a232abbe-3006-3f67-bed4-124abab91dce",
    "client_reference_id": "mobile-app-checkout-123"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-payments-pawapay-deposit-initiate">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;transaction_id&quot;: 501,
    &quot;deposit_id&quot;: &quot;a232abbe-3006-3f67-bed4-124abab91dce&quot;,
    &quot;transaction_ref&quot;: &quot;NBX-PWP-ABC123XYZ0-1710240000&quot;,
    &quot;status&quot;: &quot;PENDING&quot;,
    &quot;message&quot;: &quot;Deposit initiated. Check your phone to approve payment.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;PawaPay gateway is not available&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;status&quot;: &quot;FAILED&quot;,
    &quot;message&quot;: &quot;Invalid phone number. Use 07XXXXXXXX, 7XXXXXXXX, +2567XXXXXXXX or 2567XXXXXXXX.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-payments-pawapay-deposit-initiate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-payments-pawapay-deposit-initiate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-payments-pawapay-deposit-initiate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-payments-pawapay-deposit-initiate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-payments-pawapay-deposit-initiate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-payments-pawapay-deposit-initiate" data-method="POST"
      data-path="api/v1/payments/pawapay/deposit/initiate"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-payments-pawapay-deposit-initiate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-payments-pawapay-deposit-initiate"
                    onclick="tryItOut('POSTapi-v1-payments-pawapay-deposit-initiate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-payments-pawapay-deposit-initiate"
                    onclick="cancelTryOut('POSTapi-v1-payments-pawapay-deposit-initiate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-payments-pawapay-deposit-initiate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/payments/pawapay/deposit/initiate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="media_id"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="1"
               data-component="body">
    <br>
<p>required_if:type,RENT,BUY The movie/TV show id for rent/buy. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_type"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="MOVIE"
               data-component="body">
    <br>
<p>required_if:type,RENT,BUY Must be <code>MOVIE</code> or <code>TV_SHOW</code>. Example: <code>MOVIE</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="SUBSCRIPTION"
               data-component="body">
    <br>
<p>One of <code>RENT</code>, <code>BUY</code>, <code>SUBSCRIPTION</code>. Example: <code>SUBSCRIPTION</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>subscription_plan_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="subscription_plan_id"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="3"
               data-component="body">
    <br>
<p>required_if:type,SUBSCRIPTION The subscription plan id. Example: <code>3</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="0770000000"
               data-component="body">
    <br>
<p>Uganda phone (07XXXXXXXX, 7XXXXXXXX, +2567XXXXXXXX or 2567XXXXXXXX). Example: <code>0770000000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>provider</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="MTN_MOMO_UGA"
               data-component="body">
    <br>
<p>PawaPay provider code. One of <code>MTN_MOMO_UGA</code>, <code>AIRTEL_OAPI_UGA</code>. Example: <code>MTN_MOMO_UGA</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>currency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="currency"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="UGX"
               data-component="body">
    <br>
<p>Three-letter currency code (eg. <code>UGX</code>). Example: <code>UGX</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>deposit_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="deposit_id"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="a232abbe-3006-3f67-bed4-124abab91dce"
               data-component="body">
    <br>
<p>uuid nullable Optional client-generated deposit UUID. Example: <code>a232abbe-3006-3f67-bed4-124abab91dce</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>client_reference_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="client_reference_id"                data-endpoint="POSTapi-v1-payments-pawapay-deposit-initiate"
               value="mobile-app-checkout-123"
               data-component="body">
    <br>
<p>nullable Optional client reference id echoed back in provider callbacks. Example: <code>mobile-app-checkout-123</code></p>
        </div>
        </form>

                    <h2 id="payments-GETapi-v1-payments-pawapay-deposit--depositId--status">Check PawaPay deposit status</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Polls the latest PawaPay status for a given <code>depositId</code>.</p>
<p>Normalized statuses:</p>
<ul>
<li><code>COMPLETED</code></li>
<li><code>FAILED</code></li>
<li><code>PENDING</code></li>
</ul>
<p>On <code>COMPLETED</code>, <code>PaymentApprovalService::grantAccess()</code> has already
been called by the provider result handler.</p>

<span id="example-requests-GETapi-v1-payments-pawapay-deposit--depositId--status">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/payments/pawapay/deposit/a232abbe-3006-3f67-bed4-124abab91dce/status" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/payments/pawapay/deposit/a232abbe-3006-3f67-bed4-124abab91dce/status"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-payments-pawapay-deposit--depositId--status">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: true,
    &quot;transaction_id&quot;: 501,
    &quot;deposit_id&quot;: &quot;a232abbe-3006-3f67-bed4-124abab91dce&quot;,
    &quot;status&quot;: &quot;COMPLETED&quot;,
    &quot;message&quot;: null
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Transaction not found&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-payments-pawapay-deposit--depositId--status" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-payments-pawapay-deposit--depositId--status"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-payments-pawapay-deposit--depositId--status"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-payments-pawapay-deposit--depositId--status" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-payments-pawapay-deposit--depositId--status">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-payments-pawapay-deposit--depositId--status" data-method="GET"
      data-path="api/v1/payments/pawapay/deposit/{depositId}/status"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-payments-pawapay-deposit--depositId--status', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-payments-pawapay-deposit--depositId--status"
                    onclick="tryItOut('GETapi-v1-payments-pawapay-deposit--depositId--status');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-payments-pawapay-deposit--depositId--status"
                    onclick="cancelTryOut('GETapi-v1-payments-pawapay-deposit--depositId--status');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-payments-pawapay-deposit--depositId--status"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/payments/pawapay/deposit/{depositId}/status</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-payments-pawapay-deposit--depositId--status"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-payments-pawapay-deposit--depositId--status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-payments-pawapay-deposit--depositId--status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-payments-pawapay-deposit--depositId--status"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>depositId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="depositId"                data-endpoint="GETapi-v1-payments-pawapay-deposit--depositId--status"
               value="a232abbe-3006-3f67-bed4-124abab91dce"
               data-component="url">
    <br>
<p>The PawaPay deposit identifier returned from initiate. Example: <code>a232abbe-3006-3f67-bed4-124abab91dce</code></p>
            </div>
                    </form>

                <h1 id="dashboard-watch-history">Dashboard & Watch history</h1>

    <p>User dashboard: subscription, rentals, purchases, transactions, watch history. Requires auth.</p>

                                <h2 id="dashboard-watch-history-GETapi-v1-dashboard">Get current user dashboard</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>High-level snapshot of the user’s account state and purchases for
powering the “Account / Dashboard” screen.</p>
<p>Shape:</p>
<ul>
<li><code>user</code>: basic profile + plan label and status</li>
<li><code>subscription</code>: active or most recent subscription (if any)</li>
<li><code>pending_subscription</code>: latest pending subscription payment (if any)</li>
<li><code>vault</code>:<ul>
<li><code>rentedIds</code>: ids of currently rented movies/TV shows</li>
<li><code>purchasedIds</code>: ids of permanently owned movies/TV shows</li>
<li><code>watchHistory</code>: recent watch history entries</li>
</ul>
</li>
<li><code>rentals</code>: expanded list of active rentals</li>
<li><code>purchases</code>: expanded list of purchases</li>
<li><code>transactions</code>: all payment transactions with gateway and item info</li>
</ul>
<p>Plan semantics:</p>
<ul>
<li><code>plan</code> (string) is a human label like <code>FREE</code>, <code>Daily Access</code>, <code>Monthly Access</code></li>
<li><code>planStatus</code> is one of <code>NONE</code>, <code>ACTIVE</code>, <code>PENDING</code>, or any legacy status</li>
</ul>

<span id="example-requests-GETapi-v1-dashboard">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/dashboard" \
    --header "Authorization: Bearer {YOUR_AUTH_TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/dashboard"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-dashboard">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;user&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Jane Doe&quot;,
        &quot;email&quot;: &quot;jane@example.com&quot;,
        &quot;phone&quot;: &quot;+256780000000&quot;,
        &quot;avatar&quot;: null,
        &quot;plan&quot;: &quot;Monthly Access&quot;,
        &quot;planStatus&quot;: &quot;ACTIVE&quot;,
        &quot;renewalDate&quot;: &quot;2026-03-31&quot;
    },
    &quot;subscription&quot;: {
        &quot;plan&quot;: &quot;Monthly Access&quot;,
        &quot;status&quot;: &quot;ACTIVE&quot;,
        &quot;started_at&quot;: &quot;2026-03-01T20:00:00+03:00&quot;,
        &quot;expires_at&quot;: &quot;2026-03-31T20:00:00+03:00&quot;
    },
    &quot;pending_subscription&quot;: null,
    &quot;vault&quot;: {
        &quot;rentedIds&quot;: [
            193,
            308
        ],
        &quot;purchasedIds&quot;: [
            1,
            59
        ],
        &quot;watchHistory&quot;: [
            {
                &quot;id&quot;: 1,
                &quot;title&quot;: &quot;Zootopia 2&quot;,
                &quot;thumbnail&quot;: &quot;https://portal.naraboxtv.com/storage/tmdb/posters/tmdb_f158459be819affd8c9f257f75a49f15.jpg&quot;,
                &quot;episodeId&quot;: null,
                &quot;progressSeconds&quot;: 1200,
                &quot;lastWatched&quot;: &quot;2026-03-10T20:15:30+03:00&quot;
            }
        ]
    },
    &quot;rentals&quot;: [
        {
            &quot;id&quot;: 193,
            &quot;media_id&quot;: 193,
            &quot;media_type&quot;: &quot;MOVIE&quot;,
            &quot;title&quot;: &quot;Shelter - VJ Junior&quot;,
            &quot;thumbnail&quot;: &quot;https://portal.naraboxtv.com/storage/tmdb/posters/tmdb_98ca49849b082b96c5424c2dfa69f648.jpg&quot;,
            &quot;expires_at&quot;: &quot;2026-03-15T20:00:00+03:00&quot;,
            &quot;rented_at&quot;: &quot;2026-03-14T20:00:00+03:00&quot;
        }
    ],
    &quot;purchases&quot;: [
        {
            &quot;id&quot;: 59,
            &quot;media_id&quot;: 59,
            &quot;media_type&quot;: &quot;MOVIE&quot;,
            &quot;title&quot;: &quot;Five Nights at Freddy&#039;s 2&quot;,
            &quot;thumbnail&quot;: &quot;https://portal.naraboxtv.com/storage/tmdb/posters/tmdb_9c202836afbe63c95f5f4e02196a7701.jpg&quot;,
            &quot;purchased_at&quot;: &quot;2026-03-01T10:00:00+03:00&quot;
        }
    ],
    &quot;transactions&quot;: [
        {
            &quot;id&quot;: 701,
            &quot;transaction_ref&quot;: &quot;NBX-PWP-ABC123XYZ0-1710240000&quot;,
            &quot;type&quot;: &quot;SUBSCRIPTION&quot;,
            &quot;amount&quot;: 8500,
            &quot;status&quot;: &quot;SUCCESS&quot;,
            &quot;payment_gateway&quot;: {
                &quot;id&quot;: 7,
                &quot;name&quot;: &quot;PawaPay&quot;,
                &quot;display_name&quot;: &quot;PawaPay Mobile Money&quot;
            },
            &quot;transactionable&quot;: null,
            &quot;itemTitle&quot;: &quot;Monthly Access Subscription&quot;,
            &quot;created_at&quot;: &quot;2026-03-01T09:59:00+03:00&quot;
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;Unauthorized&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-dashboard" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-dashboard"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-dashboard"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-dashboard" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-dashboard">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-dashboard" data-method="GET"
      data-path="api/v1/dashboard"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-dashboard', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-dashboard"
                    onclick="tryItOut('GETapi-v1-dashboard');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-dashboard"
                    onclick="cancelTryOut('GETapi-v1-dashboard');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-dashboard"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/dashboard</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-dashboard"
               value="Bearer {YOUR_AUTH_TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-dashboard"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-dashboard"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-dashboard"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        </form>

                <h1 id="comments">Comments</h1>

    <p>Comments on media (media_id = movie id). List (public), add (auth or anonymous with user_name), like, delete (auth, own).</p>

                                <h2 id="comments-GETapi-v1-comments--mediaId-">Get comments for a media item. Public. mediaId = movies.id.</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-comments--mediaId-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/comments/architecto" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/comments/architecto"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-comments--mediaId-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-comments--mediaId-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-comments--mediaId-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-comments--mediaId-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-comments--mediaId-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-comments--mediaId-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-comments--mediaId-" data-method="GET"
      data-path="api/v1/comments/{mediaId}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-comments--mediaId-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-comments--mediaId-"
                    onclick="tryItOut('GETapi-v1-comments--mediaId-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-comments--mediaId-"
                    onclick="cancelTryOut('GETapi-v1-comments--mediaId-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-comments--mediaId-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/comments/{mediaId}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-comments--mediaId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-comments--mediaId-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-comments--mediaId-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>mediaId</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="mediaId"                data-endpoint="GETapi-v1-comments--mediaId-"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="comments-POSTapi-v1-comments">Store a new comment</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-comments">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/comments" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"media_id\": \"architecto\",
    \"text\": \"n\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/comments"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "media_id": "architecto",
    "text": "n"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-comments">
</span>
<span id="execution-results-POSTapi-v1-comments" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-comments"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-comments"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-comments" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-comments">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-comments" data-method="POST"
      data-path="api/v1/comments"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-comments', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-comments"
                    onclick="tryItOut('POSTapi-v1-comments');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-comments"
                    onclick="cancelTryOut('POSTapi-v1-comments');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-comments"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/comments</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-comments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-comments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-comments"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_id"                data-endpoint="POSTapi-v1-comments"
               value="architecto"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the movies table. Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>text</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="text"                data-endpoint="POSTapi-v1-comments"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 5000 characters. Example: <code>n</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>parent_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="parent_id"                data-endpoint="POSTapi-v1-comments"
               value=""
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the comments table.</p>
        </div>
        </form>

                    <h2 id="comments-POSTapi-v1-comments--id--like">Toggle like on a comment</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-comments--id--like">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/comments/1/like" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/comments/1/like"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-comments--id--like">
</span>
<span id="execution-results-POSTapi-v1-comments--id--like" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-comments--id--like"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-comments--id--like"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-comments--id--like" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-comments--id--like">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-comments--id--like" data-method="POST"
      data-path="api/v1/comments/{id}/like"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-comments--id--like', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-comments--id--like"
                    onclick="tryItOut('POSTapi-v1-comments--id--like');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-comments--id--like"
                    onclick="cancelTryOut('POSTapi-v1-comments--id--like');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-comments--id--like"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/comments/{id}/like</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-comments--id--like"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-comments--id--like"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-comments--id--like"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="POSTapi-v1-comments--id--like"
               value="1"
               data-component="url">
    <br>
<p>The ID of the comment. Example: <code>1</code></p>
            </div>
                    </form>

                    <h2 id="comments-DELETEapi-v1-comments--id-">Delete a comment</h2>

<p>
</p>



<span id="example-requests-DELETEapi-v1-comments--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://127.0.0.1:8000/api/v1/comments/1" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/comments/1"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-comments--id-">
</span>
<span id="execution-results-DELETEapi-v1-comments--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-comments--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-comments--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-comments--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-comments--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-comments--id-" data-method="DELETE"
      data-path="api/v1/comments/{id}"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-comments--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-comments--id-"
                    onclick="tryItOut('DELETEapi-v1-comments--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-comments--id-"
                    onclick="cancelTryOut('DELETEapi-v1-comments--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-comments--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/comments/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-comments--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-comments--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="DELETEapi-v1-comments--id-"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="DELETEapi-v1-comments--id-"
               value="1"
               data-component="url">
    <br>
<p>The ID of the comment. Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="ad-banners">Ad banners</h1>

    <p>Read-only banner API for web/app clients.</p>

                                <h2 id="ad-banners-GETapi-v1-banners">List active banners</h2>

<p>
</p>

<p>Filter banners by placement and platform (app/web/all).</p>

<span id="example-requests-GETapi-v1-banners">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://127.0.0.1:8000/api/v1/banners" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"placement\": \"b\",
    \"platform\": \"all\",
    \"limit\": 22
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/banners"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "placement": "b",
    "platform": "all",
    "limit": 22
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-banners">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
access-control-allow-headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
access-control-allow-credentials: true
access-control-expose-headers: 
vary: Origin
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-banners" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-banners"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-banners"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-banners" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-banners">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-banners" data-method="GET"
      data-path="api/v1/banners"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-banners', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-banners"
                    onclick="tryItOut('GETapi-v1-banners');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-banners"
                    onclick="cancelTryOut('GETapi-v1-banners');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-banners"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/banners</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-banners"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-banners"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="GETapi-v1-banners"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>placement</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="placement"                data-endpoint="GETapi-v1-banners"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="GETapi-v1-banners"
               value="all"
               data-component="body">
    <br>
<p>Example: <code>all</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>app</code></li> <li><code>web</code></li> <li><code>all</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>limit</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="limit"                data-endpoint="GETapi-v1-banners"
               value="22"
               data-component="body">
    <br>
<p>Must be at least 1. Must not be greater than 50. Example: <code>22</code></p>
        </div>
        </form>

                <h1 id="endpoints">Endpoints</h1>

    

                                <h2 id="endpoints-POSTapi-v1-video-fetch">Fetch a video from URL and store it on the CDN server.</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-video-fetch">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/video/fetch" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"url\": \"http:\\/\\/www.bailey.biz\\/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html\",
    \"sourceable_type\": \"App\\\\Models\\\\Episode\",
    \"quality\": \"i\",
    \"format\": \"khwayk\",
    \"import_mode\": \"now\",
    \"import_strategy\": \"python_worker\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/video/fetch"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "url": "http:\/\/www.bailey.biz\/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html",
    "sourceable_type": "App\\Models\\Episode",
    "quality": "i",
    "format": "khwayk",
    "import_mode": "now",
    "import_strategy": "python_worker"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-video-fetch">
</span>
<span id="execution-results-POSTapi-v1-video-fetch" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-video-fetch"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-video-fetch"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-video-fetch" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-video-fetch">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-video-fetch" data-method="POST"
      data-path="api/v1/video/fetch"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-video-fetch', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-video-fetch"
                    onclick="tryItOut('POSTapi-v1-video-fetch');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-video-fetch"
                    onclick="cancelTryOut('POSTapi-v1-video-fetch');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-video-fetch"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/video/fetch</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-video-fetch"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-video-fetch"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-video-fetch"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="url"                data-endpoint="POSTapi-v1-video-fetch"
               value="http://www.bailey.biz/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html"
               data-component="body">
    <br>
<p>Must be a valid URL. Example: <code>http://www.bailey.biz/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sourceable_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sourceable_type"                data-endpoint="POSTapi-v1-video-fetch"
               value="App\Models\Episode"
               data-component="body">
    <br>
<p>Example: <code>App\Models\Episode</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>App\Models\Movie</code></li> <li><code>App\Models\Episode</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sourceable_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sourceable_id"                data-endpoint="POSTapi-v1-video-fetch"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>quality</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="quality"                data-endpoint="POSTapi-v1-video-fetch"
               value="i"
               data-component="body">
    <br>
<p>Must not be greater than 50 characters. Example: <code>i</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>format</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="format"                data-endpoint="POSTapi-v1-video-fetch"
               value="khwayk"
               data-component="body">
    <br>
<p>Must not be greater than 10 characters. Example: <code>khwayk</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>import_mode</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="import_mode"                data-endpoint="POSTapi-v1-video-fetch"
               value="now"
               data-component="body">
    <br>
<p>Example: <code>now</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>now</code></li> <li><code>queue</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>import_strategy</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="import_strategy"                data-endpoint="POSTapi-v1-video-fetch"
               value="python_worker"
               data-component="body">
    <br>
<p>Example: <code>python_worker</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>auto</code></li> <li><code>python_worker</code></li></ul>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-v1-subtitle-fetch">Fetch a subtitle file from a URL and save it to the server</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-subtitle-fetch">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/subtitle/fetch" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"url\": \"http:\\/\\/www.bailey.biz\\/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html\",
    \"subtitleable_type\": \"App\\\\Models\\\\Movie\",
    \"language\": \"ikhway\",
    \"label\": \"k\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/subtitle/fetch"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "url": "http:\/\/www.bailey.biz\/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html",
    "subtitleable_type": "App\\Models\\Movie",
    "language": "ikhway",
    "label": "k"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-subtitle-fetch">
</span>
<span id="execution-results-POSTapi-v1-subtitle-fetch" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-subtitle-fetch"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-subtitle-fetch"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-subtitle-fetch" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-subtitle-fetch">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-subtitle-fetch" data-method="POST"
      data-path="api/v1/subtitle/fetch"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-subtitle-fetch', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-subtitle-fetch"
                    onclick="tryItOut('POSTapi-v1-subtitle-fetch');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-subtitle-fetch"
                    onclick="cancelTryOut('POSTapi-v1-subtitle-fetch');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-subtitle-fetch"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/subtitle/fetch</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-subtitle-fetch"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-subtitle-fetch"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-subtitle-fetch"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>url</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="url"                data-endpoint="POSTapi-v1-subtitle-fetch"
               value="http://www.bailey.biz/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html"
               data-component="body">
    <br>
<p>Must be a valid URL. Example: <code>http://www.bailey.biz/quos-velit-et-fugiat-sunt-nihil-accusantium-harum.html</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>subtitleable_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="subtitleable_type"                data-endpoint="POSTapi-v1-subtitle-fetch"
               value="App\Models\Movie"
               data-component="body">
    <br>
<p>Example: <code>App\Models\Movie</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>App\Models\Movie</code></li> <li><code>App\Models\Episode</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>subtitleable_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="subtitleable_id"                data-endpoint="POSTapi-v1-subtitle-fetch"
               value=""
               data-component="body">
    <br>

        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>language</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="language"                data-endpoint="POSTapi-v1-subtitle-fetch"
               value="ikhway"
               data-component="body">
    <br>
<p>Must not be greater than 10 characters. Example: <code>ikhway</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>label</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="label"                data-endpoint="POSTapi-v1-subtitle-fetch"
               value="k"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>k</code></p>
        </div>
        </form>

                <h1 id="push-devices">Push devices</h1>

    <p>Register and manage device tokens for push notifications.</p>

                                <h2 id="push-devices-POSTapi-v1-push-devices-register">Register or update a device token</h2>

<p>
</p>

<p>This endpoint is used by the mobile/web app to register a push token.
If a device with the same provider+token already exists, it will be updated.</p>

<span id="example-requests-POSTapi-v1-push-devices-register">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/push/devices/register" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"platform\": \"web\",
    \"provider\": \"fcm\",
    \"token\": \"b\",
    \"device_id\": \"n\",
    \"device_name\": \"g\",
    \"app_version\": \"z\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/push/devices/register"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "platform": "web",
    "provider": "fcm",
    "token": "b",
    "device_id": "n",
    "device_name": "g",
    "app_version": "z"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-push-devices-register">
</span>
<span id="execution-results-POSTapi-v1-push-devices-register" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-push-devices-register"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-push-devices-register"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-push-devices-register" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-push-devices-register">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-push-devices-register" data-method="POST"
      data-path="api/v1/push/devices/register"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-push-devices-register', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-push-devices-register"
                    onclick="tryItOut('POSTapi-v1-push-devices-register');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-push-devices-register"
                    onclick="cancelTryOut('POSTapi-v1-push-devices-register');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-push-devices-register"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/push/devices/register</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-push-devices-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-push-devices-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-push-devices-register"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="POSTapi-v1-push-devices-register"
               value="web"
               data-component="body">
    <br>
<p>Example: <code>web</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>android</code></li> <li><code>ios</code></li> <li><code>web</code></li> <li><code>other</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>provider</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider"                data-endpoint="POSTapi-v1-push-devices-register"
               value="fcm"
               data-component="body">
    <br>
<p>Example: <code>fcm</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>fcm</code></li> <li><code>onesignal</code></li> <li><code>custom</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-v1-push-devices-register"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 1024 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_id"                data-endpoint="POSTapi-v1-push-devices-register"
               value="n"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>n</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>device_name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="device_name"                data-endpoint="POSTapi-v1-push-devices-register"
               value="g"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>g</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>app_version</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="app_version"                data-endpoint="POSTapi-v1-push-devices-register"
               value="z"
               data-component="body">
    <br>
<p>Must not be greater than 50 characters. Example: <code>z</code></p>
        </div>
        </form>

                    <h2 id="push-devices-POSTapi-v1-push-devices-unregister">Unregister a device token</h2>

<p>
</p>

<p>Marks a device as inactive so it stops receiving notifications.</p>

<span id="example-requests-POSTapi-v1-push-devices-unregister">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://127.0.0.1:8000/api/v1/push/devices/unregister" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --header "X-API-KEY: FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN" \
    --data "{
    \"provider\": \"fcm\",
    \"token\": \"b\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://127.0.0.1:8000/api/v1/push/devices/unregister"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "X-API-KEY": "FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN",
};

let body = {
    "provider": "fcm",
    "token": "b"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-push-devices-unregister">
</span>
<span id="execution-results-POSTapi-v1-push-devices-unregister" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-push-devices-unregister"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-push-devices-unregister"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-push-devices-unregister" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-push-devices-unregister">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-push-devices-unregister" data-method="POST"
      data-path="api/v1/push/devices/unregister"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-push-devices-unregister', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-push-devices-unregister"
                    onclick="tryItOut('POSTapi-v1-push-devices-unregister');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-push-devices-unregister"
                    onclick="cancelTryOut('POSTapi-v1-push-devices-unregister');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-push-devices-unregister"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/push/devices/unregister</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-push-devices-unregister"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-push-devices-unregister"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-API-KEY</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-API-KEY"                data-endpoint="POSTapi-v1-push-devices-unregister"
               value="FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN"
               data-component="header">
    <br>
<p>Example: <code>FMfcgzQfCMsttJHqsljxCcRXXfdTvGrN</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>provider</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider"                data-endpoint="POSTapi-v1-push-devices-unregister"
               value="fcm"
               data-component="body">
    <br>
<p>Example: <code>fcm</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>fcm</code></li> <li><code>onesignal</code></li> <li><code>custom</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-v1-push-devices-unregister"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 1024 characters. Example: <code>b</code></p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
