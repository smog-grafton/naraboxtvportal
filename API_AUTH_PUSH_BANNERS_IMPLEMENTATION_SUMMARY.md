# Auth, push, banners & API security implementation summary

This file summarizes the backend upgrades implemented across authentication, push notifications, ad banners, API key protection, and documentation polish.

## 1. What was already in place

- **Core auth:** Email/password registration and login (`AuthController`), email verification codes, password reset, Sanctum tokens, and a Google OAuth web flow using Socialite.
- **Content & payments:** Fully implemented movies/TV/VJs, playback, access checks, subscriptions, payments, dashboard, and Scribe-based API docs (see `API_DOCUMENTATION_PROFESSIONALIZATION_SUMMARY.md`).
- **Filament admin:** Rich resources for movies, TV shows, VJs, payment gateways/transactions, etc.

## 2. New database tables and models

All new tables are created in a single, guarded migration (`2026_03_12_000300_create_auth_push_banner_support_tables.php`) that uses `Schema::hasTable()` so `php artisan migrate` can run safely end-to-end.

- **Phone verification codes**
  - Table: `phone_verification_codes`
  - Model: `App\Models\PhoneVerificationCode`
  - Fields: `phone`, `code` (6 digits), `expires_at`, `used`, `attempts`.
  - Purpose: OTP-based phone login (request/verify).

- **Social accounts**
  - Table: `social_accounts`
  - Model: `App\Models\SocialAccount`
  - Fields: `user_id`, `provider` (`google`, `apple`, ...), `provider_user_id`, `email`, `raw_profile` (JSON), `last_login_at`.
  - Purpose: Stable linkage between users and external identity providers.

- **Push devices**
  - Table: `push_devices`
  - Model: `App\Models\PushDevice`
  - Fields: `user_id` (nullable), `platform` (`android`, `ios`, `web`, `other`), `provider` (`fcm`, `onesignal`, `custom`), `token`, `device_id`, `device_name`, `app_version`, `is_active`, `last_seen_at`.
  - Purpose: Device registry for push notifications.

- **Push notifications**
  - Table: `push_notifications`
  - Model: `App\Models\PushNotification`
  - Fields: `title`, `body`, `image_url`, `deep_link`, `target_platform` (`all`, `android`, `ios`, `web`), `target_audience` (`all`, `subscribed`, `free`, `custom`), `filters` (JSON), `provider`, `status` (`draft`, `queued`, `sending`, `sent`, `failed`), `sent_at`, `success_count`, `failure_count`, `last_error` (JSON).
  - Purpose: Logical notifications, sent to many devices.

- **Ad banners**
  - Table: `ad_banners`
  - Model: `App\Models\AdBanner`
  - Fields: `name`, `slug`, `type` (`image` or `script`), `image_path`, `script_content`, `target_url`, `width`, `height`, `placement`, `platform` (`all`, `app`, `web`), `is_active`, `active_from`, `active_until`, `sort_order`, `notes`.
  - Scope: `active()` to filter by `is_active` and active window.
  - Purpose: Ad inventory for web/app banner slots.

## 3. Auth upgrades (phone, Google mobile, Apple)

All auth flows now share a **common response shape**, implemented via `AuthController::buildAuthPayload()`:

```json
{
  "message": "Login successful",
  "data": {
    "user": { ... },
    "token": "<sanctum_token>",
    "auth_provider": "email|phone|google|apple",
    "is_new_user": false,
    "requires_verification": false
  }
}
```

### 3.1 Phone OTP login

New model/service:

- `PhoneVerificationCode` – creates and validates phone OTPs.
- `SmsService` – provider-agnostic SMS sender (currently logs OTPs for dev/local; ready to be wired to a real gateway via config/env later).

New endpoints in `AuthController`:

- `POST /api/v1/auth/phone/request-otp`
  - Body: `{ "phone": "<E.164 or local phone string>" }`.
  - Behavior: creates a `phone_verification_codes` record and calls `SmsService::sendOtp(phone, code)`.

- `POST /api/v1/auth/phone/verify-otp`
  - Body: `{ "phone": "...", "code": "123456", "name"?: "...", "email"?: "..." }`.
  - Behavior:
    - Validates OTP (not used + not expired).
    - Resolves or creates a user:
      - existing by `phone`, or
      - existing by `email` (if provided), or
      - new customer user (`FREE` plan).
    - Links phone and (optionally) email.
    - Returns common auth payload with `auth_provider: "phone"` and `is_new_user` flag.

### 3.2 Google login (web + mobile)

