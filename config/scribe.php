<?php

use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\configureStrategy;
use function Knuckles\Scribe\Config\removeStrategies;

// Only the most common configs are shown. See the https://scribe.knuckles.wtf/laravel/reference/config for all.

return [
    // The HTML <title> for the generated documentation.
    'title' => 'NaraBox TV Portal API v1',

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => 'REST API for the NaraBox TV streaming portal. Powers the Next.js frontend with content (movies, TV shows, VJs), authentication, playback, subscriptions, rentals, and payments.',

    // Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
    'intro_text' => <<<'INTRO'
            This documentation describes the **NaraBox TV Portal API v1**. Use it to build or integrate with the portal frontend (e.g. Next.js or mobile apps), understand workflows (homepage, watch page, dashboard, payments), and consume playback and access data.
        
            **Base path:** All documented endpoints are under `/api/v1/`.
        
            **Headers:** All app requests should send:
        
            - `X-API-KEY: <APP_API_KEY>` (configured via `APP_API_KEY` / `APP_API_KEY_ENABLED`)
            - `Authorization: Bearer {token}` on protected endpoints (token from auth flows).
        
            **Authentication:** Most listing and playback endpoints are public (API key only). Protected endpoints (dashboard, payments, watch history, profile, push device registration) also require a Bearer token from `POST /api/v1/auth/login`, `POST /api/v1/auth/register`, phone OTP, Google, or Apple login flows.
        
            <aside>As you scroll, you'll see code examples for working with the API. You can switch the language with the tabs. Use "Try It Out" to call endpoints from the docs (CORS permitting).</aside>
        
            **Workflow and domain docs** (in the repo): See the `docs/` folder for API_OVERVIEW.md, AUTHENTICATION.md, DOMAIN_MODEL.md, PLAYBACK_FLOW.md, FRONTEND_INTEGRATION_GUIDE.md, PUSH_NOTIFICATIONS.md, AD_BANNERS.md, API_SECURITY.md, and related guides for AI designers and frontend developers.
        
            **Regenerating docs:** After changing controllers or routes, run:
        
            ```bash
            php artisan scribe:generate
            mkdir -p storage/app/scribe
            cp storage/app/private/scribe/openapi.yaml storage/app/scribe/openapi.yaml
            cp storage/app/private/scribe/collection.json storage/app/scribe/collection.json
            cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
            ```
        
            Then visit `/docs/api/v1` to view the updated docs and `/docs/api/v1.openapi` to fetch the OpenAPI spec.
        INTRO,

    // The base URL displayed in the docs.
    'base_url' => config('app.url'),

    // Routes to include in the docs (only public-facing v1 API; exclude internal/webhook/worker routes)
    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/v1/*'],
                'domains' => ['*'],
            ],
            'include' => [],
            'exclude' => [
                'POST api/cdn/fetch-and-push',
                'POST api/telegram/ingest-notify',
                'POST api/v1/worker/sync',
                'POST api/v1/flutterwave/webhook',
                'POST api/v1/iotec/webhook',
                'POST api/v1/webhooks/pawapay/deposits',
                'POST api/v1/webhooks/pawapay/refunds',
            ],
        ],
    ],

    // The type of documentation output to generate.
    // - "static" will generate a static HTMl page in the /public/docs folder,
    // - "laravel" will generate the documentation as a Blade view, so you can add routing and authentication.
    // - "external_static" and "external_laravel" do the same as above, but pass the OpenAPI spec as a URL to an external UI template
    'type' => 'laravel',

    // See https://scribe.knuckles.wtf/laravel/reference/config#theme for supported options
    'theme' => 'default',

    'static' => [
        // HTML documentation, assets and Postman collection will be generated to this folder.
        // Source Markdown will still be in resources/docs.
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        // Docs at /docs/api/v1 for versioning (future: /docs/api/v2).
        'docs_url' => '/docs/api/v1',

        'assets_directory' => null,

        // No auth required for docs; optional: add middleware to restrict in production.
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        // Add a Try It Out button to your endpoints so consumers can test endpoints right from their browser.
        // Don't forget to enable CORS headers for your endpoints.
        'enabled' => true,

        // The base URL to use in the API tester. Leave as null to be the same as the displayed URL (`scribe.base_url`).
        'base_url' => null,

        // [Laravel Sanctum] Fetch a CSRF token before each request, and add it as an X-XSRF-TOKEN header.
        'use_csrf' => false,

        // The URL to fetch the CSRF token from (if `use_csrf` is true).
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    // API uses Laravel Sanctum: Bearer token from login/register.
    'auth' => [
        'enabled' => true,
        'default' => false,

        'in' => AuthIn::BEARER->value,
        'name' => 'Authorization',

        'use_value' => env('SCRIBE_AUTH_KEY'),

        'placeholder' => '{YOUR_AUTH_TOKEN}',

        'extra_info' => 'Obtain a token via <code>POST /api/v1/auth/login</code> or <code>POST /api/v1/auth/register</code>. Send it as <code>Authorization: Bearer {token}</code> on protected endpoints.',
    ],

    // Example requests for each endpoint will be shown in each of these languages.
    // Supported options are: bash, javascript, php, python
    // To add a language of your own, see https://scribe.knuckles.wtf/laravel/advanced/example-requests
    // Note: does not work for `external` docs types
    'example_languages' => [
        'bash',
        'javascript',
    ],

    // Generate a Postman collection (v2.1.0) in addition to HTML docs.
    // For 'static' docs, the collection will be generated to public/docs/collection.json.
    // For 'laravel' docs, it will be generated to storage/app/scribe/collection.json.
    // Setting `laravel.add_routes` to true (above) will also add a route for the collection.
    'postman' => [
        'enabled' => true,

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    // Generate an OpenAPI spec in addition to docs webpage.
    // For 'static' docs, the collection will be generated to public/docs/openapi.yaml.
    // For 'laravel' docs, it will be generated to storage/app/scribe/openapi.yaml.
    // Setting `laravel.add_routes` to true (above) will also add a route for the spec.
    'openapi' => [
        'enabled' => true,

        // The OpenAPI spec version to generate. Supported versions: '3.0.3', '3.1.0'.
        // OpenAPI 3.1 is more compatible with JSON Schema and is becoming the dominant version.
        // See https://spec.openapis.org/oas/v3.1.0 for details on 3.1 changes.
        'version' => '3.0.3',

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],

        // Additional generators to use when generating the OpenAPI spec.
        // Should extend `Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator`.
        'generators' => [],
    ],

    'groups' => [
        // Endpoints which don't have a @group will be placed in this default group.
        'default' => 'Endpoints',

        // Order groups for clearer docs (Authentication first, then content, then payments).
        'order' => [
            'Authentication',
            'Hero',
            'Movies',
            'TV Shows',
            'Search',
            'VJs',
            'Articles',
            'Contact',
            'Live Streams',
            'Actors',
            'Player & Downloads',
            'Access & Views',
            'Subscription plans',
            'Payments',
            'Dashboard & Watch history',
            'Comments',
        ],
    ],

    // Custom logo path. This will be used as the value of the src attribute for the <img> tag,
    // so make sure it points to an accessible URL or path. Set to false to not use a logo.
    // For example, if your logo is in public/img:
    // - 'logo' => '../img/logo.png' // for `static` type (output folder is public/docs)
    // - 'logo' => 'img/logo.png' // for `laravel` type
    'logo' => false,

    // Customize the "Last updated" value displayed in the docs by specifying tokens and formats.
    // Examples:
    // - {date:F j Y} => March 28, 2022
    // - {git:short} => Short hash of the last Git commit
    // Available tokens are `{date:<format>}` and `{git:<format>}`.
    // The format you pass to `date` will be passed to PHP's `date()` function.
    // The format you pass to `git` can be either "short" or "long".
    // Note: does not work for `external` docs types
    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        // Set this to any number to generate the same example values for parameters on each run,
        'faker_seed' => 1234,

        // With API resources and transformers, Scribe tries to generate example models to use in your API responses.
        // By default, Scribe will try the model's factory, and if that fails, try fetching the first from the database.
        // You can reorder or remove strategies here.
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    // The strategies Scribe will use to extract information about your routes at each stage.
    // Use configureStrategy() to specify settings for a strategy in the list.
    // Use removeStrategies() to remove an included strategy.
    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                // Include app API key in Try-It-Out requests so interactive docs work out of the box.
                'X-API-KEY' => env('APP_API_KEY', ''),
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,
            Strategies\Responses\ResponseCalls::withSettings(
                only: ['GET *'],
                // Recommended: disable debug mode in response calls to avoid error stack traces in responses
                config: [
                    'app.debug' => false,
                ]
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    // For response calls, API resource responses and transformer responses,
    // Scribe will try to start database transactions, so no changes are persisted to your database.
    // Tell Scribe which connections should be transacted here. If you only use one db connection, you can leave this as is.
    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        // If you are using a custom serializer with league/fractal, you can specify it here.
        'serializer' => null,
    ],
];
