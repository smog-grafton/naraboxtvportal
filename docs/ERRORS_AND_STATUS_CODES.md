# Errors and status codes

This document describes how the API reports errors and what status codes to expect.

## HTTP status codes

| Code | Meaning | Typical use |
|------|---------|-------------|
| **200** | OK | Successful GET/PUT/DELETE; many POSTs (e.g. login, access check). |
| **201** | Created | Resource created (e.g. register, comment). |
| **400** | Bad Request | Malformed request (rare; validation usually returns 422). |
| **401** | Unauthorized | Missing or invalid Bearer token on a protected endpoint. |
| **403** | Forbidden | Valid token but not allowed (e.g. no access to content; see body for reason). |
| **404** | Not Found | Resource does not exist (movie, TV show, plan, episode, etc.). |
| **422** | Unprocessable Entity | Validation failed (e.g. invalid body, duplicate email). |
| **429** | Too Many Requests | Rate limit exceeded (e.g. throttle on contact, ingest). |
| **500** | Server Error | Unexpected server error (check logs; not contractually defined). |

## Response shapes

### 401 Unauthorized

Typically:

```json
{
  "message": "Unauthenticated."
}
```

Or a custom body such as:

```json
{
  "error": "Unauthorized"
}
```

### 403 Forbidden (e.g. player / access)

When the user cannot play the content, the body is richer:

```json
{
  "error": "This content requires a premium subscription...",
  "message": "...",
  "has_access": false,
  "reason": "...",
  "requires_payment": false,
  "requires_subscription": true,
  "requires_auth": false,
  "access_type": "PREMIUM",
  "can_rent": true,
  "can_buy": true,
  "rent_price": 5000,
  "buy_price": 15000,
  "is_free": false,
  "is_premium": true,
  "pending_payment": false,
  "transaction_ref": null
}
```

Use these fields to drive the paywall UI (login, subscribe, rent, buy, or “payment pending”).

### 404 Not Found

- **Media:** `GET /player/{id}` or movie/TV show detail may return `404` with `{"error": "Media not found"}` or `{"error": "No video source available", "requiresVideoSource": true}`.
- **Generic:** Some endpoints return `{"error": "..."}` with a short message.

### 422 Validation error

Standard shape:

```json
{
  "error": "Validation failed",
  "messages": {
    "email": ["The email has already been taken."],
    "password": ["The password confirmation does not match."]
  }
}
```

Or a single message:

```json
{
  "error": "Email already registered"
}
```

Frontend should display field-level errors from `messages` when present.

## Common error messages (examples)

- **Auth:** `"Invalid credentials"`, `"Unauthorized"`, `"Email already registered"`, `"Invalid or expired verification code"`, `"Invalid or expired reset token"`.
- **Access:** `"Please log in to access this content"`, `"This content requires a premium subscription..."`, `"Payment required"`, `"Your payment is pending admin approval..."`.
- **Player:** `"Media not found"`, `"No video source available"`.
- **Payments:** Depends on gateway and flow; check the response body and status for initiate/verify endpoints.

## Best practices for frontend

1. **401** — Redirect to login or show login modal; store intended destination for post-login redirect.
2. **403** on player/access — Use `reason`, `requires_auth`, `requires_subscription`, `can_rent`, `can_buy`, `rent_price`, `buy_price`, `pending_payment`, `transaction_ref` to show the right CTA.
3. **422** — Show validation errors from `messages` next to form fields.
4. **404** — Show “Not found” or “This content is unavailable”.
5. **429** — Show “Too many requests; please try again later.”
6. **500** — Show a generic error and optionally a support message; do not rely on body shape.
