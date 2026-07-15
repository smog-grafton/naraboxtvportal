# Database schema (naraboxt-lara)

This document describes the **naraboxt-lara** MySQL database used by the NaraBox TV Portal API. Use it when integrating a mobile app, building reports, or when AI needs to understand data relationships.

**Database name:** `naraboxt-lara`

---

## Tables overview

| Table | Purpose |
|-------|---------|
| **users** | User accounts; auth via Laravel Sanctum (personal_access_tokens). |
| **roles** | Role definitions (e.g. customer, admin). |
| **movies** | Movies/single-title content; media_type MOVIE/SERIES; links to category, vj, video_sources, etc. |
| **tv_shows** | TV series; seasons → episodes. |
| **seasons** | Seasons of a TV show (or movie-as-series). |
| **episodes** | Episodes; each has video_sources, subtitles. |
| **video_sources** | Polymorphic: sourceable = Movie, TVShow, or Episode; type url/local/fetched; metadata (CDN, HLS, MP4). |
| **download_sources** | Polymorphic: downloadable = Movie, TVShow, or Episode; quality, format, url/file_path. |
| **subtitles** | Polymorphic: subtitleable = Movie, TVShow, or Episode; language, url/file_path. |
| **categories** | Content category (e.g. Movie, TV). |
| **genres** | Genres; many-to-many with movies (media_genre) and tv_shows (tv_show_genre). |
| **actors** | Cast; many-to-many via media_actor (movies), tv_show_actor (tv_shows). |
| **vjs** | VJ (translator/presenter) brands; vj_genre; movies/tv_shows have vj_id. |
| **hero_slides** | Homepage carousel; media_id → movies.id. |
| **articles** | News/editorial; article_blocks, article_tags. |
| **article_blocks** | Article content blocks (type, value, gallery_images). |
| **article_tags** | Article tags. |
| **live_streams** | Live channels; stream_url, platform, is_live, is_archived. |
| **comments** | Comments on media (media_id = movies.id); parent_id for replies; user_id, user_name, likes. |
| **watch_history** | user_id, media_id (movie id), episode_id, progress_seconds, total_seconds, last_watched_at. |
| **subscription_plans** | Plan name, slug, duration_days, price, features. |
| **payment_gateways** | Gateway name, slug, code, type (AUTOMATIC/MANUAL), display_name, instructions, config. |
| **payment_transactions** | Per payment: user_id, gateway, type (RENT/BUY/SUBSCRIPTION), transaction_ref, amount, status, transactionable (movie/TV), subscription_plan_id. |
| **payments** | Manual proof uploads: transaction_id, proof_path, status (PENDING/APPROVED/REJECTED). |
| **user_subscriptions** | user_id, subscription_plan_id, transaction_id, started_at, expires_at, status (ACTIVE/EXPIRED/CANCELLED). |
| **user_rentals** | user_id, rentable_type/id, transaction_id, rented_at, expires_at, is_active. |
| **user_purchases** | user_id, purchasable_type/id, transaction_id, purchased_at. |
| **contact_messages** | Contact form: name, email, subject, message, is_read. |
| **email_verification_codes** | Email verification codes. |
| **password_reset_tokens** / **password_resets** | Password reset flow. |
| **personal_access_tokens** | Sanctum API tokens. |
| **trailers** | Polymorphic trailers (trailerable = Movie/TVShow). |
| **collections** | TMDB-style collections. |
| **crews** | Crew (polymorphic). |
| **keywords** | Keywords (polymorphic). |
| **telegram_imports** | Telegram ingest tracking. |
| **smtp_settings**, **email_templates** | Email config and templates. |
| **cache**, **cache_locks** | Laravel cache (database driver). |
| **jobs**, **job_batches**, **failed_jobs** | Queues. |
| **migrations** | Laravel migrations. |
| **sessions** | Session driver (if used). |

---

## Key columns (reference)

### users

- `id`, `role_id`, `name`, `email`, `email_verified_at`, `password`, `phone`, `avatar`
- `plan`, `plan_status` (e.g. NONE, ACTIVE), `renewal_date` (denormalized from user_subscriptions)

### movies

- `id`, `tmdb_id`, `imdb_id`, `title`, `slug`, `description`, `thumbnail`, `backdrop`, `rating`, `release_date`
- `media_type` (MOVIE, SERIES), `category_id`, `vj_id`, `duration`
- `is_free`, `is_premium`, `price_rent`, `price_buy`, `download_enabled`, `is_active`
- `views_count`, `manual_views`, `trending_score`, `is_featured`, `video_url`, `collection_id`

### tv_shows

- Same access fields as movies: `is_free`, `is_premium`, `price_rent`, `price_buy`, `download_enabled`
- `number_of_seasons`, `number_of_episodes`; no direct `video_url` (use episodes)

### payment_gateways

- `id`, `name`, `slug`, `code`, `type` (AUTOMATIC | MANUAL), `display_name`, `logo_path`
- `description`, `helper_text`, `instructions`, `payment_details` (JSON/text), `config` (JSON), `is_active`, `sort_order`

### payment_transactions

- `id`, `user_id`, `payment_gateway_id`, `gateway_code`
- `type`: RENT | BUY | SUBSCRIPTION
- `subscription_plan_id` (when type=SUBSCRIPTION)
- `transaction_ref` (unique, e.g. NBX-xxx), `amount`, `status`: PENDING | SUCCESS | FAILED | CANCELLED
- `transactionable_type`, `transactionable_id` (Movie or TVShow for RENT/BUY)
- `gateway_transaction_id`, `external_reference`, `provider_code`, `gateway_response`, `raw_request`, `raw_response`, `raw_callback`, `meta`

### user_subscriptions

- `user_id`, `subscription_plan_id`, `transaction_id`, `started_at`, `expires_at`, `status` (ACTIVE | EXPIRED | CANCELLED), `auto_renew`

### user_rentals / user_purchases

- `rentable_type` / `purchasable_type`: `App\Models\Movie` or `App\Models\TVShow`
- `rentable_id` / `purchasable_id`, `transaction_id`, `expires_at` (rentals), `is_active` (rentals)

---

## Relationships (for API consumers)

- **Movie/TV show → playback:** Use `video_sources` and `subtitles` (and optionally CDN manifest). Episodes have their own video_sources.
- **Access:** Check `user_subscriptions` (active + not expired), `user_purchases`, `user_rentals` (is_active, expires_at > now).
- **Payments:** Create `payment_transactions`; for MANUAL gateways, user uploads proof → `payments`; admin approves → status SUCCESS and `PaymentApprovalService::grantAccess()` creates user_subscription / user_rental / user_purchase.

For full API behavior and code samples, see **API_REFERENCE_WITH_CODE_SAMPLES.md** and **PAYMENT_GATEWAYS.md**.
