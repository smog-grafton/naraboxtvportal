# API documentation implementation summary

This document summarizes the API documentation system added to the NaraBox TV Portal (naraboxt-lara) and how to use and maintain it.

---

## 1. What was found in the codebase

### Project and database

- **Laravel 12** app with **Filament** admin; **Sanctum** for API auth.
- **Database:** `naraboxt-lara`. Main tables: `users`, `movies`, `tv_shows`, `seasons`, `episodes`, `vjs`, `actors`, `genres`, `categories`, `hero_slides`, `articles`, `live_streams`, `video_sources`, `download_sources`, `subtitles`, `subscription_plans`, `user_subscriptions`, `user_rentals`, `user_purchases`, `payment_transactions`, `payment_gateways`, `watch_history`, `comments`, `contact_messages`, plus supporting tables (trailers, crews, keywords, telegram_imports, etc.).

### API routes

- All public API under **`/api/v1/`** (defined in `routes/api.php`).
- **Public:** Hero, movies, TV shows, search, VJs, articles, contact, live streams, actors, player, downloads, payment gateways, subscription plans, comments (read), access check, views track.
- **Protected (auth:sanctum):** Auth (me, profile, logout, delete account), dashboard, payments (initiate, upload proof, verify), Flutterwave/ioTec/PawaPay payment endpoints, watch history, video/subtitle fetch (admin), comments (write).
- **Internal / excluded from docs:** `POST /api/cdn/fetch-and-push`, `POST /api/telegram/ingest-notify`, `POST /api/v1/worker/sync`, Flutterwave/ioTec/PawaPay webhooks.

### Authentication

- **Laravel Sanctum.** Token from `POST /api/v1/auth/login` or `POST /api/v1/auth/register`; sent as `Authorization: Bearer <token>`.
- Optional: Google OAuth; email verification; password reset.

### Core domains identified

- **Authentication** — Register, login, profile, OAuth, verification, password reset.
- **Content** — Hero, movies, TV shows (with seasons/episodes), search, VJs, articles, live streams, actors.
- **Playback** — Player (manifest with video URLs, HLS, subtitles, download sources), access check, downloads.
- **Monetization** — Subscription plans, payment gateways, payments (initiate, proof, verify), Flutterwave/ioTec/PawaPay.
- **User** — Dashboard (subscription, rentals, purchases, transactions), watch history, comments.

---

## 2. What was installed and configured

### Scribe

- **Package:** `knuckleswtf/scribe` (dev).
- **Config:** `config/scribe.php`.
- **Type:** `laravel` (Blade views served by the app).
- **Docs URL:** `/docs/api/v1` (so production: `https://portal.naraboxtv.com/docs/api/v1`).
- **Routes documented:** `api/v1/*` only; internal and webhook routes excluded in config.
- **Auth in docs:** Bearer token; placeholder and extra info point to login/register.
- **OpenAPI:** Enabled; generated to `storage/app/private/scribe/openapi.yaml` (Scribe 5.x). A copy is kept in `storage/app/scribe/openapi.yaml` so the Scribe route can serve it, and a versioned copy in `docs/openapi.yaml`.
- **Postman:** Enabled; generated to `storage/app/private/scribe/collection.json`; served at `/docs/api/v1.postman`.
- **Group order:** Configured so Authentication, Hero, Movies, TV Shows, Search, VJs, Articles, Contact, Live Streams, Actors, Player & Downloads, Access & Views, Subscription plans, Payments, Dashboard & Watch history, Comments appear in a logical order.

### Controller annotations

- **@group** (and short descriptions) added to **all** public API controllers: AuthController, HeroController, MovieController, TVShowController, SearchController, VJController, ArticleController, ContactController, LiveStreamController, ActorController, PlayerController, DownloadController, AccessController, ViewController, SubscriptionController, PaymentController, FlutterwaveController, IoTeCController, PawaPayController, DashboardController, CommentController. Internal controllers (CdnFetchProxy, TelegramIngestNotify, WorkerSync, etc.) are excluded from Scribe.

---

## 3. Where generated files live

| What | Location |
|------|----------|
| **HTML docs (Blade)** | `resources/views/scribe/` |
| **Scribe assets** | `public/vendor/scribe/` |
| **OpenAPI (Scribe output)** | `storage/app/private/scribe/openapi.yaml` |
| **OpenAPI (served by route)** | `storage/app/scribe/openapi.yaml` (copy) |
| **OpenAPI (versioned)** | `docs/openapi.yaml` |
| **Postman collection** | `storage/app/private/scribe/collection.json`; served via route from `storage/app/scribe/collection.json` if you copy it there |

---

## 4. How the `/docs/api/v1` route works

- Scribe registers **web** routes when `config('scribe.laravel.add_routes')` is true:
  - **GET /docs/api/v1** → Blade view `scribe.index` (HTML docs).
  - **GET /docs/api/v1.openapi** → Serves `storage/app/scribe/openapi.yaml`.
  - **GET /docs/api/v1.postman** → Serves `storage/app/scribe/collection.json` (JSON).
