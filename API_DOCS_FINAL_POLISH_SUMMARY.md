# API docs final polish summary (v1)

This file records the final polish pass on the NaraBox TV Portal API v1 docs and interactive behavior.

## 1. Generic endpoint titles fixed

The underlying controllers already exposed clear group-level descriptions, but Scribe still surfaced some route-shaped titles (eg. `GET api/v1/movies/{id}`, `GET api/v1/tv-shows`, etc.) in the sidebar and endpoint cards. This pass ensures the first lines of each docblock clearly express the product intent, so Scribe uses them as human-readable titles.

Key endpoints polished:

- **Movies**
  - `GET /api/v1/movies` → **List movies** (with filters and sorting).
  - `GET /api/v1/movies/{id}` → **Get movie details** (by id or slug).
  - `GET /api/v1/movies/selected-today` → **Get “selected today” movies**.
- **TV shows**
  - `GET /api/v1/tv-shows` → **List TV shows**.
  - `GET /api/v1/tv-shows/{id}` → **Get TV show details**.
- **VJs**
  - `GET /api/v1/vjs` → **List VJs** (translators/presenters).
  - `GET /api/v1/vjs/{id}` → **Get VJ profile and catalog**.
- **Articles**
  - `GET /api/v1/articles` → **List articles/news**.
  - `GET /api/v1/articles/{id}` → **Get article details**.
- **Actors**
  - `GET /api/v1/actors` → **List actors** (with search, trending).
  - `GET /api/v1/actors/{id}` → **Get actor details and filmography**.

These labels now appear consistently in the Scribe HTML docs, OpenAPI spec, and Postman collection, replacing the generic `GET api/v1/...` forms wherever Scribe uses the docblock summaries.

## 2. Payment poll endpoint summaries de-duplicated

The payments area had multiple “poll status” endpoints whose descriptions were previously too similar. These were clarified so frontend developers and AI tools can clearly distinguish the flows:

- **ioTec Pay**
  - `POST /api/v1/iotec/initiate` → “Initiate ioTec Pay collection (phone prompt, in-site)”.
  - `POST /api/v1/iotec/status` / `GET /api/v1/iotec/status` → **“Poll ioTec Pay payment status”**, with explicit normalized statuses: `success`, `failed`, `pending` and their meaning.
- **PawaPay**
  - `POST /api/v1/payments/pawapay/deposit/initiate` → “Initiate PawaPay deposit” (mobile money deposit for rent/buy/subscription).
  - `GET /api/v1/payments/pawapay/deposit/{depositId}/status` → **“Check PawaPay deposit status”**, with normalized statuses: `COMPLETED`, `FAILED`, `PENDING`.

Each endpoint’s summary and detailed description are now distinct and product-oriented, avoiding duplicate “Poll payment status” phrasing.

## 3. Try-It-Out behavior and CORS / base URL

The docs are served at `/docs/api/v1` on the same host as the API (eg. `https://portal.naraboxtv.com`), so Try-It-Out calls are **same-origin** and should not normally require CORS. However:

- A custom `HandleCors` middleware and `config/cors.php` configuration are in place.
- CORS rules previously only allowed localhost origins and `naraboxtv.com`, but not `portal.naraboxtv.com`.
- Scribe’s Try-It-Out requests did **not** include the app API key (`X-API-KEY`), which caused 401-style errors when hitting `/api/v1/*`.

To make the interactive docs reliable:

1. **CORS / origin updates**
   - `config/cors.php` already allows:
     - `https://naraboxtv.com`, `https://www.naraboxtv.com`.
   - `App\Http\Middleware\HandleCors` was extended to treat the following as allowed origins for both preflight and normal responses:
     - `https://naraboxtv.com`
     - `https://www.naraboxtv.com`
     - `https://portal.naraboxtv.com`
   - Pattern checks now also accept `https?://(www\.)?naraboxtv\.com` and `https?://portal\.naraboxtv\.com`, so browser-originating requests from the docs UI are not blocked.

2. **API key header in Try-It-Out**
   - Scribe’s `headers` strategy in `config/scribe.php` was updated to include:

     ```php
     Strategies\StaticData::withSettings(data: [
         'Content-Type' => 'application/json',
         'Accept' => 'application/json',
         'X-API-KEY' => env('APP_API_KEY', ''),
     ]),
     ```

   - This means Try-It-Out requests automatically include the correct `X-API-KEY` header in each environment, assuming `APP_API_KEY` is set in `.env`.
   - Authenticated endpoints still require Bearer tokens; Scribe’s `auth` config (using `SCRIBE_AUTH_KEY`) controls that behavior when “Use auth” is enabled in the docs UI.

With these changes, the interactive examples should no longer fail with CORS/network-style errors and instead return real API responses (401/403/2xx as appropriate) from the same origin as the docs.

## 4. Regenerating docs and verifying output

After polishing titles, payment summaries, CORS, and headers, Scribe was regenerated and artifacts were copied to the expected locations:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/naraboxt-lara

php artisan scribe:generate

mkdir -p storage/app/scribe
cp storage/app/private/scribe/openapi.yaml storage/app/scribe/openapi.yaml
cp storage/app/private/scribe/collection.json storage/app/scribe/collection.json

# Versioned copy of the OpenAPI spec for Git
cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
```

The docs remain available at:

- HTML docs: `/docs/api/v1`
- OpenAPI spec: `/docs/api/v1.openapi`

## 5. Remaining limitations / notes

- **Auth for Try-It-Out:** The app API key is now automatically included, but user-level Bearer tokens still need to be provided (or `SCRIBE_AUTH_KEY` set) for protected endpoints to succeed interactively.
- **Environment-specific APP_URL:** Scribe uses `config('app.url')` as the base URL. Ensure `APP_URL` is set correctly (with `https://portal.naraboxtv.com` in production) so any absolute URLs in docs or generated clients match the deployed host.
- **Provider wiring for push/payments:** The docs accurately describe the current behavior; FCM/OneSignal and any additional payment provider hooks still need environment-specific credentials and may require further server-side integration beyond what’s documented here.

With this pass, the docs surface **product-oriented endpoint names**, distinct payment poll descriptions, a more robust Try-It-Out experience (including API key headers and CORS allowances), and a clear regeneration workflow for keeping `/docs/api/v1` and the OpenAPI spec in sync with future backend changes.

