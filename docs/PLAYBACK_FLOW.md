# Playback flow

This document describes how the frontend should obtain and use playback data for the watch experience.

## 1. Decide what to play

- **Movie:** You have a movie `id` (or `slug`). Use `media_type=MOVIE` (or omit; MOVIE is default).
- **TV show episode:** You have a TV show `id` (or `slug`) and an **episode** `id`. Use `media_type=TV_SHOW` and `episode={episode_id}`.

## 2. Check access (optional but recommended)

Before showing the player or a paywall, call:

```http
POST /api/v1/access/check
Content-Type: application/json
Authorization: Bearer <token>   // optional; include if user is logged in

{
  "media_id": 123,
  "media_type": "MOVIE"   // or "TV_SHOW"
}
```

Response fields:

- **has_access** — `true` if the user can play.
- **access_type** — `FREE` | `SUBSCRIPTION` | `PURCHASED` | `RENTED` | `PENDING` | `PAID` | `PREMIUM` | etc.
- **reason** — Human-readable message.
- When `has_access` is `false`: **requires_auth**, **requires_subscription**, **can_rent**, **can_buy**, **rent_price**, **buy_price**, **pending_payment**, **transaction_ref** as needed.

Use this to show either the player or the appropriate paywall (login, subscribe, rent, buy, or “payment pending”).

## 3. Request playback manifest

```http
GET /api/v1/player/{id}?media_type=MOVIE
GET /api/v1/player/{id}?media_type=TV_SHOW&episode={episode_id}
```

`id` can be numeric ID or slug for the movie or TV show.

- **200:** Playback payload.
- **403:** No access; response body includes reason, prices, and flags (e.g. `requires_subscription`, `can_rent`).
- **404:** Media not found or no video source available.

## 4. Use the response

Response shape (simplified):

- **movie** — `id`, `title`, `thumbnail`, `backdrop`, `download_enabled`.
- **episode** — Present for TV: `id`, `number`, `title`, `download_enabled`.
- **videoUrl** — Primary URL (MP4 or HLS master).
- **videoSources** — Array of `{ id, url, quality, format, type, isPrimary, duration }` for quality switching.
- **subtitles** — Array of `{ id, src, label, language, kind, default, format }`.
- **duration** — In seconds (when available).
- **poster** — Image URL for poster.
- **downloadSources** — When download is allowed and user has access: `{ id, type, quality, format, label, url, download_url, file_size, ... }`.
- **playback** — Rich object:
  - **type** — `"mp4"` or `"hls"`.
  - **url** — Primary playback URL (HLS master or MP4).
  - **hls_master_url**, **mp4_play_url**, **mp4_url**, **download_url**.
  - **sources** — Same as `videoSources` (or from CDN manifest).
  - **subtitles** — Same as top-level `subtitles`.
  - **qualities** — Optional list of quality variants (id, label, url, bandwidth, width, height).

**Frontend:**

- Prefer **playback.url** (or **videoUrl**) for the primary stream.
- For HLS, use **playback.type === "hls"** and **playback.url** (or **hls_master_url**) with an HLS-capable player.
- For quality switching, use **playback.sources** or **videoSources**.
- Attach **subtitles** to the player.
- Use **downloadSources[].url** (or **download_url**) for download buttons; the URL is the API download endpoint that enforces access.

## 5. Track progress (authenticated)

After the user has watched, send progress:

```http
POST /api/v1/watch-history
Authorization: Bearer <token>
Content-Type: application/json

{
  "media_id": 123,
  "episode_id": null,
  "progress_seconds": 320,
  "total_seconds": 3600
}
```

- **media_id** — Always the movie ID (for TV shows, this is the TV show’s ID).
- **episode_id** — Set for TV show episodes; null for movies.

Use **GET /api/v1/watch-history** to get the user’s list for “continue watching” and resume.

## 6. Downloads

- **GET /api/v1/downloads/{id}** — Returns the file (or redirect). `id` is the download source id from **downloadSources[].id** in the player response.
- Access is enforced by the server (free content or user has purchase/rental/subscription as applicable).
- Use the same Bearer token when the user is logged in.

## Summary

1. Resolve media (movie or TV show + episode).
2. **POST /access/check** to decide play vs paywall.
3. **GET /player/{id}** with `media_type` and optional `episode` to get URLs and subtitles.
4. Play using **playback** / **videoSources** / **subtitles**; optionally **POST /watch-history** and **GET /watch-history** for resume.
5. Use **downloadSources** and **GET /downloads/{id}** for downloads when allowed.