Refactored existing Google logic into a shared helper:

- `AuthController::findOrCreateUserFromGoogleUser($googleUser)`:
  - Finds user via `social_accounts` or email.
  - Creates a new `FREE` user if needed.
  - Ensures `SocialAccount` exists/updated (`provider = google`).
  - Verifies email and updates avatar.

Web flow (existing, now using shared helper):

- `GET /api/v1/auth/google/url` – returns Google redirect URL.
- `GET /api/v1/auth/google` – redirects user to Google.
- `GET /api/v1/auth/google/callback` – handles callback, issues Sanctum token, and redirects to frontend with `token` and minimal user info.

Mobile flow (new):

- `POST /api/v1/auth/google/mobile`
  - Body: `{ "access_token": "<google_access_token>" }`.
  - Uses `Socialite::userFromToken()` + `findOrCreateUserFromGoogleUser()`.
  - Returns common auth payload with `auth_provider: "google"`.

### 3.3 Apple login (mobile)

New endpoint:

- `POST /api/v1/auth/apple/mobile`
  - Body: `{ "apple_user_id": "<Apple sub>", "email"?: "...", "name"?: "..." }`.
  - Behavior:
    - Looks up existing `SocialAccount` for Apple by `apple_user_id`, else tries `email`, else creates a new `FREE` user.
    - Creates/updates a `social_accounts` record (`provider = apple`).
    - Returns common auth payload with `auth_provider: "apple"` and `is_new_user` flag.

Note: This implementation assumes the mobile app has already validated the Apple identity token. Server-side validation can be added later for higher assurance.

## 4. API key–driven app access

New config and middleware:

- `config/api.php`:
  - `key` (from `APP_API_KEY`).
  - `header` (`APP_API_KEY_HEADER`, default `X-API-KEY`).
  - `enabled` (`APP_API_KEY_ENABLED`, default `true`).

- `App\Http\Middleware\ValidateApiKey`:
  - If disabled or no key configured, allows all requests.
  - Else checks the configured header against `APP_API_KEY`.
  - On failure, returns a consistent **401** JSON error:

    ```json
    {
      "error": "Invalid or missing API key",
      "code": "INVALID_API_KEY",
      "message": "Your client is not authorized to call this API. Please include a valid API key in the request headers."
    }
    ```

Middleware wiring:

- `bootstrap/app.php`: alias `app.api_key` → `ValidateApiKey`.
- `routes/api.php`: the main v1 group is now:

  ```php
  Route::prefix('v1')->middleware(['app.api_key'])->group(function () {
      // ...
  });
  ```

Worker sync and webhooks remain outside this group and use their own tokens.

## 5. Push notifications

### 5.1 Configuration and service

- `config/push.php`:
  - `PUSH_PROVIDER` controls default provider (`log`, `fcm`, `onesignal`).
  - Provider-specific env sections for FCM/OneSignal are scaffolded.

- `App\Services\PushNotificationService`:
  - `send(PushNotification $notification): array`:
    - Selects active `PushDevice` records matching `target_platform`.
    - For now, logs sends (safe default); ready for FCM/OneSignal integration.
    - Updates `status`, `sent_at`, `success_count`, `failure_count`, `last_error`.

### 5.2 Device registration API

New controller: `App\Http\Controllers\Api\PushDeviceController`.

Routes (in v1 group, behind app API key; register/unregister also wrapped in `auth:sanctum` to bind devices to users):

- `POST /api/v1/push/devices/register`
  - Body: `platform`, `provider`, `token`, optional `device_id`, `device_name`, `app_version`.
  - Uses `PushDevice::updateOrCreate()` to upsert by (`provider`, `token`).

- `POST /api/v1/push/devices/unregister`
  - Body: `provider`, `token`.
  - Marks `is_active` as `false`.

### 5.3 Filament push resources and auto-send

New Filament resource: `PushNotificationResource` + pages.

- Allows admins to:
  - Create/edit notifications (title/body/image/deep link/targeting).
  - See status and send statistics.
  - Trigger **Send now** from the table, which calls `PushNotificationService::send()`.

Auto-send on content save:

- `MovieResource`, `TVShowResource`, and `VJResource` forms now have a **“Push Notification on Save”** section with:
  - `send_push_on_save` (toggle).
  - `push_title`, `push_body`, `push_image_url`, `push_target_platform`.
- The corresponding `Create*` and `Edit*` pages:
  - Inspect form state in `afterCreate()` / `afterSave()`.
  - If enabled, create a `PushNotification` with a sensible default deep link:
    - `app://movie/{id}`
    - `app://tv-show/{id}`
    - `app://vj/{id}`
  - Immediately call `PushNotificationService::send(...)`.

