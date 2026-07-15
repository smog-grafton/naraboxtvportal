# API reference with code samples

Complete reference for the NaraBox TV Portal API with **cURL** and **JavaScript (fetch)** examples. Use this for mobile apps (you can adapt fetch to React Native, Flutter, or native HTTP). Base URL: `https://portal.naraboxtv.com`; all endpoints: `/api/v1/...`. Send `Accept: application/json` and `Content-Type: application/json` where applicable.

---

## Authentication

### Register

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "0782123456"
  }'
```

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/auth/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  body: JSON.stringify({
    name: 'Jane Doe',
    email: 'jane@example.com',
    password: 'password123',
    password_confirmation: 'password123',
    phone: '0782123456',
  }),
});
const data = await res.json();
// data.data.token, data.data.user (id, name, email, plan, planStatus, emailVerified)
```

**Response (201):** `data.token` (Bearer), `data.user` (id, name, email, phone, avatar, plan, planStatus, renewalDate, emailVerified).

---

### Login

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "jane@example.com", "password": "password123"}'
```

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  body: JSON.stringify({ email: 'jane@example.com', password: 'password123' }),
});
const data = await res.json();
const token = data.data?.token;
```

**Response (200):** `data.token`, `data.user`, optional `requires_verification`.

---

### Get current user (auth)

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/auth/me" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/auth/me', {
  headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
});
const data = await res.json();
// data.data: id, name, email, phone, avatar, plan, planStatus, renewalDate, emailVerified, pendingSubscription
```

---

### Update profile (auth)

```bash
curl -X PUT "https://portal.naraboxtv.com/api/v1/auth/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Jane Smith", "phone": "0782999999"}'
```

---

### Logout (auth)

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/auth/logout" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Forgot password

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/auth/forgot-password" \
  -H "Content-Type: application/json" \
  -d '{"email": "jane@example.com"}'
```

---

### Reset password

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/auth/reset-password" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jane@example.com",
    "token": "RESET_TOKEN_FROM_EMAIL",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }'
```

---

### Google OAuth URL

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/auth/google/url" \
  -H "Accept: application/json"
```

**Response:** `data.url` — redirect user to this URL; after login, user is redirected to your frontend with `?token=...&user=...` or `?error=...`.

---

## Hero (homepage carousel)

### Get hero slides

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/hero" \
  -H "Accept: application/json"
```

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/hero', {
  headers: { 'Accept': 'application/json' },
});
const data = await res.json();
// data.data[]: id, title, description, thumbnail, backdrop, rating, releaseDate, category, mediaType, vj, genre, accessType, priceRent, priceBuy
```

---

## Movies

### List movies

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/movies?per_page=20&sort=trending&filter=free" \
  -H "Accept: application/json"
```

Query params: `category`, `genre`, `vj`, `has_vj`, `filter` (free|rent|purchase|premium|featured), `sort` (trending|latest|rating), `order`, `per_page`.

```javascript
const params = new URLSearchParams({ per_page: 20, sort: 'trending' });
const res = await fetch(`https://portal.naraboxtv.com/api/v1/movies?${params}`);
const data = await res.json();
// data.data[], data.meta (current_page, last_page, per_page, total)
```

---

### Selected today

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/movies/selected-today" \
  -H "Accept: application/json"
```

---

### Get movie by ID or slug

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/movies/1" \
  -H "Accept: application/json"
# or /movies/my-movie-slug
```

**Response:** Full movie object with genres, vj, category, actors, seasons/episodes (if applicable), is_free, is_premium, price_rent, price_buy, access_type, video_sources summary, etc.

---

## TV Shows

### List TV shows

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/tv-shows?per_page=20" \
  -H "Accept: application/json"
```

Query params: `category`, `category_id`, `per_page`, etc.

---

### Get TV show by ID or slug

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/tv-shows/1" \
  -H "Accept: application/json"
```

**Response:** TV show with seasons and episodes nested.

---

## Search

### Global search

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/search?q=action" \
  -H "Accept: application/json"
```

```javascript
const q = encodeURIComponent('action');
const res = await fetch(`https://portal.naraboxtv.com/api/v1/search?q=${q}`);
const data = await res.json();
// data.data.archives (movies/TV), data.data.people (VJs), data.data.intel (articles)
```

---

## VJs

### List VJs

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/vjs?featured=1" \
  -H "Accept: application/json"
```

