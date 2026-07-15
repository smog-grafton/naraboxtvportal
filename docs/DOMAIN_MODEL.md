# Domain model

This document describes the main entities and relationships in the NaraBox TV Portal, as reflected by the API and database.

## Core content entities

### Movie

- Represents a **film** (or a single “archive” item). Stored in `movies` with `media_type = 'MOVIE'`.
- Key fields: `id`, `title`, `slug`, `description`, `thumbnail`, `backdrop`, `rating`, `release_date`, `duration`, `is_free`, `is_premium`, `price_rent`, `price_buy`, `download_enabled`, `is_active`, `vj_id`, `category_id`, `views_count`, `trending_score`, `media_type`.
- **Relations:** `category`, `genres`, `vj`, `actors` (via `media_actor`), `videoSources`, `downloadSources`, `subtitles`, `trailers`, `seasons` (for “movie as series” edge case).

### TV Show

- Represents a **series**. Stored in `tv_shows`.
- Same access concepts as movies: `is_free`, `is_premium`, `price_rent`, `price_buy`.
- **Relations:** `seasons` → **Episode**; `genres`, `vj`, `category`, `actors` (via `tv_show_actor`), `videoSources` (on show and on episodes), `downloadSources`, `subtitles`.

### Episode

- A single episode of a **TV show** (or a “season” linked to a movie in some setups). Belongs to a **Season**; has its own `videoSources`, `subtitles`, `download_enabled`.
- Key for playback: `GET /player/{id}?media_type=TV_SHOW&episode={episode_id}`.

### Category

- Content category (e.g. Movie, TV). Used for filtering: `GET /movies?category=...`.

### Genre

- Genre tags. Many-to-many with movies (`media_genre`) and TV shows (`tv_show_genre`). Filter: `GET /movies?genre=...`.

### VJ (Video Jockey)

- Translator/presenter brand. Movies and TV shows can have `vj_id`. Filter: `GET /movies?vj=...`; listing: `GET /vjs`, `GET /vjs/{id}`.

### Actor

- Cast member. Linked to movies via `media_actor`, to TV shows via `tv_show_actor`. Endpoints: `GET /actors`, `GET /actors/trending`, `GET /actors/{id}`.

## Hero and discovery

### HeroSlide

- Carousel on the homepage. Each slide references a **Movie** (`media_id`). Order by `order`. API: `GET /hero` returns processed slide data (title, backdrop, rating, category, genre, access type, etc.).

## Access and monetization

### Access types (conceptual)

- **FREE** — No login or payment.
- **SUBSCRIPTION** — Active plan required (for `is_premium` content).
- **PURCHASED** — One-off purchase (lifetime).
- **RENTED** — Time-limited rental; `expires_at` matters.
- **PENDING** — Payment submitted, not yet approved.

### Subscription plan

- Defined in `subscription_plans`: name, slug, duration_days, price, features. Public: `GET /subscription-plans`, `GET /subscription-plans/{id}`.

### User subscription

- A user’s subscription instance: `user_subscriptions` (plan, started_at, expires_at, status: ACTIVE/EXPIRED/etc.). Shown in `GET /dashboard` and reflected in `GET /auth/me` (plan, planStatus, renewalDate).

### User rental / User purchase

- **user_rentals:** rentable_type (Movie/TVShow), rentable_id, expires_at, is_active.
- **user_purchases:** purchasable_type, purchasable_id, purchased_at.

Used by access control and dashboard.

### Payment transaction

- Records payments (subscription or one-off). Status: PENDING, COMPLETED, FAILED, etc. Linked to gateway, user, and optionally subscription_plan or transactionable (movie/TV show).

### Payment gateway

- Active gateways (e.g. Flutterwave, ioTec, PawaPay). Public list: `GET /payment-gateways`.

## Playback and media

### VideoSource

- Polymorphic: attached to Movie, TVShow, or Episode. Types: url, local, fetched. Holds `metadata` (e.g. CDN asset id, HLS/MP4 URLs). Player uses these to build playback manifest.

### DownloadSource

- Polymorphic; links to downloadable file (URL or local path). Quality, format, label. Served via `GET /downloads/{id}` with access control.

### Subtitle

- Polymorphic; language, label, URL or file path. Returned in player payload as `subtitles[]`.

## User and engagement

### User

- id, name, email, phone, avatar, role_id, plan, plan_status, email_verified_at. Authenticated via Sanctum; token from login/register.

### SocialAccount

- Links a user to an external identity provider. Table: `social_accounts`.
- Fields: `user_id`, `provider` (`google`, `apple`, ...), `provider_user_id`, `email`, `raw_profile`, `last_login_at`.
- Used by Google and Apple login flows to keep identities stable across sessions/devices.

### PhoneVerificationCode

- Stores OTP codes for phone login (`phone_verification_codes`).
- Fields: `phone`, `code`, `expires_at`, `used`, `attempts`.
- Used by `POST /auth/phone/request-otp` and `POST /auth/phone/verify-otp`.

### Watch history

- user_id, media_id (movie id), episode_id (optional), progress_seconds, total_seconds, last_watched_at. Used for “continue watching” and resume.

### Comment

- media_id (movie id), user_id, text, parent_id, likes. Read: `GET /comments/{mediaId}`; write (auth): `POST /comments`, like, delete.

## Editorial and live

### Article

- News/editorial. Blocks and tags. API: `GET /articles`, `GET /articles/{id}`.

### LiveStream

- Live channel: title, stream_url, platform, is_live, thumbnail. API: `GET /live-streams`, `GET /live-streams/{id}`.

## Engagement and monetization extensions

### PushDevice

- Represents a concrete app/browser installation that can receive push notifications.
- Table: `push_devices`.
- Fields: `user_id` (nullable), `platform` (`android`, `ios`, `web`, `other`), `provider` (`fcm`, `onesignal`, `custom`), `token`, `device_id`, `device_name`, `app_version`, `is_active`, `last_seen_at`.
- API: `POST /push/devices/register`, `POST /push/devices/unregister`.

### PushNotification

- Logical notification to be sent to many devices.
- Table: `push_notifications`.
- Fields: `title`, `body`, `image_url`, `deep_link`, `target_platform` (`all`, `android`, `ios`, `web`), `target_audience` (`all`, `subscribed`, `free`, `custom`), `filters` (JSON), `provider`, `status` (`draft`, `queued`, `sending`, `sent`, `failed`), `sent_at`, `success_count`, `failure_count`, `last_error`.
- Exposed in Filament as `Push Notifications` resource; sent via `PushNotificationService`.

### AdBanner

- Represents an ad unit for a specific placement and platform.
- Table: `ad_banners`.
- Fields: `name`, `slug`, `type` (`image` or `script`), `image_path`, `script_content`, `target_url`, `width`, `height`, `placement` (e.g. `home_hero`, `home_sidebar`, `player_overlay`), `platform` (`all`, `app`, `web`), `is_active`, `active_from`, `active_until`, `sort_order`, `notes`.
- API: `GET /banners` (with placement/platform filtering).

## Summary for frontend / AI

- **Homepage:** Hero (slides → movies), featured/trending movies, maybe TV shows and live.
- **Detail page:** One movie or one TV show (with seasons/episodes); access check drives “Play” vs “Subscribe/Rent/Buy”.
- **Watch page:** Player endpoint with media id + optional episode id; use videoSources/subtitles/playback; optionally update watch history when authenticated.
- **Profile/Dashboard:** User info, subscription, rentals, purchases, transactions, watch history.
- **Payments:** Subscription plans + payment gateways; initiate → (gateway flow) → verify; then refresh dashboard/auth/me.
