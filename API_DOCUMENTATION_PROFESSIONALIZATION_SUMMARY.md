 +# API Documentation Professionalization Summary
 +
 +This file summarizes the second pass of work done to upgrade the Narabox Portal API docs from “good enough” to a more complete, developer-grade, AI-friendly documentation system.
 +
 +## 1. What was audited
 +
 +- **Routes & controllers:** `routes/api.php` and all `app/Http/Controllers/Api/*` (with a special focus on `PlayerController`, `AccessController`, `PaymentController`, `IoTeCController`, `PawaPayController`, `DashboardController`).
 +- **Models & DB:** `app/Models/*` and the `naraboxt-lara` schema (notably `movies`, `tv_shows`, `episodes`, `video_sources`, `download_sources`, `watch_history`, `subscription_plans`, `user_subscriptions`, `user_rentals`, `user_purchases`, `payment_gateways`, `payment_transactions`, `payments`).
 +- **Docs config:** `config/scribe.php` (routes, groups, auth, OpenAPI/Postman generation, try-it-out).
 +- **Hand-written docs:** All markdown files under `docs/` (`DOMAIN_MODEL`, `PLAYBACK_FLOW`, `PAYMENT_GATEWAYS`, `ERRORS_AND_STATUS_CODES`, `FRONTEND_INTEGRATION_GUIDE`, etc.).
 +
 +The goal was to align the docs with the **actual business logic** for playback, access control, payments, subscriptions, and the user dashboard.
 +
 +## 2. Gaps identified in the previous docs
 +
 +- **Playback & access:**
 +  - The `GET /api/v1/player/{id}` endpoint did not fully document:
 +    - How `media_type` and `episode` are used.
 +    - The detailed shape of the `playback` object (HLS vs MP4, qualities).
 +    - How free vs premium vs paid content is enforced.
 +  - The `POST /api/v1/access/check` endpoint did not clearly list all possible `access_type` values or how to map them to UI states.
 +- **Payments & subscriptions:**
 +  - Gateway-specific flows (ioTec Pay, PawaPay) were only partially documented and some parameter names drifted from the real controllers.
 +  - The generic `POST /api/v1/payments/initiate`, `POST /api/v1/payments/upload-proof`, and `POST /api/v1/payments/verify` endpoints lacked explicit field/enum descriptions and realistic response examples.
 +- **Dashboard & user state:**
 +  - The `GET /api/v1/dashboard` endpoint shape was not clearly described, especially:
 +    - The `vault` structure (rentedIds, purchasedIds, watchHistory).
 +    - How `plan` and `planStatus` are derived from `user_subscriptions` and legacy subscription state.
 +- **AI friendliness:**
 +  - Many critical fields (e.g. `access_type`, subscription and payment statuses, playback fields) were only implicitly documented or only visible in example responses, not in structured descriptions.
 +  - Screen-to-endpoint mappings existed conceptually but needed tighter alignment with the actual controller logic.
 +
 +## 3. Endpoint-level documentation improvements
 +
 +The following controllers were enhanced with richer Scribe docblocks, including `@urlParam`, `@queryParam`, `@bodyParam`, and `@response` examples:
 +
 +- `PlayerController`
 +  - **GET /api/v1/player/{id}**
 +    - Now documents:
 +      - `id` (numeric id or slug), `media_type` (`MOVIE` | `TV_SHOW`), `episode` (episode id).
 +      - Response shape: `movie`, `episode`, `videoUrl`, `videoSources`, `subtitles`, `duration`, `poster`, `downloadSources`, and the nested `playback` object (`type`, `url`, `hls_master_url`, `mp4_play_url`, `qualities`, etc.).
 +      - Example responses for:
 +        - Successful playback (HLS with downloads).
 +        - 403 “locked” response for premium content without subscription.
 +        - 404 “Media not found”.
 +  - **POST /api/v1/watch-history** / **GET /api/v1/watch-history**
 +    - Added authentication requirement, field validation, and example payloads for resume/continue-watching flows.
 +
 +- `AccessController`
 +  - **POST /api/v1/access/check**
 +    - Clarified:
 +      - Input: `media_id`, `media_type` (`MOVIE` | `TV_SHOW`).
 +      - Normalized `access_type` values (`FREE`, `SUBSCRIPTION`, `PREMIUM`, `PURCHASED`, `RENTED`, `PENDING`, `PAID`).
 +      - How to use `has_access`, `requires_auth`, `requires_subscription`, `can_rent`, `can_buy`, `rent_price`, `buy_price`, `pending_payment`, and `transaction_ref` for the paywall UI.
 +    - Added 200/401/404 examples.
 +
 +- `PaymentController`
 +  - **GET /api/v1/payment-gateways**
 +    - Documented public gateway list structure (id, slug, code, displayName, type, instructions, logoUrl, etc.).
 +  - **POST /api/v1/payments/initiate**
 +    - Clarified usage for `RENT`, `BUY`, and `SUBSCRIPTION`.
 +    - Documented behavior for `MANUAL` vs `AUTOMATIC` gateways and the meaning of `transaction_ref`.
 +    - Added realistic examples for both manual and automatic responses.
 +  - **POST /api/v1/payments/upload-proof**
 +    - Documented file constraints (jpeg/jpg/png/pdf, 10MB max) and success/failure shapes.
 +  - **POST /api/v1/payments/verify**
 +    - Clarified how `status` behaves:
 +      - Manual: `APPROVED`, `PENDING`, `REJECTED`.
 +      - Automatic: simulated `SUCCESS` in this implementation (to be wired to real provider checks later).
 +
 +- `IoTeCController`
 +  - **POST /api/v1/iotec/initiate**
 +    - Documented:
 +      - Required fields: `type`, `media_id`+`media_type` (for RENT/BUY) or `subscription_plan_id` (for SUBSCRIPTION), `phone`, `return_url`.
 +      - Uganda phone format validation and error responses.
 +      - How `transaction_ref`, `payment_id`, and masked phone are emitted.
 +  - **POST /api/v1/iotec/status**
 +    - Documented normalized statuses: `success`, `failed`, `pending`, and how to use `redirect_url`.
 +  - **POST /api/v1/iotec/webhook**
 +    - Clarified that this is server-to-server only and is responsible for updating `payment_transactions` and granting access.
 +
 +- `PawaPayController`
 +  - **POST /api/v1/payments/pawapay/deposit/initiate**
 +    - Documented:
 +      - Fields: `type`, `media_id`, `media_type`, `subscription_plan_id`, `phone`, `provider`, `currency`, `deposit_id`, `client_reference_id`.
 +      - Relationship between `deposit_id` and `external_reference`.
 +      - Validation and failure responses (invalid MSISDN, unavailable gateway, invalid amount).
 +  - **GET /api/v1/payments/pawapay/deposit/{depositId}/status**
 +    - Documented normalized statuses from the frontend’s perspective: `COMPLETED`, `FAILED`, `PENDING`.
 +  - **depositWebhook / refundWebhook**
 +    - Marked deposit webhook as the primary integration point (server-to-server status check then grant access) and explicitly called out that refund webhooks are not yet enabled.
 +
 +- `DashboardController`
 +  - **GET /api/v1/dashboard**
 +    - Documented the full response:
 +      - `user` (id, name, email, phone, avatar, `plan`, `planStatus`, `renewalDate`).
 +      - `subscription` (plan, status, started_at, expires_at).
 +      - `pending_subscription` transaction (plan, status, transaction_ref, amount).
 +      - `vault` (rentedIds, purchasedIds, watchHistory).
 +      - Expanded `rentals`, `purchases`, and `transactions` structures for UI.
 +
 +These richer annotations feed directly into:
 +- The **Scribe HTML docs** at `/docs/api/v1`.
 +- The **OpenAPI** spec used by codegen and AI tools.
 +
 +## 4. Markdown guide refinements
 +
 +The following markdown docs were adjusted to better mirror the real controllers and flows:
 +
 +- `docs/PAYMENT_GATEWAYS.md`
 +  - **ioTec Pay section:**
 +    - Updated request parameters to match the real controller (`type`, `subscription_plan_id` / `media_id` + `media_type`, `phone`, `return_url`).
 +    - Clarified status polling via `POST /api/v1/iotec/status` and normalized responses (`success`, `pending`, `failed`, `redirect_url`).
 +  - **PawaPay section:**
 +    - Updated parameters to use `phone`, `provider` (`MTN_MOMO_UGA`, `AIRTEL_OAPI_UGA`), and `currency`.
 +    - Clarified that the frontend must use the returned `deposit_id` when polling `GET /api/v1/payments/pawapay/deposit/{depositId}/status`.
 +    - Documented typical JSON responses for `PENDING`, `COMPLETED`, and `FAILED`.
 +
 +- `docs/PLAYBACK_FLOW.md`
 +  - Already aligned closely with `PlayerController` and `AccessController`; verified and clarified:
 +    - The interplay between `/access/check` and `/player/{id}`.
 +    - How to interpret 403 responses from the player endpoint (using `reason`, `requires_subscription`, `can_rent`, `can_buy`, etc.).
 +    - How to use `playback.type`, `playback.url`, `videoSources`, and `subtitles` for HLS vs MP4.
 +
 +- `docs/ERRORS_AND_STATUS_CODES.md`
 +  - Confirmed error shapes against the real controllers, especially:
 +    - Structured 403 responses from the player/access flows.
 +    - How validation errors and generic 401/404 responses are returned.
 +  - Reinforced best practices for using `reason`, `requires_auth`, subscription and payment flags to drive UI decisions.
 +
 +- `docs/FRONTEND_INTEGRATION_GUIDE.md`
 +  - Cross-checked the **Homepage**, **Detail**, **Watch**, **Subscription/Rental**, and **Dashboard** sections against the improved endpoint docs to ensure terminology and parameters match.
 +
 +- `docs/DOMAIN_MODEL.md`
 +  - Verified access-related concepts (FREE, SUBSCRIPTION, PURCHASED, RENTED, PENDING) against the logic in `AccessController` and `PlayerController::checkAccessDetailed()`.
 +
 +> Note: the markdown docs are intentionally high-level and narrative. For exact shapes, the **OpenAPI spec** and Scribe-generated endpoint pages are now the primary machine-readable source of truth.
 +
 +## 5. Where the docs live and how to regenerate
 +
 +- **Self-hosted docs UI:**  
 +  - URL: `/docs/api/v1` (in production: `https://portal.naraboxtv.com/docs/api/v1`)  
 +  - Config: `config/scribe.php` (`type = 'laravel'`, `docs_url = '/docs/api/v1'`).
 +- **OpenAPI spec:**  
 +  - Generated to `storage/app/private/scribe/openapi.yaml` by Scribe, then copied to:
 +    - `storage/app/scribe/openapi.yaml` (so `/docs/api/v1.openapi` can serve it).
 +    - `docs/openapi.yaml` (versioned copy in Git).
 +- **Postman collection:**  
 +  - Generated to `storage/app/private/scribe/collection.json` then copied to `storage/app/scribe/collection.json`.
 +- **Hand-written conceptual docs:**  
 +  - All under `docs/` (overview, domain model, playback, media pipeline, uploads/import, errors, payment gateways, frontend integration, database schema, controllers index, etc.).
 +
 +To regenerate docs after code changes:
 +
 +```bash
 +php artisan scribe:generate
 +
 +# Ensure Scribe assets are in the path expected by the laravel docs routes
 +mkdir -p storage/app/scribe
 +cp storage/app/private/scribe/openapi.yaml storage/app/scribe/openapi.yaml
 +cp storage/app/private/scribe/collection.json storage/app/scribe/collection.json
 +
 +# Optional: keep a versioned copy of the spec in docs/
 +cp storage/app/private/scribe/openapi.yaml docs/openapi.yaml
 +```
 +
 +For full deployment instructions (including composer flags, cache commands, and copying Scribe artifacts on production), see `docs/DEPLOYMENT_WORKFLOW.md`.
 +
 +## 6. Remaining assumptions and limitations
 +
 +- Some payment provider integrations (especially Flutterwave and parts of automatic verification flows) still use placeholder logic on the backend and are documented as such. The docs clearly mark where a real provider call should be wired in.
 +- The OpenAPI spec is generated from Scribe’s understanding of controllers and docblocks. If new fields or flows are added in controllers without updating annotations, they may not appear in the spec until Scribe is regenerated.
 +- Existing linter warnings about dynamic relationships and facades (e.g. `Schema`, `watchHistory()`) predate this documentation pass and are not behavioral bugs but static-analysis limitations.
 +
 +## 7. Recommendations for future improvements
 +
 +- **Tighten validation and error schemas:**
 +  - Standardize validation error shape across all endpoints (eg. always use a `messages` map).
 +  - Add explicit error enums for payment and subscription failures.
 +- **Provider-specific docs:**
 +  - Once Flutterwave and other automatic providers are fully wired, add concrete examples of provider responses and callback payloads to `PAYMENT_GATEWAYS.md`.
 +- **More typed resources:**
 +  - Consider adding dedicated API Resources for dashboard, playback, and payment objects, and reference them explicitly in Scribe.
 +- **v2 planning:**
 +  - When breaking changes are needed (eg. restructuring playback or dashboard payloads), introduce `/api/v2/*` with a new `/docs/api/v2` instance and versioned `docs/openapi-v2.yaml`.
 +
 +With these changes, the Narabox Portal API docs are now structured so that:
 +- **Frontend engineers** can locate and understand the exact payloads for watch, paywall, subscription, and dashboard flows.
 +- **Mobile developers** (React Native, Flutter) can map endpoints directly to screens and state machines.
 +- **AI coding tools** can consume the OpenAPI spec and enriched examples to generate accurate client code and UI boilerplate with minimal guessing.
 +
