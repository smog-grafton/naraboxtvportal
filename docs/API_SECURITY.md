# API security

This document summarizes how client apps should authenticate with the NaraBox TV API and protect requests.

## 1. App-level API key

All `/api/v1/*` routes are protected by a lightweight, **configurable API key** to ensure only approved client apps call the API.

- Environment:
  - `APP_API_KEY` — long random string.
  - `APP_API_KEY_ENABLED` — `true`/`false` (toggles enforcement).
  - `APP_API_KEY_HEADER` — header name (defaults to `X-API-KEY`).
- Config: `config/api.php`.

Clients must send:

```http
X-API-KEY: <APP_API_KEY>
```

If the key is missing or invalid, the response is:

```json
{
  "error": "Invalid or missing API key",
  "code": "INVALID_API_KEY",
  "message": "Your client is not authorized to call this API. Please include a valid API key in the request headers."
}
```

This is **in addition to** user authentication (Bearer tokens) and does not replace login.

## 2. User authentication (Sanctum)

User-level auth uses **Laravel Sanctum** personal access tokens:

- Register/login/phone/Google/Apple flows return a token in the shared auth payload:

```http
Authorization: Bearer <sanctum_token>
```

See `docs/AUTHENTICATION.md` for detailed flows and response shapes.

## 3. Sensitive routes and middleware

Middleware:

- `auth:sanctum` — protects user-specific routes (dashboard, payments, watch history, comments write, device registration, etc.).
- `email.verified` — wraps payment routes to ensure the user’s email is verified.
- `app.api_key` — wraps all `/api/v1/*` routes to enforce the app key.
- `worker.api` — guards internal worker sync using `PORTAL_WORKER_API_TOKEN`.

Design principles:

- **Public read** (catalog, hero, banners, search, player for free content) — no user token required, but still require API key.
- **User read/write** (dashboard, payments, comments, watch history) — require both API key and Bearer token.
- **Internal webhooks/worker** — separate routes outside v1 group with their own tokens and throttling.

## 4. Mobile and web app guidance

For each request from a trusted client (web, React Native, Flutter):

1. Always send the app key header:

   ```http
   X-API-KEY: <APP_API_KEY>
   ```

2. For authenticated requests, also send:

   ```http
   Authorization: Bearer <sanctum_token>
   ```

3. On **401**:
   - If body mentions invalid API key → app misconfigured (fix env or header).
   - If body is `{"message":"Unauthenticated."}` → user token invalid/expired (log out and re-authenticate).

4. On **403** from access/player/payment endpoints:
   - Use the detailed body fields (`reason`, `requires_auth`, `requires_subscription`, `can_rent`, `can_buy`, etc.) to drive CTAs instead of guessing.

## 5. Rotating keys and tokens

- **APP_API_KEY** can be rotated by:
  - Generating a new random value.
  - Updating it in all deployed clients (using remote config if needed).
- Sanctum user tokens can be invalidated by:
  - Calling `POST /api/v1/auth/logout` (current token).
  - Forcing logout on password reset (already implemented).

For high-security deployments, combine:

- Short-lived tokens (rotate or expire frequently).
- TLS/HTTPS everywhere.
- Device-level secure storage (Keychain/SecureStorage/EncryptedSharedPreferences) for tokens and API key.

