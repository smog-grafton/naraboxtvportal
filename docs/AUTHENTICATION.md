# Authentication

The NaraBox TV Portal API uses **Laravel Sanctum** for API authentication. Clients receive a **Bearer token** and send it on protected endpoints.

All successful auth flows (email/password, phone OTP, Google, Apple) return a **common payload**:

```json
{
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "+256700000000",
      "avatar": null,
      "plan": "FREE",
      "planStatus": "NONE",
      "renewalDate": null,
      "emailVerified": true
    },
    "token": "<sanctum_token>",
    "auth_provider": "email|phone|google|apple",
    "is_new_user": false,
    "requires_verification": false
  }
}
```

Use `data.token` as the Bearer token and `data.user` for UI state. `auth_provider` and `is_new_user` help drive onboarding flows.

## Backend app API key

All `/api/v1/*` routes are additionally protected by an **app-level API key** (for mobile/web clients):

- Header: `X-API-KEY: <APP_API_KEY>`
- Config: `APP_API_KEY` and `APP_API_KEY_ENABLED` in `.env`

If the key is missing or invalid, responses look like:

```json
{
  "error": "Invalid or missing API key",
  "code": "INVALID_API_KEY",
  "message": "Your client is not authorized to call this API. Please include a valid API key in the request headers."
}
```

See `docs/API_SECURITY.md` for details.

## 1. Email/password flows

### Register

```http
POST /api/v1/auth/register
Content-Type: application/json
X-API-KEY: <APP_API_KEY>

{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": null
}
```

- **201:** User created; response includes common auth payload with `auth_provider: "email"`, `is_new_user: true`.
- **422:** Validation failed (e.g. email already taken); see `messages` in the body.

### Login

```http
POST /api/v1/auth/login
Content-Type: application/json
X-API-KEY: <APP_API_KEY>

{
  "email": "jane@example.com",
  "password": "password123"
}
```

- **200:** Success; response includes common auth payload with `auth_provider: "email"`.
- **401:** Invalid credentials.
- **422:** Validation error.

The payload may include `requires_verification: true` if email is not verified; the frontend can prompt for verification.

### Email verification

- **POST /api/v1/auth/verify-email** — Verify with `email` and `code` (6 digits).
- **POST /api/v1/auth/resend-verification** — Resend code to `email`.

Payment-related endpoints (e.g. initiate subscription payment) are behind the `email.verified` middleware; unverified users receive an error and should be directed to verify first.

### Password reset

- **POST /api/v1/auth/forgot-password** — Body: `{ "email": "..." }`. Sends reset link/code.
- **POST /api/v1/auth/reset-password** — Body: `email`, `token`, `password`, `password_confirmation`. Completes reset and invalidates existing tokens.

## 2. Phone OTP login

Phone login is designed for mobile apps and supports **login or register by phone**.

### Request OTP

```http
POST /api/v1/auth/phone/request-otp
Content-Type: application/json
X-API-KEY: <APP_API_KEY>

{
  "phone": "+256700000000"
}
```

- **200:** OTP sent (currently logged to server logs in dev; wire real SMS provider later).
- **422:** Validation error.

### Verify OTP (login/register)

```http
POST /api/v1/auth/phone/verify-otp
Content-Type: application/json
X-API-KEY: <APP_API_KEY>

{
  "phone": "+256700000000",
  "code": "123456",
  "name": "Jane Doe",       // optional, for new users
  "email": "jane@example.com" // optional, links to existing email account if present
}
```

- **200:** Common auth payload with `auth_provider: "phone"` and `is_new_user` set appropriately.
- **422:** Invalid or expired OTP.

Backend behavior:

- If a user exists with this `phone`, they are logged in.
- Else, if `email` matches an existing user, that user is updated with this `phone`.
- Else, a new customer user is created (name/email optional) with plan `FREE`.

## 3. Google login

### Web OAuth redirect flow

- **GET /api/v1/auth/google/url** — Returns `data.url` for redirecting the user to Google.
- User is sent to Google, then back to **GET /api/v1/auth/google/callback**, which redirects to the frontend with `?token=...&user=...` or `?error=...`.

