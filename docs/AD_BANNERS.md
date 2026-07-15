# Ad banners

This document describes the ad banner system, including how admins manage banners and how clients consume them via the API.

## Data model

Entity: `AdBanner` (`ad_banners` table)

Key fields:

- `name` — Human-readable name in Filament.
- `slug` — Unique key, used for debugging and potential future APIs.
- `type` — `"image"` or `"script"`.
- `image_path` — Banner image path (for image type).
- `script_content` — Raw script/HTML (for script type).
- `target_url` — Click-through URL (image banners).
- `width`, `height` — Optional dimensions in pixels.
- `placement` — Logical slot (e.g. `home_hero`, `home_sidebar`, `player_overlay`).
- `platform` — `"all"`, `"app"`, `"web"`.
- `is_active` — Whether the banner is currently active.
- `active_from`, `active_until` — Optional active window.
- `sort_order` — Ordering within a placement.

The model exposes an `active()` scope that:

- Filters `is_active = true`.
- Applies `active_from` / `active_until` bounds against the current time.

## Admin UI (Filament)

The `Ad Banners` Filament resource lets admins:

- Create/edit/delete banners.
- Choose type (image/script).
- Upload an image file or paste ad network script/HTML.
- Configure placement, platform, active window, and order.

Recommended placements:

- `home_hero` — Large banner near the homepage hero.
- `home_sidebar` — Side column banner.
- `player_overlay` — Overlay or below-player banner.
- `profile_header` — Profile/dashboard header area.

## Banner API

### List banners

```http
GET /api/v1/banners?placement=home_hero&platform=app&limit=3
X-API-KEY: <APP_API_KEY>
Accept: application/json
```

Query parameters:

- `placement` (optional) — Filter by logical slot.
- `platform` (optional) — `app`, `web`, or `all`. When set, returns banners where `platform = all` OR `platform = <value>`.
- `limit` (optional) — Max banners to return (1–50, default 20).

Response:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Home hero banner",
      "slug": "home-hero-1",
      "type": "image",
      "image_url": "banners/home-hero.jpg",
      "script_content": null,
      "target_url": "https://sponsor.example.com",
      "width": 1920,
      "height": 400,
      "placement": "home_hero",
      "platform": "app"
    }
  ]
}
```

Notes:

- For **image** banners, use `image_url` and `target_url` to render a clickable `<img>`.
- For **script** banners, use `script_content` and render it in a sandboxed/safe container appropriate for your frontend (consider CSP, sandboxed iframes, and user consent).

## Frontend usage patterns

### Web frontend

- For each slot (e.g. homepage hero, sidebar), call:

```http
GET /api/v1/banners?placement=home_hero&platform=web
```

- Render:
  - Image banners as `<a href="target_url"><img src="image_url" .../></a>`.
  - Script banners by injecting `script_content` into a controlled DOM node.

### Mobile apps

- For each slot in the app (e.g. home top banner, player overlay), call:

```http
GET /api/v1/banners?placement=home_hero&platform=app
```

- For image banners, show an `Image` with a tap handler to open `target_url` in an in-app browser.
- Script banners are typically **ignored** in native code or rendered only if you have a secure WebView integration.

### Future extensions

The `filters`/audience rules and impression/click tracking are intentionally left out for now. The current design keeps:

- A clean **API shape** for banners.
- Enough metadata (placement, platform, type, sizes) for AI tools to design layouts.

Tracking can be added later via:

- Simple tracking endpoints (e.g. `/api/v1/banners/{id}/impression`, `/api/v1/banners/{id}/click`).
- Or external analytics embedded in `script_content`.

