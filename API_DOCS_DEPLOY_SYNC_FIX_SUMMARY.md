# API docs deploy & sync fix summary (v1)

This file explains why the live docs at `https://portal.naraboxtv.com/docs/api/v1` still looked stale after local fixes, and what is required to keep them in sync with the backend source.

## 1. What was stale

On the production host (Hostinger), the docs still showed:

- Generic endpoint labels in the sidebar, such as:
  - `GET api/v1/movies/{id}`
  - `GET api/v1/tv-shows`
  - `GET api/v1/tv-shows/{id}`
  - `GET api/v1/vjs/{id}`
  - `GET api/v1/articles/{id}`
  - `GET api/v1/actors/trending`
  - `GET api/v1/actors/{id}`
- Payment polling entries with duplicated wording.
- Auth endpoints with a baked-in “Request failed with error / enabled CORS” style block, making the API look broken.

Locally, however:

- The `.scribe/endpoints/00.yaml` file shows correct, human-readable titles (`List movies`, `Get movie details`, etc.).
- The Scribe HTML view (`resources/views/scribe/index.blade.php`) is generated from that YAML.
- Configs for CORS and Scribe headers (including `X-API-KEY`) are up to date.

This indicates the **live docs on Hostinger were still using an older Scribe build** produced before these changes.

## 2. Root causes

There are two main sources of staleness:

1. **Scribe artifacts not regenerated on production**
   - Scribe’s docs are generated, not dynamic: it writes to:
     - `.scribe/endpoints/*.yaml` (raw endpoint metadata)
     - `resources/views/scribe/*.blade.php` (the rendered docs)
     - `public/vendor/scribe/*` (assets)
     - `storage/app/scribe/openapi.yaml` and `storage/app/scribe/collection.json`
   - The updated controllers and configs were applied locally, and Scribe was regenerated here, but the Hostinger instance was still serving the older generated Blade + YAML files.

2. **Try-It-Out base URL baked in at generation time**
   - In `resources/views/scribe/index.blade.php`, Scribe hardcodes:

     ```html
     <script>
         var tryItOutBaseUrl = "http://127.0.0.1:8000";
     </script>
     ```

   - That value comes from `config('scribe.base_url')`, which in turn uses `config('app.url')` at **generation time**.
   - On the dev machine, `APP_URL` was `http://127.0.0.1:8000`, so Try-It-Out in the generated docs pointed back to localhost instead of `https://portal.naraboxtv.com`, causing “request failed” messages when hosted on Hostinger.

In short: **the live docs are stale because Scribe was regenerated locally with dev settings and the generated assets were never rebuilt (or re-run) on the production host.**

## 3. Files/configs that matter

To update docs correctly, production must have:

- Up-to-date **source code**:
  - Controllers under `app/Http/Controllers/Api/*` with rich docblocks and `@group`/`@bodyParam`/`@urlParam` annotations.
  - `config/scribe.php` (base URL, headers including `X-API-KEY`).
  - `app/Http/Middleware/HandleCors.php` and `config/cors.php` for CORS/headers.
- Up-to-date **Scribe outputs**:
  - `.scribe/endpoints/*.yaml`
  - `resources/views/scribe/*.blade.php`
  - `public/vendor/scribe/*`
  - `storage/app/scribe/openapi.yaml` and `storage/app/scribe/collection.json`

If only the controllers/configs are deployed, but Scribe isn’t re-run on that host (or the generated files aren’t copied), the docs UI will still show the old titles, examples, and Try-It-Out configuration.

## 4. Correct regeneration process on production

On the production server (Hostinger), after deploying code changes:

1. Ensure `.env` has correct values:

```env
APP_URL=https://portal.naraboxtv.com
APP_API_KEY=...your app key...
APP_API_KEY_ENABLED=true
```

2. Clear caches (optional but recommended after config/view changes):

```bash
php artisan optimize:clear
```

3. Regenerate Scribe docs **on that server**:

```bash
php artisan scribe:generate

mkdir -p storage/app/scribe
cp storage/app/private/scribe/openapi.yaml storage/app/scribe/openapi.yaml
cp storage/app/private/scribe/collection.json storage/app/scribe/collection.json

# Optional: keep a Git-tracked copy of the OpenAPI spec in production deploys
cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
```

This will:

- Rebuild `.scribe/endpoints/*.yaml` with the latest titles/descriptions.
- Rebuild `resources/views/scribe/index.blade.php` and other views.
- Embed the correct `tryItOutBaseUrl` based on `APP_URL`/`scribe.base_url`.

If you cannot run Artisan on Hostinger, you must instead:

- Run the above commands on a local environment that mimics production (`APP_URL=https://portal.naraboxtv.com`).
- Then deploy/sync the generated files to Hostinger:
  - `.scribe/**`
  - `resources/views/scribe/**`
  - `public/vendor/scribe/**`
  - `storage/app/scribe/openapi.yaml`
  - `storage/app/scribe/collection.json`

## 5. How to verify the live docs are updated

After regenerating (and deploying any generated files if needed):

1. Visit `https://portal.naraboxtv.com/docs/api/v1`.
2. Check the sidebar for:
   - “List TV Shows”, “Get TV Show Details”, “Get Movie Details”, “Get VJ Profile”, “Get Article Details”, “List Trending Actors”, “Get Actor Details”.
   - Payment poll endpoints labelled distinctly as “Poll ioTec Pay payment status” and “Check PawaPay deposit status”.
3. Open an endpoint with Try-It-Out:
   - Confirm the requests go to `https://portal.naraboxtv.com/api/v1/...` (not `http://127.0.0.1:8000`).
   - Confirm the headers include `X-API-KEY: <APP_API_KEY>` by default.
4. Check `https://portal.naraboxtv.com/docs/api/v1.openapi` to ensure the OpenAPI spec matches the current codebase.

If any of these still look stale, it usually means:

- The host is still serving old Blade views (clear view cache), or
- The newly generated `.scribe` and `resources/views/scribe` files were not deployed.

## 6. Summary

- **What was stale:** The production docs were still using older, generated Scribe assets (YAML + Blade) created before the latest polish, and Try-It-Out was still pointed at `http://127.0.0.1:8000`.
- **Root cause:** Scribe docs were regenerated locally, but not on the production host, and the generated assets were not fully deployed/synced.
- **Fix:** Run `php artisan scribe:generate` (and copy OpenAPI/Postman files) on production with the correct `APP_URL` and `APP_API_KEY`, or deploy the generated `.scribe`, `resources/views/scribe`, `public/vendor/scribe`, and `storage/app/scribe` artifacts from a correctly configured build environment.
- **Verification:** Use the sidebar titles, payment status endpoint names, Try-It-Out base URL, and the OpenAPI spec at `/docs/api/v1.openapi` to confirm the live docs are in sync with the backend implementation.