Query: `featured` (1), `order_by` (movies_count), `limit`.

---

### Get VJ by ID or slug

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/vjs/1" \
  -H "Accept: application/json"
```

**Response:** VJ with movies array.

---

## Articles (news)

### List articles

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/articles?per_page=10&category=news" \
  -H "Accept: application/json"
```

Query: `category`, `top_news`, `per_page`.

---

### Get article by ID or slug

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/articles/1" \
  -H "Accept: application/json"
```

---

## Contact

### Submit contact form

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/contact" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John",
    "email": "john@example.com",
    "subject": "Support",
    "message": "Hello, I need help."
  }'
```

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/contact', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  body: JSON.stringify({
    name: 'John',
    email: 'john@example.com',
    subject: 'Support',
    message: 'Hello, I need help.',
  }),
});
const data = await res.json();
// data.success, data.message
```

---

## Live streams

### List live streams

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/live-streams?type=live" \
  -H "Accept: application/json"
```

Query: `type` (live|archived).

---

### Get live stream by ID

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/live-streams/1" \
  -H "Accept: application/json"
```

**Response:** `data`: id, title, description, stream_url, platform, is_live, thumbnail, viewer_count.

---

## Actors

### List actors

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/actors?per_page=20&search=john" \
  -H "Accept: application/json"
```

Query: `search`, `trending` (1), `per_page`.

---

### Trending actors

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/actors/trending" \
  -H "Accept: application/json"
```

---

### Get actor by ID or slug

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/actors/1" \
  -H "Accept: application/json"
```

---

## Player (playback)

### Get playback manifest

For a **movie:** use `media_type=MOVIE` or omit. For a **TV show episode:** use `media_type=TV_SHOW` and `episode={episode_id}`.

```bash
# Movie
curl -X GET "https://portal.naraboxtv.com/api/v1/player/1?media_type=MOVIE" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"

# TV show episode
curl -X GET "https://portal.naraboxtv.com/api/v1/player/5?media_type=TV_SHOW&episode=12" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

```javascript
const mediaId = 1;
const mediaType = 'MOVIE';
const episodeId = null;
let url = `https://portal.naraboxtv.com/api/v1/player/${mediaId}?media_type=${mediaType}`;
if (episodeId) url += `&episode=${episodeId}`;
const res = await fetch(url, {
  headers: {
    'Accept': 'application/json',
    ...(token && { 'Authorization': `Bearer ${token}` }),
  },
});
const data = await res.json();
// data.videoUrl, data.videoSources[], data.subtitles[], data.playback (type, url, hls_master_url, sources, qualities)
// data.downloadSources[] (when allowed): url is GET /api/v1/downloads/{id}
// On 403: data.reason, data.can_rent, data.can_buy, data.rent_price, data.buy_price
```

**Response (200):** movie, episode (if TV), videoUrl, videoSources, subtitles, duration, poster, downloadSources, playback (type, url, hls_master_url, mp4_play_url, sources, qualities).

---

## Downloads

### Download file (with access control)

Use the download source `id` from the player response `downloadSources[].id`. Optional: pass token in header or as query `?access_token=YOUR_TOKEN`.

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/downloads/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -L -o movie.mp4
```

```javascript
const downloadSourceId = 1;
const url = `https://portal.naraboxtv.com/api/v1/downloads/${downloadSourceId}`;
const res = await fetch(url, {
  headers: token ? { 'Authorization': `Bearer ${token}` } : {},
});
// res.ok: stream or blob; else JSON error (401/403/404)
```

---

## Access check

### Check if user can play content

Optional auth; with token returns full access type (FREE, SUBSCRIPTION, RENTED, PURCHASED, etc.).

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/access/check" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"media_id": 1, "media_type": "MOVIE"}'
```

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/access/check', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...(token && { 'Authorization': `Bearer ${token}` }),
  },
  body: JSON.stringify({ media_id: 1, media_type: 'MOVIE' }),
});
const data = await res.json();
// data.has_access, data.access_type, data.reason
// If !has_access: data.can_rent, data.can_buy, data.rent_price, data.buy_price, data.requires_auth, data.requires_subscription
```

---

## View tracking

### Track view (public)

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/views/track" \
  -H "Content-Type: application/json" \
  -d '{"media_id": 1, "media_type": "MOVIE"}'
```