- No auth middleware on these routes by default; you can add middleware in `config/scribe.php` under `laravel.middleware` to restrict access in production if desired.
- **Versioning:** The path `/docs/api/v1` allows adding `/docs/api/v2` later (e.g. by changing Scribe config or adding a second docs app).

---

## 5. How to regenerate docs

1. **Regenerate:**  
   ```bash
   php artisan scribe:generate
   ```

2. **If Scribe writes only to `storage/app/private/scribe/`:**  
   Copy so the routes work and the repo has an OpenAPI copy:  
   ```bash
   mkdir -p storage/app/scribe
   cp storage/app/private/scribe/openapi.yaml storage/app/scribe/
   cp storage/app/private/scribe/collection.json storage/app/scribe/
   cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
   ```

3. **Commit (optional):**  
   ```bash
   git add docs/openapi.yaml
   git commit -m "chore: update OpenAPI spec"
   ```

See **docs/REGENERATE.md** for full steps and config notes.

---

## 6. Manual docs added (docs/ folder)

| File | Purpose |
|------|---------|
| **docs/API_OVERVIEW.md** | High-level API overview, domains, and audience. |
| **docs/AUTHENTICATION.md** | How to obtain and use Bearer tokens; protected vs optional auth. |
| **docs/DOMAIN_MODEL.md** | Entities and relationships (movies, TV shows, VJs, access, payments, etc.). |
| **docs/PLAYBACK_FLOW.md** | How to get playback (access check → player → watch history, downloads). |
| **docs/MEDIA_PIPELINE.md** | How media is ingested and how playback URLs are produced (for context). |
| **docs/UPLOAD_AND_IMPORT_FLOW.md** | Upload/import from operator side; what is public vs internal. |
| **docs/ERRORS_AND_STATUS_CODES.md** | HTTP status codes and error response shapes. |
| **docs/FRONTEND_INTEGRATION_GUIDE.md** | Step-by-step flows: homepage, detail, watch, VJ, search, subscription/rental, profile, comments. |
| **docs/REGENERATE.md** | How to regenerate Scribe docs and OpenAPI. |
| **docs/openapi.yaml** | Versioned OpenAPI spec (copied after each generation). |
| **docs/DATABASE_SCHEMA.md** | Full database (naraboxt-lara) table list and key columns; relationships for API consumers. |
| **docs/PAYMENT_GATEWAYS.md** | payment_gateways and payment_transactions schema; gateway types (AUTOMATIC/MANUAL); Flutterwave, ioTec, PawaPay, manual flows with **code samples** (cURL, JavaScript). |
| **docs/API_REFERENCE_WITH_CODE_SAMPLES.md** | **Every documented endpoint** with cURL and JavaScript (fetch) examples; request/response notes; quick reference table. For mobile apps, AI, and developers. |
| **docs/CONTROLLERS_INDEX.md** | Index of **all API controllers**: route(s), purpose, and which are public vs internal. |

These are written for **humans, AI, and mobile developers**: workflows, entities, code samples, and which controller backs which endpoint.

---

## 7. Assumptions and limitations

- **Docs route:** Assumes the app is served with the same base URL for web and API (e.g. `https://portal.naraboxtv.com`). OpenAPI/Postman base URL comes from `config('app.url')` unless overridden in Scribe.
- **OpenAPI serving:** Scribe’s route uses `Storage::disk('local')->path('scribe/openapi.yaml')`. If your Scribe version only writes to `private/scribe`, you must copy to `scribe/` (or change the route) so `/docs/api/v1.openapi` works.
- **Production:** No middleware is applied to the docs routes by default. To restrict access in production, set `laravel.middleware` in `config/scribe.php` (e.g. to an auth or IP middleware).
- **Internal endpoints:** CDN fetch, Telegram ingest, worker sync, and payment webhooks are excluded from Scribe and from the OpenAPI spec on purpose; they are not part of the public API contract.
- **Try It Out:** Scribe’s “Try It Out” works only if CORS allows requests from the docs origin to your API. Your app already uses `HandleCors`; ensure your frontend/docs domain is allowed if you use a different host for docs.

---

## 8. Quick reference

- **View docs:** Open `/docs/api/v1` in the browser (e.g. `https://portal.naraboxtv.com/docs/api/v1`).
- **OpenAPI:** `/docs/api/v1.openapi` or repo file `docs/openapi.yaml`.
- **Postman:** `/docs/api/v1.postman`.
- **Workflow and domain docs:** Read the markdown files in `docs/` (API_OVERVIEW, AUTHENTICATION, DOMAIN_MODEL, PLAYBACK_FLOW, FRONTEND_INTEGRATION_GUIDE, etc.).
- **Regenerate:** `php artisan scribe:generate` then copy OpenAPI/Postman as in **docs/REGENERATE.md**.

This setup gives you a single place for API reference (Scribe + OpenAPI), versioned OpenAPI in the repo, and workflow-oriented docs suitable for AI designers and frontend developers.
