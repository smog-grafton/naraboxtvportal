# NaraBox TV Portal API — Overview

This document gives a high-level overview of the NaraBox TV Portal API for frontend developers and AI-assisted UI design.

## Base URL and versioning

- **Base path:** All endpoints live under `/api/v1/`.
- **Documentation:** Scribe-generated docs: `/docs/api/v1`. OpenAPI spec: `/docs/api/v1.openapi` or `docs/openapi.yaml` in the repo.
- **Versioning:** The path prefix `v1` allows future `v2` without breaking existing clients.

## Audience

- **Next.js frontend** (portal) consuming this API for all data and actions.
- **AI UI generators** that need to understand entities, workflows, and which endpoints power which pages.
- **Integrators** (mobile apps, third-party UIs) that need a stable, documented API.

## Main domains

| Domain | Purpose | Key endpoints |
|--------|---------|----------------|
| **Authentication** | Register, login, profile, OAuth, password reset | `POST /auth/register`, `POST /auth/login`, `GET /auth/me`, `PUT /auth/profile` |
| **Hero / Homepage** | Carousel and featured content | `GET /hero` |
| **Movies** | List and detail; filter by category, genre, VJ, free/premium/rent/buy | `GET /movies`, `GET /movies/selected-today`, `GET /movies/{id}` |
| **TV Shows** | List and detail; seasons and episodes | `GET /tv-shows`, `GET /tv-shows/{id}` |
| **Search** | Global search (archives, people, intel) | `GET /search?q=` |
| **VJs** | Translators/presenters; their catalog | `GET /vjs`, `GET /vjs/{id}` |
| **Articles / News** | Editorial content | `GET /articles`, `GET /articles/{id}` |
| **Live streams** | Live streaming channels | `GET /live-streams`, `GET /live-streams/{id}` |
| **Actors** | Cast; trending | `GET /actors`, `GET /actors/trending`, `GET /actors/{id}` |
| **Player** | Playback manifest (video URLs, HLS, subtitles, downloads) | `GET /player/{id}?media_type=&episode=` |
| **Access** | Check if user can play a title (free/subscription/rent/buy) | `POST /access/check` |
| **Downloads** | Download file (with access control) | `GET /downloads/{id}` |
| **Subscription plans** | List and detail plans | `GET /subscription-plans`, `GET /subscription-plans/{id}` |
| **Payments** | Initiate, upload proof, verify; gateways (Flutterwave, ioTec, PawaPay) | `POST /payments/initiate`, `POST /payments/verify`, etc. |
| **Dashboard** | User summary: subscription, rentals, purchases, watch history | `GET /dashboard` (auth) |
| **Watch history** | Progress and resume | `POST /watch-history`, `GET /watch-history` (auth) |
| **Comments** | Per-media comments; like/delete (auth for write) | `GET /comments/{mediaId}`, `POST /comments`, etc. |
| **Contact** | Contact form | `POST /contact` |
| **Views** | Track play views | `POST /views/track` |

## Authentication

- **Mechanism:** Laravel Sanctum. Token from `POST /api/v1/auth/login` or `POST /api/v1/auth/register`.
- **Header:** `Authorization: Bearer <token>`.
- **Public vs protected:** Listing, hero, search, player (for free content), access check, subscription plans, payment gateways, and comments (read) are public. Dashboard, profile, payments (initiate/verify), watch history, and comments (write) require auth.
- **Email verification:** Some payment flows require verified email (middleware `email.verified`).

See [AUTHENTICATION.md](AUTHENTICATION.md) for details.

## Content and access model

- **Movies and TV shows** can be:
  - **Free** — no login required to play.
  - **Premium** — requires an active subscription.
  - **Rent/Buy** — one-off rental or purchase (no subscription).
- **Access order:** Free → Subscription (for premium) → Purchase → Rental → Pending payment. Use `POST /access/check` before showing the player or paywall.

See [DOMAIN_MODEL.md](DOMAIN_MODEL.md) and [PLAYBACK_FLOW.md](PLAYBACK_FLOW.md).

## Frontend-oriented workflows

- **Build homepage:** `GET /hero`, optional `GET /movies?filter=featured`, `GET /movies?filter=trending`, etc.
- **Build movie/TV detail page:** `GET /movies/{id}` or `GET /tv-shows/{id}`; then `POST /access/check` to decide play vs paywall.
- **Build watch page:** `GET /player/{id}?media_type=MOVIE|TV_SHOW&episode={id}`; use `videoUrl`, `videoSources`, `subtitles`, `playback`; optionally `POST /watch-history` (auth).
- **Build VJ/catalog page:** `GET /vjs`, `GET /vjs/{id}`; movies by VJ: `GET /movies?vj={slug|id}`.
- **Build subscription/rental UI:** `GET /subscription-plans`, `GET /dashboard` (auth); payments via `POST /payments/initiate`, gateway-specific endpoints, then `POST /payments/verify`.

See [FRONTEND_INTEGRATION_GUIDE.md](FRONTEND_INTEGRATION_GUIDE.md) for step-by-step flows.

## Errors and status codes

- **200** — Success.
- **201** — Created (e.g. register, comment).
- **401** — Unauthorized (missing or invalid token).
- **403** — Forbidden (e.g. no access to content).
- **404** — Not found (media, plan, etc.).
- **422** — Validation error; body includes `messages` or field errors.

See [ERRORS_AND_STATUS_CODES.md](ERRORS_AND_STATUS_CODES.md).

## Media pipeline (backend context)

Content is ingested via CDN/worker; the portal stores metadata and uses the CDN for playback manifests. See [MEDIA_PIPELINE.md](MEDIA_PIPELINE.md) and the project root [MEDIA_PIPELINE_OVERVIEW.md](../MEDIA_PIPELINE_OVERVIEW.md).
