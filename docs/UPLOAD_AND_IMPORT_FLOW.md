# Upload and import flow

This document describes how media is **ingested** into the portal from an operator’s perspective. It is **not** a public API flow for end-users; it informs AI/docs about the system and future integrations.

## Entry points

1. **Filament admin** — Movies and TV shows (and episodes) are managed in the Laravel Filament panel. Admins can:
   - Create/edit movies and TV shows.
   - Add **Video sources** (URL, local, or “Fetched” with a URL that the CDN will fetch).
   - Add **Download sources** and **Subtitles**.

2. **CDN import** — The portal can call the CDN’s import/upload API (e.g. `POST /api/v1/media/import`) with a source URL. The CDN fetches the file, runs faststart and (when configured) HLS via a worker, then notifies or is polled for playback URLs.

3. **Worker sync** — After the CDN/worker finishes processing, it can `POST /api/v1/worker/sync` to the portal with `cdn_asset_id`, `cdn_source_id`, and status. The portal then runs **VideoSourceDerivationService** to create or update derived sources (MP4/HLS variants) and store playback metadata.

4. **Telegram ingest** — The portal exposes `POST /api/telegram/ingest-notify` for external systems (e.g. Telegram bot) to notify about new content. This is part of the ingestion pipeline, not a consumer-facing API.

5. **CDN fetch-and-push** — `POST /api/cdn/fetch-and-push` is used internally to trigger the CDN to fetch a URL and push the result back into the portal’s metadata. Rate-limited; not for public use.

## Public API vs internal

- **Public API** (documented at `/docs/api/v1`): Hero, movies, TV shows, search, VJs, articles, player, access, downloads, auth, dashboard, payments, subscription plans, comments, etc.
- **Internal / admin / worker**: Video source management in Filament, worker sync, Telegram ingest, CDN fetch-and-push. These are either not in the public docs or explicitly excluded.

## Frontend impact

- The **Next.js frontend** (or any client) does **not** upload or import media. It only consumes the **Player**, **Movies**, **TV Shows**, **Access**, **Downloads**, and related public endpoints.
- “Upload” in the sense of **user-uploaded payment proof** is different: **POST /api/v1/payments/upload-proof** is a documented, authenticated endpoint for users to upload a payment receipt image.

For the technical pipeline (CDN, worker, derivation), see [MEDIA_PIPELINE.md](MEDIA_PIPELINE.md).
