# Media pipeline

This document summarizes how media gets into the portal and how playback URLs are produced. It is aimed at integrators and AI designers who need to understand the system; it does not define new API endpoints.

## Role of the portal

The **NaraBox TV Portal** (this Laravel app) is the source of truth for **metadata** (movies, TV shows, episodes, VJs, categories, etc.) and **access control** (subscriptions, rentals, purchases). It does **not** stream video itself; it stores or fetches **playback manifests** (HLS/MP4 URLs) and serves them via the **Player** API.

## High-level flow

1. **Ingest** — An admin adds or imports media (e.g. via Filament). For “fetched” or CDN-backed sources, the portal calls the **CDN** (e.g. `POST /api/v1/media/import` or upload/telegram intake). The CDN fetches the file, runs faststart and (locally or via a worker) HLS, then marks the source ready.
2. **Metadata** — The portal stores `cdn_asset_id`, `cdn_source_id`, and playback URLs in **VideoSource** (e.g. in `metadata`: `hls_master_url`, `mp4_play_url`, `download_url`, `qualities`). It may call the CDN’s playback endpoint (e.g. `GET /api/v1/media/{assetId}/playback`) to populate this.
3. **Worker sync** — When an external worker (or the CDN) finishes processing, it can notify the portal with `POST /api/v1/worker/sync` (Bearer `PORTAL_WORKER_API_TOKEN`) and payload like `cdn_asset_id`, `cdn_source_id`, `status`. The portal then runs **VideoSourceDerivationService** so that MP4/HLS sibling sources and variants are created or updated.
4. **Playback** — **PlayerController** uses **CdnMediaClientService::getPlaybackManifest()** when `services.cdn.use_playback_manifest` is true; otherwise it builds the player payload from stored **VideoSource** metadata (HLS master URL, MP4 URL, qualities).

## Key config

- **config/services.php** (or env): `cdn` — base_url, api_token, ingest_secret, `use_playback_manifest`, etc.
- **PORTAL_WORKER_API_TOKEN** — Used by the worker to call `POST /api/v1/worker/sync`. Same value as the worker’s `PORTAL_API_TOKEN`.

## Key code (for maintainers)

- **CdnMediaClientService** — HTTP client for CDN: import, upload, playback, lookup.
- **VideoFetchController** — “Fetch from URL” (import now/queue, strategy).
- **VideoSourceDerivationService** — Builds MP4/HLS sibling and variant sources from CDN URLs.
- **WorkerSyncController** — Handles worker sync and triggers derivation.
- **PlayerController** — Builds the playback payload (from manifest or from stored metadata).

## Frontend impact

- The **frontend** only calls **GET /api/v1/player/{id}** (and optionally **GET /downloads/{id}**). It does not call the CDN or worker.
- Response shapes (videoUrl, videoSources, playback.type, playback.url, qualities, subtitles, downloadSources) are stable; whether the portal got them from the CDN manifest or from stored metadata is an implementation detail.

For more detail, see the project root **[MEDIA_PIPELINE_OVERVIEW.md](../MEDIA_PIPELINE_OVERVIEW.md)**.
