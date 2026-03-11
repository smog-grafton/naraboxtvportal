# Production Auth & Access Setup ( naraboxtv.com + portal.naraboxtv.com )

This guide helps resolve:
- Access Denied / Unlock Mission when user has active subscription
- Google login session lost on navigation
- Player and download access issues across domains

## Laravel Backend (Hostinger - portal.naraboxtv.com)

### Required .env Variables

```env
# API / App
APP_URL=https://portal.naraboxtv.com
FRONTEND_URL=https://naraboxtv.com

# For cross-origin auth (add to .env)
SANCTUM_STATEFUL_DOMAINS=naraboxtv.com,www.naraboxtv.com,portal.naraboxtv.com
```

### CORS

`config/cors.php` already includes:
- `https://naraboxtv.com` and `https://www.naraboxtv.com` in allowed_origins
- Pattern `#^https?://(www\.)?naraboxtv\.com$#` for origin matching
- `allowed_headers` = `['*']` (allows Authorization Bearer token)
- `supports_credentials` = true

### Google OAuth

- `GOOGLE_REDIRECT_URI` must be: `https://portal.naraboxtv.com/api/v1/auth/google/callback`
- Add this exact URL in Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 redirect URIs

---

## Next.js Frontend (Vercel - naraboxtv.com)

### Required Environment Variables

```env
NEXT_PUBLIC_API_URL=https://portal.naraboxtv.com/api/v1
NEXT_PUBLIC_SITE_URL=https://naraboxtv.com
```

In Vercel:
1. Project → Settings → Environment Variables
2. Add `NEXT_PUBLIC_API_URL` = `https://portal.naraboxtv.com/api/v1`
3. Add `NEXT_PUBLIC_SITE_URL` = `https://naraboxtv.com` (if used)

---

## Changes Made

1. **hasPremiumAccess**: Any active subscription (Daily, Weekly, Monthly, PRO, ELITE) now grants premium access. Previously only PRO/ELITE were accepted.
2. **AuthContext**: Token is no longer cleared on network/CORS errors; only on explicit 401 Unauthorized. Prevents session loss when the API is temporarily unreachable.
3. **Google OAuth callback**: Calls `refreshUser()` before redirecting to dashboard so the Navbar shows the user name immediately.
4. **Footer links**: Updated `/legal` → `/legal/legal` and `/cookies` → `/legal/cookies` to fix 404s.
5. **CORS**: Added explicit production origins and patterns for naraboxtv.com.

---

## Verification

1. Log in (email or Google) and verify the header shows your name after navigation.
2. Subscribe or pay for a plan, then open a premium movie. You should see the player instead of "Access Denied".
3. Visit `/legal/legal` and `/legal/cookies` – both should load.
4. Check browser console for CORS errors when calling the API.
