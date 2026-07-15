# Push notifications

This document describes how device registration and push notifications work for the NaraBox TV apps.

## Overview

- Devices (mobile or web) register a **push token** so the backend can target notifications.
- Admins can create and send notifications from Filament (`Push Notifications` resource).
- Movie/TV show/VJ saves can optionally trigger a push.
- The provider layer is **pluggable** (log/FCM/OneSignal) via `config/push.php`.

## Device registration API

### Register / update device

```http
POST /api/v1/push/devices/register
Content-Type: application/json
X-API-KEY: <APP_API_KEY>
Authorization: Bearer <token>   // optional but recommended

{
  "platform": "android",         // android | ios | web | other
  "provider": "fcm",             // fcm | onesignal | custom
  "token": "<push_token>",
  "device_id": "ABC123",         // optional
  "device_name": "Pixel 7",      // optional
  "app_version": "1.0.0"         // optional
}
```

- If a device with the same `provider` + `token` exists, it is updated (including `user_id` when logged in).
- If not, a new record is created.

Typical success response:

```json
{
  "message": "Device registered successfully",
  "data": {
    "id": 1,
    "platform": "android",
    "provider": "fcm",
    "device_id": "ABC123",
    "device_name": "Pixel 7",
    "app_version": "1.0.0"
  }
}
```

### Unregister device

```http
POST /api/v1/push/devices/unregister
Content-Type: application/json
X-API-KEY: <APP_API_KEY>

{
  "provider": "fcm",
  "token": "<push_token>"
}
```

Marks the device as inactive so it no longer receives notifications.

## Provider abstraction

Configuration lives in `config/push.php`:

- `PUSH_PROVIDER` — `log` (default), `fcm`, or `onesignal`.
- Each provider has its own env-driven credentials section.

The core service is `App\Services\PushNotificationService::send(PushNotification $notification)`, which:

- Resolves the active provider.
- Selects target devices based on `target_platform` (`all`, `android`, `ios`, `web`) and `is_active`.
- Sends (currently logs for safety) and updates:
  - `status` (`sent` / `failed`)
  - `sent_at`
  - `success_count`
  - `failure_count`

When you wire FCM/OneSignal, extend this service to call the real provider SDK or HTTP API.

### Firebase Cloud Messaging (FCM) setup

Backend:

- In `.env`:

```env
PUSH_PROVIDER=fcm
FIREBASE_SERVER_KEY=your-fcm-server-key
FIREBASE_PROJECT_ID=your-firebase-project-id
```

- In `config/push.php`, the `fcm` section already reads these values:
  - `server_key` → `FIREBASE_SERVER_KEY`
  - `project_id` → `FIREBASE_PROJECT_ID`
- Extend `PushNotificationService::send()` to:
  - Build an FCM HTTP payload (`notification` + `data`).
  - Call `https://fcm.googleapis.com/fcm/send` with `Authorization: key=<FIREBASE_SERVER_KEY>`.
  - Use the device’s `token` as the FCM registration token.

Mobile apps:

- Configure Firebase SDK in your iOS/Android project using the same `FIREBASE_PROJECT_ID` / app credentials.
- Obtain the FCM token from the SDK and pass it to `/push/devices/register` with `"provider": "fcm"`.
- Handle incoming notifications and respect the `deep_link` field from the notification `data` payload.

### OneSignal setup

Backend:

- In `.env`:

```env
PUSH_PROVIDER=onesignal
ONESIGNAL_APP_ID=your-onesignal-app-id
ONESIGNAL_API_KEY=your-onesignal-rest-api-key
```

- In `config/push.php`, the `onesignal` section already reads:
  - `app_id` → `ONESIGNAL_APP_ID`
  - `api_key` → `ONESIGNAL_API_KEY`
- Extend `PushNotificationService::send()` to:
  - Call the OneSignal REST API (`https://api.onesignal.com/notifications`) with `app_id`, `include_player_ids` (from `PushDevice::token`), and notification content.

Mobile apps / web:

- Integrate the OneSignal SDK in your app (iOS, Android, Web).
- Make sure the OneSignal project uses the same `app_id` as configured on the backend.
- Register the device with OneSignal, capture the player id, and send it as `token` with `"provider": "onesignal"` to `/push/devices/register`.

### Other/custom providers

- Set `PUSH_PROVIDER=log` to disable actual sending and simply log notifications (useful for development).
- For any other provider:
  - Add a new key under `providers` in `config/push.php`.
  - Store its credentials via env.
  - Add a `case 'your_provider'` branch in `PushNotificationService::send()` to call its SDK or HTTP API.

## Admin push UI (Filament)

### Push Notifications resource

In the Filament admin, there is a `Push Notifications` resource backed by the `push_notifications` table.

Fields:

- `title`, `body`, `image_url`, `deep_link`
- `target_platform` — `all`, `android`, `ios`, `web`
- `target_audience` — currently `all`, `subscribed`, `free`, `custom` (filters JSON reserved for future use)
- `provider` — `default` (use config), `fcm`, `onesignal`
- `status`, `sent_at`, `success_count`, `failure_count`

From the index table, admins can:

- Create notifications in **draft** status.
- Click **Send now** to queue and send immediately.

### Auto-send on content save

The following Filament resources include a **“Push Notification on Save”** section:

- `MovieResource` (movies)
- `TVShowResource` (TV shows)
- `VJResource` (VJs)

Each has fields:

- `send_push_on_save` (toggle)
- `push_title` (defaults to title/name if empty)
- `push_body` (short message)
- `push_image_url` (optional image)
- `push_target_platform` (`all` / `android` / `ios` / `web`)

Behavior:

- On **create** or **edit**, if `send_push_on_save` is true, the backend:
  - Creates a `push_notifications` record with the chosen fields and a deep link such as:
    - `app://movie/{id}`
    - `app://tv-show/{id}`
    - `app://vj/{id}`
  - Immediately calls `PushNotificationService::send(...)`.

The mobile app should handle these deep links to navigate to the appropriate screen.

## Client responsibilities

- Store the device’s push token from FCM/OneSignal/APNs.
- Register the token via the API (and re-register on token refresh).
- Respect deep links from push payloads to navigate inside the app.
- Optionally display in-app banners or modals based on notification data.

## Regenerating Scribe docs after push/auth changes

After modifying controllers, routes, or models related to auth, push notifications, or banners, regenerate the Scribe docs and OpenAPI spec so the live docs stay in sync:

```bash
php artisan scribe:generate

mkdir -p storage/app/scribe
cp storage/app/private/scribe/openapi.yaml storage/app/scribe/openapi.yaml
cp storage/app/private/scribe/collection.json storage/app/scribe/collection.json

# Optional: keep a Git-tracked copy of the OpenAPI spec
cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
```

Then visit `/docs/api/v1` to confirm that the new endpoints and examples are visible.***

