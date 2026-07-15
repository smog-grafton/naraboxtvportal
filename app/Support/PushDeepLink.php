<?php

namespace App\Support;

class PushDeepLink
{
    public static function build(?string $destinationKind, mixed $destinationValue = null, ?string $customDeepLink = null): ?string
    {
        return match ($destinationKind) {
            'movie' => filled($destinationValue) ? 'app://movie/' . trim((string) $destinationValue) : null,
            'tv_show' => filled($destinationValue) ? 'app://tv-show/' . trim((string) $destinationValue) : null,
            'article' => filled($destinationValue) ? 'app://news/' . trim((string) $destinationValue) : null,
            'live' => filled($destinationValue) ? 'app://live/' . trim((string) $destinationValue) : null,
            'vj' => filled($destinationValue) ? 'app://vj/' . trim((string) $destinationValue) : null,
            'custom' => filled($customDeepLink) ? trim((string) $customDeepLink) : null,
            default => null,
        };
    }

    /**
     * @return array{kind: string, value: string|null, custom: string|null}
     */
    public static function parse(?string $deepLink): array
    {
        $link = trim((string) $deepLink);

        if ($link === '') {
            return [
                'kind' => 'custom',
                'value' => null,
                'custom' => null,
            ];
        }

        foreach ([
            'movie' => '#^app://movie/([^/?#]+)$#i',
            'tv_show' => '#^app://tv-show/([^/?#]+)$#i',
            'article' => '#^app://news/([^/?#]+)$#i',
            'live' => '#^app://live/([^/?#]+)$#i',
            'vj' => '#^app://vj/([^/?#]+)$#i',
        ] as $kind => $pattern) {
            if (preg_match($pattern, $link, $matches) === 1) {
                return [
                    'kind' => $kind,
                    'value' => $matches[1],
                    'custom' => null,
                ];
            }
        }

        return [
            'kind' => 'custom',
            'value' => null,
            'custom' => $link,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadData(?string $deepLink): array
    {
        $link = trim((string) $deepLink);
        if ($link === '') {
            return [];
        }

        $parsed = static::parse($link);
        $payload = [
            'deep_link' => $link,
        ];

        return match ($parsed['kind']) {
            'movie' => $payload + [
                'type' => 'movie',
                'media_type' => 'MOVIE',
                'media_id' => $parsed['value'],
            ],
            'tv_show' => $payload + [
                'type' => 'tv_show',
                'media_type' => 'TV_SHOW',
                'media_id' => $parsed['value'],
            ],
            'article' => is_numeric($parsed['value'])
                ? $payload + [
                    'type' => 'article',
                    'article_id' => $parsed['value'],
                ]
                : $payload + [
                    'type' => 'article',
                    'article_slug' => $parsed['value'],
                ],
            'live' => $payload + [
                'type' => 'live',
                'stream_id' => $parsed['value'],
            ],
            'vj' => $payload + [
                'type' => 'vj',
                'vj_id' => $parsed['value'],
            ],
            default => $payload,
        };
    }
}
