# Regenerating API documentation

The API docs are generated with [Scribe](https://scribe.knuckles.wtf/laravel/). After changing routes, controllers, or request/response shapes, regenerate so the docs and OpenAPI spec stay in sync.

## Commands

```bash
# From project root (naraboxt-lara)
php artisan scribe:generate
```

This will:

1. Scan routes matching `api/v1/*` (excluding internal/webhook/worker routes; see `config/scribe.php`).
2. Extract metadata, parameters, and responses (including from docblocks and attributes).
3. Write Blade views to `resources/views/scribe/`.
4. Write assets to `public/vendor/scribe/`.
5. Write the Postman collection and OpenAPI spec to `storage/app/private/scribe/` (Scribe 5.x) or `storage/app/scribe/` (depending on version).

## Serving OpenAPI and Postman

- **HTML docs:** Served at **/docs/api/v1** (configured in `config/scribe.php` under `laravel.docs_url`).
- **OpenAPI YAML:** Served at **/docs/api/v1.openapi**. The route reads from `Storage::disk('local')->path('scribe/openapi.yaml')` (i.e. `storage/app/scribe/openapi.yaml`).
- **Postman collection:** Served at **/docs/api/v1.postman**.

If your Scribe version writes to `storage/app/private/scribe/` instead of `storage/app/scribe/`, copy the generated files so the routes work:

```bash
mkdir -p storage/app/scribe
cp storage/app/private/scribe/openapi.yaml storage/app/scribe/
cp storage/app/private/scribe/collection.json storage/app/scribe/
```

## Committing the OpenAPI spec

To keep a versioned copy of the spec in the repo:

```bash
cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
# or, if Scribe wrote to storage/app/scribe:
cp storage/app/scribe/openapi.yaml docs/openapi.yaml
git add docs/openapi.yaml
git commit -m "chore: update OpenAPI spec"
```

## Config

- **config/scribe.php** — Base URL, title, description, intro, routes (prefixes, exclude), auth (Bearer), laravel.docs_url (`/docs/api/v1`), OpenAPI and Postman enabled, group order, etc.
- **Excluded routes** (not documented): `api/cdn/fetch-and-push`, `api/telegram/ingest-notify`, `api/v1/worker/sync`, Flutterwave/ioTec/PawaPay webhooks.

## Improving generated docs

- Add **@group** in controller docblocks to group endpoints (e.g. `@group Authentication`, `@group Movies`).
- Use **@bodyParam**, **@queryParam**, **@response** in docblocks for clearer examples.
- Set **groups.order** in `config/scribe.php` to control the order of groups and endpoints in the sidebar.

After editing annotations or config, run `php artisan scribe:generate` again.
