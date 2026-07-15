# API controllers index

Every API controller in the NaraBox TV Portal, with route prefix **/api/v1/** and how it is used. Use this so AI and developers know which controller backs which endpoint.

---

## Public API controllers (documented in Scribe)

| Controller | Route(s) | Purpose |
|------------|----------|---------|
| **AuthController** | POST /auth/register, /auth/login, /auth/verify-email, /auth/resend-verification, /auth/forgot-password, /auth/reset-password, GET /auth/google/url, /auth/google, /auth/google/callback; **auth:** GET /auth/me, PUT /auth/profile, DELETE /auth/account, POST /auth/logout | Register, login, OAuth, profile, password reset, email verification. |
| **HeroController** | GET /hero | Homepage carousel slides (each slide = movie). |
| **MovieController** | GET /movies, /movies/selected-today, /movies/{id} | List movies (filter, sort), selected today, single movie by id/slug. |
| **TVShowController** | GET /tv-shows, /tv-shows/{id} | List TV shows, single show with seasons/episodes. |
| **SearchController** | GET /search?q= | Global search (archives, people, intel). |
| **VJController** | GET /vjs, /vjs/{id} | List VJs, single VJ with movies. |
| **ArticleController** | GET /articles, /articles/{id} | List articles (news), single article. |
| **ContactController** | POST /contact | Contact form (name, email, subject, message). |
| **LiveStreamController** | GET /live-streams, /live-streams/{id} | List live streams, single stream. |
| **ActorController** | GET /actors, /actors/trending, /actors/{id} | List actors, trending, single actor with movies. |
| **PlayerController** | GET /player/{id}?media_type=&episode= | Playback manifest (video URLs, HLS, subtitles, download sources). Optional auth. |
| **DownloadController** | GET /downloads/{id} | Download file; access control (free or purchase/rental/subscription). Optional auth or ?access_token=. |
| **AccessController** | POST /access/check | Check if user can play content (has_access, access_type, reason, prices). Optional auth. |
| **ViewController** | POST /views/track | Track play view (media_id, media_type). |
| **SubscriptionController** | GET /subscription-plans, /subscription-plans/{id} | List and get subscription plans. |
| **PaymentController** | GET /payment-gateways; **auth:** POST /payments/initiate, /payments/upload-proof, /payments/verify | Gateways list; initiate payment (rent/buy/subscription), upload proof (manual), verify. |
| **FlutterwaveController** | **auth:** POST /flutterwave/initiate, /flutterwave/verify | Flutterwave payment: initiate (returns link), verify by transaction_ref. |
| **IoTeCController** | **auth:** POST /iotec/initiate, GET|POST /iotec/status | ioTec Pay: initiate, poll status. |
| **PawaPayController** | **auth:** POST /payments/pawapay/deposit/initiate, GET /payments/pawapay/deposit/{depositId}/status | PawaPay deposit: initiate, check status. |
| **DashboardController** | GET /dashboard | User dashboard (subscription, rentals, purchases, transactions, watch history). **Auth.** |
| **PlayerController** (watch history) | POST /watch-history, GET /watch-history | Update and get watch progress. **Auth.** |
| **CommentController** | GET /comments/{mediaId}; **auth:** POST /comments, POST /comments/{id}/like, DELETE /comments/{id} | List comments; add (auth or anonymous), like, delete. |

---

## Internal / undocumented routes (excluded from Scribe)

These are **not** in the public API docs because they are for internal or machine use only:

| Controller | Route(s) | Purpose |
|------------|----------|---------|
| **CdnFetchProxyController** | POST /cdn/fetch-and-push | Internal: trigger CDN fetch. Throttled. |
| **TelegramIngestNotifyController** | POST /telegram/ingest-notify | Internal: Telegram ingest notification. Throttled. |
| **WorkerSyncController** | POST /v1/worker/sync | Worker sync (Bearer PORTAL_WORKER_API_TOKEN). |
| **FlutterwaveController** | POST /flutterwave/webhook | Flutterwave webhook (no auth; verify signature). |
| **IoTeCController** | POST /iotec/webhook | ioTec webhook. |
| **PawaPayController** | POST /webhooks/pawapay/deposits, /webhooks/pawapay/refunds | PawaPay webhooks. |
| **VideoFetchController** | POST /video/fetch | Admin: fetch video from URL. **Auth.** |
| **SubtitleFetchController** | POST /subtitle/fetch | Admin: fetch subtitle from URL. **Auth.** |

---

## File locations

- All API controllers: `app/Http/Controllers/Api/`.
- Routes: `routes/api.php` (prefix `api`, then v1 group).
- Auth: Laravel Sanctum; middleware `auth:sanctum`, `email.verified` (for payment flows).

---

## Code samples and full reference

- **Request/response samples:** See **API_REFERENCE_WITH_CODE_SAMPLES.md**.
- **Payment gateways and DB:** See **PAYMENT_GATEWAYS.md** and **DATABASE_SCHEMA.md**.
- **Scribe HTML + OpenAPI:** `/docs/api/v1` and `/docs/api/v1.openapi` (or `docs/openapi.yaml`).