**Response:** `message`, `views_count`.

---

## Subscription plans

### List plans (no auth)

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/subscription-plans" \
  -H "Accept: application/json"
```

**Response:** Array of plans: id, name, slug, description, duration_days, price, features.

---

### Get plan by ID

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/subscription-plans/1" \
  -H "Accept: application/json"
```

---

## Payment gateways

### List gateways (no auth)

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/payment-gateways" \
  -H "Accept: application/json"
```

See **PAYMENT_GATEWAYS.md** for initiate, upload-proof, verify and gateway-specific samples.

---

## Dashboard (auth)

### Get dashboard

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/dashboard" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Response:** user, subscription, pending_subscription, vault (rentedIds, purchasedIds, watchHistory), rentals, purchases, transactions.

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/dashboard', {
  headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
});
const data = await res.json();
// data.user, data.subscription, data.rentals, data.purchases, data.transactions, data.vault.watchHistory
```

---

## Watch history (auth)

### Update progress

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/watch-history" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "media_id": 1,
    "episode_id": null,
    "progress_seconds": 320,
    "total_seconds": 3600
  }'
```

For TV shows, `media_id` = tv_show id, `episode_id` = episode id.

---

### Get watch history

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/watch-history" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:** `data[]`: id, mediaId, episodeId, progressSeconds, totalSeconds, lastWatched.

---

## Comments

### List comments (public)

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/comments/1" \
  -H "Accept: application/json"
```

`1` = media_id (movie id). Response: `data[]` with id, user, avatar, text, likes, date, replies[].

---

### Add comment (auth or anonymous)

Authenticated: no user_name needed. Anonymous: send user_name (and optional avatar).

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/comments" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"media_id": 1, "text": "Great movie!", "parent_id": null}'
```

---

### Like comment (auth)

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/comments/5/like" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Delete comment (auth, own only)

```bash
curl -X DELETE "https://portal.naraboxtv.com/api/v1/comments/5" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Errors

- **401:** Missing or invalid token. Body: `{"message": "Unauthenticated."}` or `{"error": "Unauthorized"}`.
- **403:** e.g. no access to content; body includes reason, can_rent, can_buy, prices.
- **404:** Resource not found.
- **422:** Validation error; body has `messages` (field errors) or `error`.

Use **Accept: application/json** so the API returns JSON errors. For more detail see **ERRORS_AND_STATUS_CODES.md**.

---

## Quick reference: which endpoint for what

| Goal | Endpoint |
|------|----------|
| Register / login | POST /auth/register, POST /auth/login |
| Homepage carousel | GET /hero |
| Movie list / detail | GET /movies, GET /movies/{id} |
| TV list / detail | GET /tv-shows, GET /tv-shows/{id} |
| Search | GET /search?q= |
| VJ list / detail | GET /vjs, GET /vjs/{id} |
| News | GET /articles, GET /articles/{id} |
| Contact | POST /contact |
| Live streams | GET /live-streams, GET /live-streams/{id} |
| Actors | GET /actors, GET /actors/trending, GET /actors/{id} |
| Can user play? | POST /access/check |
| Playback URLs | GET /player/{id}?media_type=&episode= |
| Download | GET /downloads/{id} |
| Track view | POST /views/track |
| Subscription plans | GET /subscription-plans |
| Payment gateways | GET /payment-gateways |
| Initiate payment | POST /payments/initiate or gateway-specific |
| Upload proof | POST /payments/upload-proof |
| Verify payment | POST /payments/verify |
| User dashboard | GET /dashboard (auth) |
| Watch history | POST /watch-history, GET /watch-history (auth) |
| Comments | GET /comments/{mediaId}, POST /comments, POST /comments/{id}/like, DELETE /comments/{id} |

All of the above are documented with request/response shapes in the Scribe HTML docs at **/docs/api/v1** and in the OpenAPI spec at **/docs/api/v1.openapi** or **docs/openapi.yaml**.
