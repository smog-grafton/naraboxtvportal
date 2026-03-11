# Portal media pipeline overview

## Role

The Portal (naraboxt-lara) is the main app for content and playback. It **imports** media via the CDN (remote fetch or upload) and stores playback metadata in **VideoSource** and **DownloadSource**. It does not run HLS or faststart itself.

## Flow

1. **Import** – Admin uses Filament (Movie/Episode → Video Sources) to add a “Fetched” source with a URL. Portal calls CDN `POST /api/v1/media/import` (or upload/telegram-intake). CDN fetches the file, runs faststart and (locally or via worker) HLS, then marks the source ready.
2. **Metadata** – Portal stores `cdn_asset_id`, `cdn_source_id`, and playback URLs in **VideoSource.metadata** (`hls_master_url`, `mp4_play_url`, `download_url`, `qualities`, etc.), often after calling CDN `GET /api/v1/media/{assetId}/playback`.
3. **Worker sync** – When the Laravel worker finishes (or CDN finishes pull-based HLS), the worker can POST to Portal `POST /api/v1/worker/sync` (Bearer `PORTAL_WORKER_API_TOKEN`) with `cdn_asset_id`, `cdn_source_id`, `status`. Portal runs **VideoSourceDerivationService::ensureDerivedSourcesForCdnUrl()** so MP4/HLS sibling sources and variants are created or updated.
4. **Playback** – **PlayerController** uses **CdnMediaClientService::getPlaybackManifest()** when `services.cdn.use_playback_manifest` is true, otherwise uses stored metadata to build the player payload (HLS master URL, MP4 URL, qualities).

## Worker-driven HLS

With the new **pull-based** worker flow:

- CDN still performs faststart locally and delegates HLS to the worker.
- Worker generates HLS and returns an artifact URL; CDN pulls the ZIP and installs it.
- Portal behaviour is unchanged: it still gets playback from CDN and can receive worker sync callbacks to refresh derived sources. No new migrations or config are required in the Portal for the pull flow; existing `PORTAL_WORKER_API_TOKEN` and `WorkerSyncController` remain in use.

## Relevant config

- **services.cdn** – base_url, api_token, ingest_secret, use_playback_manifest, etc.
- **services.worker_api_token** – `PORTAL_WORKER_API_TOKEN` for `/api/v1/worker/sync`.

## Key files

- **CdnMediaClientService** – HTTP client for CDN import, upload, playback, lookup.
- **VideoFetchController** – Handles fetch from URL (import now/queue, strategy).
- **VideoSourceDerivationService** – Builds MP4/HLS sibling and variant sources from CDN URLs.
- **WorkerSyncController** – Receives worker sync and triggers derivation.
- **PlayerController** – Builds playback payload (manifest or metadata).
