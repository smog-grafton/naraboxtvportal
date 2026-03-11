<?php

namespace App\Services;

class CdnUrlDerivationService
{
    /**
     * CDN base host for URL building (configurable).
     */
    protected string $cdnHost = 'cdn.naraboxtv.com';

    /**
     * Derive play and HLS master URLs from a CDN media URL.
     *
     * Input: https://cdn.naraboxtv.com/media/{uuid}/{id}/Name_naraboxtv_com.mp4
     * Output: [
     *   'download_url' => original URL,
     *   'play_url' => .../Name_naraboxtv_com_play.mp4,
     *   'hls_master_url' => https://cdn.../media-hls/{uuid}/{id}/master.m3u8,
     *   'uuid' => uuid,
     *   'source_id' => id,
     * ]
     *
     * @return array{download_url: string|null, play_url: string|null, hls_master_url: string|null, uuid: string|null, source_id: int|null}|null
     */
    public function deriveFromCdnUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parsed = parse_url($url);
        $path = (string) ($parsed['path'] ?? '');
        $path = '/' . trim($path, '/');
        $segments = array_values(array_filter(explode('/', $path)));

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? $this->cdnHost;
        $base = $scheme . '://' . $host;

        $uuid = null;
        $sourceId = null;

        if (str_starts_with($path, '/media/') && count($segments) >= 4) {
            // /media/{uuid}/{sourceId}/{filename}
            $uuid = $segments[1];
            $sourceId = is_numeric($segments[2]) ? (int) $segments[2] : null;
            $filename = $segments[3] ?? '';

            if ($uuid === '' || $sourceId === null) {
                return null;
            }

            $isPlayMp4 = str_ends_with(strtolower($filename), '_play.mp4');
            $downloadUrl = $base . '/media/' . $uuid . '/' . $sourceId . '/' . $filename;

            if ($isPlayMp4) {
                $originalName = preg_replace('/_play\.mp4$/i', '.mp4', $filename);
                $playUrl = $downloadUrl;
                $downloadUrl = $base . '/media/' . $uuid . '/' . $sourceId . '/' . $originalName;
            } else {
                $playUrl = $base . '/media/' . $uuid . '/' . $sourceId . '/' . preg_replace('/\.mp4$/i', '_play.mp4', $filename);
            }

            $hlsMasterUrl = $base . '/media-hls/' . $uuid . '/' . $sourceId . '/master.m3u8';

            return [
                'download_url' => $downloadUrl,
                'play_url' => $playUrl,
                'hls_master_url' => $hlsMasterUrl,
                'uuid' => $uuid,
                'source_id' => $sourceId,
            ];
        }

        if (str_starts_with($path, '/media-hls/') && count($segments) >= 4) {
            // /media-hls/{uuid}/{sourceId}/...
            $uuid = $segments[1];
            $sourceId = is_numeric($segments[2]) ? (int) $segments[2] : null;
            if ($uuid === '' || $sourceId === null) {
                return null;
            }
            $hlsMasterUrl = $base . '/media-hls/' . $uuid . '/' . $sourceId . '/master.m3u8';

            return [
                'download_url' => null,
                'play_url' => null,
                'hls_master_url' => $hlsMasterUrl,
                'uuid' => $uuid,
                'source_id' => $sourceId,
            ];
        }

        return null;
    }

    /**
     * Check if the given URL is a CDN media or media-hls URL that we can derive from.
     */
    public function isCdnDerivableUrl(string $url): bool
    {
        return $this->deriveFromCdnUrl($url) !== null;
    }

    /**
     * Set the CDN host (e.g. for testing).
     */
    public function setCdnHost(string $host): self
    {
        $this->cdnHost = $host;

        return $this;
    }
}