Frontend (web) should call `GET /auth/google/url`, redirect the user to the returned URL, and handle the callback URL (e.g. `/auth/callback`) to store the token and user.

### Mobile (access-token-based) flow

Mobile apps (React Native, Flutter) should obtain a Google access token via the native SDK, then call:

```http
POST /api/v1/auth/google/mobile
Content-Type: application/json
X-API-KEY: <APP_API_KEY>

{
  "access_token": "<google_access_token>"
}
```

- **200:** Common auth payload with `auth_provider: "google"` and `is_new_user` set appropriately.
- **500:** If Google token validation fails (check `error`).

The backend:

- Looks up or creates a user by Google id/email.
- Ensures a `social_accounts` record exists (`provider = google`).
- Verifies email and stores avatar when available.

## 4. Apple login (mobile)

Apple Sign-In is handled by the mobile app, which sends the verified Apple user id (and optionally email/name) to the backend:

```http
POST /api/v1/auth/apple/mobile
Content-Type: application/json
X-API-KEY: <APP_API_KEY>

{
  "apple_user_id": "<apple_sub>",
  "email": "jane@example.com", // optional, only provided first time
  "name": "Jane Doe"           // optional
}
```

- **200:** Common auth payload with `auth_provider: "apple"` and `is_new_user` set appropriately.
- **422:** Validation error.
- **500:** If customer role is missing or an internal error occurs.

Backend behavior:

- Tries to find an existing `social_accounts` record for Apple, then an existing user by email, else creates a new `FREE` user.
- Ensures a `social_accounts` record exists (`provider = apple`).

## 5. Using the token

Send the Sanctum token on every request to protected endpoints:

```http
Authorization: Bearer <your_token>
```

Protected endpoints return **401 Unauthorized** if the token is missing or invalid.

## 6. Protected and semi-protected endpoints

### Fully protected (require auth)

- `GET /api/v1/auth/me` — Current user and plan/subscription state.
- `PUT /api/v1/auth/profile` — Update name, email, phone.
- `DELETE /api/v1/auth/account` — Delete account.
- `POST /api/v1/auth/logout` — Invalidate current token.
- `GET /api/v1/dashboard` — User dashboard (subscription, rentals, purchases, watch history).
- `POST /api/v1/payments/initiate`, `POST /api/v1/payments/upload-proof`, `POST /api/v1/payments/verify` (and gateway-specific payment endpoints).
- `POST /api/v1/watch-history`, `GET /api/v1/watch-history`.
- `POST /api/v1/comments`, `POST /api/v1/comments/{id}/like`, `DELETE /api/v1/comments/{id}`.
- `POST /api/v1/video/fetch`, `POST /api/v1/subtitle/fetch` (admin-only in practice).
- `POST /api/v1/push/devices/register`, `POST /api/v1/push/devices/unregister` (to link tokens to authenticated users).

### Optional auth (public but richer when logged in)

- **GET /api/v1/player/{id}** — Works for free content without auth; for premium/rent/buy, token is used to determine access.
- **POST /api/v1/access/check** — Same: no token = not logged in; with token = full access check (subscription, rental, purchase).

### Public (no auth required, but still require API key)

- Listings (movies, TV shows, VJs, hero, articles, live streams, actors).
- Read-only comments, payment gateways, subscription plans.
- Contact form, view tracking.
- Ad banners (`GET /api/v1/banners`).

## 7. User object and session refresh

Typical `user` shape (from `login`, `register`, `phone verify`, `google mobile`, `apple mobile`, and `GET /auth/me`):

- `id`, `name`, `email`, `phone`, `avatar`
- `plan` — e.g. "FREE", or plan name when subscribed
- `planStatus` — `"NONE"` | `"ACTIVE"` | `"PENDING"` | `"EXPIRED"`
- `renewalDate` — when subscription renews (if applicable)
- `emailVerified` — boolean

Use `GET /auth/me` to refresh user and subscription state (e.g. after payment, expiry, or login from another device).***