## 6. Ad banners

New model/resource:

- `AdBanner` model (see section 2).
- Filament `AdBannerResource` with:
  - Basic info: name, slug, type.
  - Image/script content.
  - Placement and platform targeting.
  - Active window and sort order.

New API controller:

- `App\Http\Controllers\Api\BannerController`:
  - `GET /api/v1/banners?placement=...&platform=...&limit=...`
  - Uses `AdBanner::active()` and filters by placement/platform.
  - Returns a clean, API-friendly shape with:
    - `type`, `image_url`, `script_content`, `target_url`, `width`, `height`, `placement`, `platform`.

Usage patterns:

- Web/frontend: call `/banners` per slot (e.g. `home_hero`, `home_sidebar`), render either `<img>` or safe script container.
- Mobile: call `/banners` for `platform=app` and render image banners; typically ignore script banners unless a secure WebView is used.

## 7. Documentation updates

Markdown docs:

- **`docs/AUTHENTICATION.md`**
  - Now documents:
    - Common auth payload shape (`user`, `token`, `auth_provider`, `is_new_user`, `requires_verification`).
    - Email/password, phone OTP, Google mobile, and Apple mobile flows.
    - App-level API key header requirement for `/api/v1/*`.

- **`docs/PUSH_NOTIFICATIONS.md`** (new)
  - Describes:
    - Device registration/unregistration API.
    - Provider abstraction via `config/push.php`.
    - Filament `Push Notifications` resource and “Send now” action.
    - Auto-send on save for Movie/TV Show/VJ.
    - Expected deep-link behavior on the client.

- **`docs/AD_BANNERS.md`** (new)
  - Documents:
    - `AdBanner` model fields.
    - Filament `Ad Banners` resource.
    - `GET /banners` API and typical usage on web/app.

- **`docs/API_SECURITY.md`** (new)
  - Summarizes:
    - App-level API key.
    - Sanctum auth.
    - Middleware (auth, email.verified, app.api_key, worker.api).
    - Recommended headers and response handling.

- **`docs/FRONTEND_INTEGRATION_GUIDE.md`**
  - Header section now includes `X-API-KEY`.
  - Adds sections for:
    - Ad banner consumption (placements, platforms).
    - Push device registration and deep-link handling.
    - Cross-links to `AUTHENTICATION`, `PUSH_NOTIFICATIONS`, `AD_BANNERS`, and `API_SECURITY`.

- **`docs/DOMAIN_MODEL.md`**
  - Expanded with:
    - `SocialAccount`, `PhoneVerificationCode`, `PushDevice`, `PushNotification`, `AdBanner` entity descriptions and relationships.

Scribe/OpenAPI:

- New endpoints (phone auth, mobile Google/Apple, banners, push devices) have controller-level docblocks suitable for Scribe extraction.
- To refresh docs and OpenAPI after these changes:

```bash
php artisan scribe:generate

mkdir -p storage/app/scribe
cp storage/app/private/scribe/openapi.yaml storage/app/scribe/openapi.yaml
cp storage/app/private/scribe/collection.json storage/app/scribe/collection.json

# Optional: keep a Git-tracked copy
cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
```

## 8. How to use this from clients

For **every** app request:

- Send `X-API-KEY: <APP_API_KEY>`.
- For authenticated flows, also send `Authorization: Bearer <token>`.

Auth:

- Email/password, phone OTP, Google, and Apple all return the same auth payload shape, so the client can centralize session handling.

Push:

- Register the push token after login (and on token refresh).
- Handle deep links in notifications to open movie/TV/VJ screens.

Banners:

- Fetch banners by placement/platform and render image or script banners as appropriate.

## 9. Remaining assumptions and extension points

- **SMS gateway:** `SmsService` currently logs OTPs; wire to a real SMS provider for production and add rate limiting if needed.
- **Push providers:** `PushNotificationService` is provider-agnostic and currently logs payloads; connect it to FCM/OneSignal as required.
- **Apple token validation:** For maximum security, add server-side validation of Apple identity tokens.
- **Audience filters & tracking:** Future work can extend `target_audience`, `filters`, and add impression/click tracking for banners and push notifications.

With these changes, the backend is ready for:

- Mobile-first auth (phone, Google, Apple).
- Configurable, provider-agnostic push notifications with device registration.
- API-driven ad banners with placement and platform targeting.
- Clear documentation and security headers suitable for mobile/web apps and AI tooling.***
