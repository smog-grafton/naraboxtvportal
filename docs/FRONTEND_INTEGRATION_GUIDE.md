# Frontend integration guide

This guide walks through building the main frontend experiences using the NaraBox TV Portal API. It is written for developers and AI UI generators.

## Base URL and headers

- **Base URL:** `https://portal.naraboxtv.com` (production) or your backend URL.
- **Prefix:** All API calls use `/api/v1/`.
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/json`
  - `X-API-KEY: <APP_API_KEY>` (all app requests)
  - For protected endpoints: `Authorization: Bearer <token>`

## 1. Homepage

**Goal:** Hero carousel + featured/trending content.

- **GET /api/v1/hero** — Returns an array of slide objects (title, description, thumbnail, backdrop, rating, releaseDate, category, mediaType, vj, genre, accessType, priceRent, priceBuy, etc.). Each slide corresponds to a movie; use `id` to link to the detail page.
- **GET /api/v1/movies?filter=featured** — Featured movies.
- **GET /api/v1/movies?sort=trending&per_page=20** — Trending movies.
- **GET /api/v1/movies/selected-today** — “Selected today” list (if used).
- Optionally: **GET /api/v1/tv-shows**, **GET /api/v1/live-streams** for more sections.

**UI:** Hero carousel with backdrop images and CTA; grid of movie/TV cards with thumbnail, title, rating, and link to detail (e.g. `/movies/{slug}` or `/movie/{id}`).

## 2. Movie detail page

**Goal:** Show movie info, play button or paywall.

- **GET /api/v1/movies/{id}** (or use slug in place of `id`) — Full movie with genres, vj, category, actors, access info. Response includes `is_free`, `is_premium`, `price_rent`, `price_buy`, `access_type`, etc.
- **POST /api/v1/access/check** — Body: `{ "media_id": <movie_id>, "media_type": "MOVIE" }`. Send Bearer token if user is logged in. Use `has_access` to decide:
  - **has_access true** → Show “Play” and link to watch page.
  - **has_access false** → Show paywall: if `requires_auth` show login; if `requires_subscription` show subscribe CTA; if `can_rent`/`can_buy` show rent/buy with `rent_price`/`buy_price`; if `pending_payment` show “Payment pending” and optionally `transaction_ref`.
- **GET /api/v1/comments/{mediaId}** — List comments (mediaId = movie id). Optional: show comments section.

**UI:** Backdrop, title, description, metadata (year, rating, genre, VJ), actors. Primary CTA: Play or paywall (login / subscribe / rent / buy). Secondary: comments.

## 3. TV show detail page

**Goal:** Show show info, seasons/episodes, play or paywall.

- **GET /api/v1/tv-shows/{id}** — Full TV show; response includes seasons and episodes (structure depends on your API serialization).
- **POST /api/v1/access/check** — Body: `{ "media_id": <tv_show_id>, "media_type": "TV_SHOW" }`. Same paywall logic as movie.
- **GET /api/v1/comments/{mediaId}** — Comments (mediaId = TV show id).

**UI:** Same as movie for header; then season accordion or list with episodes. Each episode: “Play” → watch page with `media_type=TV_SHOW&episode={episode_id}`.

## 4. Watch page

**Goal:** Play video with quality and subtitles; optional resume and download.

- **GET /api/v1/player/{id}?media_type=MOVIE** (movie) or **GET /api/v1/player/{id}?media_type=TV_SHOW&episode={episode_id}** (TV). Optional: `Authorization: Bearer <token>` for premium/rent/buy.
- On **200:** Use `playback.url` or `videoUrl` for the primary stream; `playback.type` to choose HLS vs MP4 player; `videoSources` or `playback.sources` for quality menu; `subtitles` for captions; `downloadSources` and **GET /api/v1/downloads/{id}** for download button when allowed.
- On **403:** Show the error message and paywall (reason, can_rent, can_buy, etc.).
- **POST /api/v1/watch-history** (auth) — On pause or exit, send `media_id`, `episode_id` (for TV), `progress_seconds`, `total_seconds` for resume.
- **GET /api/v1/watch-history** (auth) — To show “Continue watching” and pre-fill progress.

**UI:** Video player (HLS or MP4), quality selector, subtitle selector, download button (if present and allowed), and “Continue watching” row using watch-history.

## 5. VJ / catalog page

**Goal:** List VJs and their catalogs.

- **GET /api/v1/vjs** — List VJs (paginated or full list).
- **GET /api/v1/vjs/{id}** — Single VJ with details.
- **GET /api/v1/movies?vj={slug_or_id}** — Movies for that VJ.

**UI:** VJ cards; on VJ detail, grid of movies filtered by that VJ.

## 6. Search

**Goal:** Global search.

- **GET /api/v1/search?q={query}** — Returns `data.archives` (movies/TV), `data.people` (VJs), `data.intel` (articles). Use for a unified search bar and results tabs.

## 7. Subscription and rental UI

**Goal:** Show plans, initiate payment, show status.

- **GET /api/v1/subscription-plans** — List plans (name, slug, description, duration_days, price, features).
- **GET /api/v1/subscription-plans/{id}** — One plan.
- **GET /api/v1/payment-gateways** — Available gateways (for “Pay with X”).
- **GET /api/v1/auth/me** (auth) — User and current plan status (plan, planStatus, renewalDate, pendingSubscription).
- **GET /api/v1/dashboard** (auth) — Full dashboard: subscription, rentals, purchases, transactions, watch history.

**Initiate subscription payment (auth, email verified):**

- **POST /api/v1/payments/initiate** — Body typically includes plan, gateway, amount. Response may include redirect URL or transaction ref.
- Gateway-specific: e.g. **POST /api/v1/flutterwave/initiate**, **POST /api/v1/iotec/initiate**, **POST /api/v1/payments/pawapay/deposit/initiate**. Follow gateway docs and response (redirect, status polling).
- After payment: **POST /api/v1/payments/verify** or gateway verify endpoint; then refresh **GET /api/v1/auth/me** or **GET /api/v1/dashboard**.

**Rent/Buy (per title):** Same payment flow but for a movie/TV show (transactionable). Check **POST /api/v1/payments/initiate** and gateway docs for parameters.

**UI:** Pricing page (subscription-plans); plan selection → gateway selection → payment flow → success/error; dashboard for “My subscription”, “My rentals”, “My purchases”, transactions, and watch history.

## 8. Profile and account

- **GET /api/v1/auth/me** (auth) — Current user and plan.
- **PUT /api/v1/auth/profile** (auth) — Update name, email, phone.
- **DELETE /api/v1/auth/account** (auth) — Delete account.
- **POST /api/v1/auth/logout** (auth) — Invalidate token.

**UI:** Profile form; account deletion with confirmation; logout button.

## 9. Comments

- **GET /api/v1/comments/{mediaId}** — List (public).
- **POST /api/v1/comments** (auth) — Body: media_id, text, optional parent_id.
- **POST /api/v1/comments/{id}/like** (auth) — Toggle like.
- **DELETE /api/v1/comments/{id}** (auth) — Delete own comment.

**UI:** Comment list under movie/TV detail; add reply; like; delete.

## 10. Contact and other

- **POST /api/v1/contact** — Body: name, email, subject, message. Public (but still requires API key).
- **POST /api/v1/views/track** — Track play views (body as defined by API). Use for analytics.

## 11. Ad banners

### Web or app banners

- **GET /api/v1/banners?placement=home_hero&platform=web** — For homepage hero area on web.
- **GET /api/v1/banners?placement=home_hero&platform=app** — For mobile app home banners.

Each banner includes:

- `type`: `"image"` or `"script"`.
- `image_url`: for image banners.
- `script_content`: for script banners (render safely).
- `target_url`, `width`, `height`, `placement`, `platform`.

Use placement keys (`home_hero`, `home_sidebar`, `player_overlay`, etc.) to map banner slots to UI regions.

## 12. Push notifications (client integration)

### Device registration

1. Obtain a push token from your push provider (FCM/OneSignal/APNs).
2. Call:

```http
POST /api/v1/push/devices/register
X-API-KEY: <APP_API_KEY>
Authorization: Bearer <token>   // if user is logged in
Content-Type: application/json

{
  "platform": "android",
  "provider": "fcm",
  "token": "<push_token>",
  "device_id": "<local_device_id>",
  "device_name": "Pixel 7",
  "app_version": "1.0.0"
}
```

3. Re-register when the push token changes (e.g. FCM token refresh).

### Receiving and handling push

- When a notification arrives, use:
  - `title`, `body` for the system UI.
  - `deep_link` (e.g. `app://movie/{id}`, `app://tv-show/{id}`, `app://vj/{id}`) to navigate inside the app.

Admin users can configure these deep links in Filament or via the automatic “Push Notification on Save” sections on Movie/TV Show/VJ resources.

---

Use the **OpenAPI spec** (`/docs/api/v1.openapi` or `docs/openapi.yaml`) for exact request/response schemas and the **Scribe docs** at **/docs/api/v1** for try-it-out and examples. See also:

- `docs/AUTHENTICATION.md` for auth flows.
- `docs/PUSH_NOTIFICATIONS.md` for push details.
- `docs/AD_BANNERS.md` for banner slots and API.
- `docs/API_SECURITY.md` for headers and middleware.
