<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Models\EditorialCategory;
use App\Models\User;

trait HandlesEditorialArticleData
{
    protected function prepareArticleData(array $data, bool $assignCurrentUser = false): array
    {
        if (! empty($data['image_url'])) {
            $data['image'] = $data['image_url'];
        }
        unset($data['image_url']);

        $videoType = $data['video_type'] ?? null;
        if ($videoType === 'upload' && ! empty($data['video_file'])) {
            $data['video_url'] = $data['video_file'];
        } elseif ($videoType === 'none') {
            $data['video_url'] = null;
        }
        unset($data['video_type'], $data['video_file']);

        $category = ! empty($data['primary_category_id'])
            ? EditorialCategory::query()->find($data['primary_category_id'])
            : null;
        $data['category'] = $category?->name;

        $user = auth()->user();
        if ($assignCurrentUser && $user) {
            $data['author_user_id'] = $data['author_user_id'] ?? $user->id;
            $data['author'] = $data['author'] ?? $user->name;
        } elseif (! empty($data['author_user_id']) && empty($data['author'])) {
            $data['author'] = User::query()->find($data['author_user_id'])?->name;
        }

        $data['pros'] = $this->flattenRepeaterValues($data['pros'] ?? []);
        $data['cons'] = $this->flattenRepeaterValues($data['cons'] ?? []);

        $postType = $data['post_type'] ?? 'news';
        $movieId = $this->normalizeNullableForeignKey($data, 'movie_id');
        $tvShowId = $this->normalizeNullableForeignKey($data, 'tv_show_id');
        $vjId = $this->normalizeNullableForeignKey($data, 'vj_id');

        if (in_array($postType, ['review', 'movie_spotlight', 'feature'], true)) {
            if ($movieId) {
                $data['review_target_type'] = 'movie';
                $data['review_target_id'] = $movieId;
                if ($postType === 'review') {
                    $data['tv_show_id'] = null;
                }
            } elseif ($tvShowId) {
                $data['review_target_type'] = 'tv_show';
                $data['review_target_id'] = $tvShowId;
                if ($postType === 'review') {
                    $data['movie_id'] = null;
                }
            } else {
                $data['review_target_type'] = null;
                $data['review_target_id'] = null;
            }
        } elseif ($postType === 'vj_profile') {
            $data['review_target_type'] = $vjId ? 'vj' : null;
            $data['review_target_id'] = $vjId;
            $data['movie_id'] = null;
            $data['tv_show_id'] = null;
        } else {
            $data['review_target_type'] = null;
            $data['review_target_id'] = null;
        }

        if (isset($data['blocks']) && is_array($data['blocks'])) {
            foreach ($data['blocks'] as $index => &$block) {
                $type = $block['type'] ?? 'rich_text';

                if ($type === 'text') {
                    $block['type'] = 'rich_text';
                    $type = 'rich_text';
                }

                $block['value'] = $this->valueForArticleBlockType($block, $type);

                if ($type === 'gallery') {
                    $block['gallery_images'] = collect(
                        preg_split('/\R+/', (string) ($block['gallery_input'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
                    )
                        ->map(fn ($line) => trim((string) $line))
                        ->filter()
                        ->values()
                        ->all();
                } else {
                    $block['gallery_images'] = null;
                }

                $this->clearUnusedArticleBlockFields($block, $type);

                $block['order'] = $index;

                unset(
                    $block['image_file'],
                    $block['gallery_input'],
                    $block['rich_text_value'],
                    $block['quote_value'],
                    $block['image_url_value'],
                    $block['cta_value'],
                );
            }
        }

        return $data;
    }

    protected function prepareArticleFillData(array $data): array
    {
        $data['pros'] = $this->expandRepeaterValues($data['pros'] ?? []);
        $data['cons'] = $this->expandRepeaterValues($data['cons'] ?? []);

        if (! empty($data['image']) && $this->isExternalUrl($data['image'])) {
            $data['image_url'] = $data['image'];
            $data['image'] = null;
        }

        $data['video_type'] = $this->detectVideoType($data['video_url'] ?? null);

        if (isset($data['blocks']) && is_array($data['blocks'])) {
            foreach ($data['blocks'] as &$block) {
                $type = $block['type'] ?? 'rich_text';

                if ($type === 'text') {
                    $block['type'] = 'rich_text';
                    $type = 'rich_text';
                }

                $block['gallery_input'] = isset($block['gallery_images']) && is_array($block['gallery_images'])
                    ? implode("\n", array_filter($block['gallery_images']))
                    : '';

                $value = (string) ($block['value'] ?? '');
                if ($type === 'rich_text') {
                    $block['rich_text_value'] = $value;
                } elseif ($type === 'quote') {
                    $block['quote_value'] = $value;
                } elseif ($type === 'image') {
                    $block['image_url_value'] = $value;
                } elseif ($type === 'cta') {
                    $block['cta_value'] = $value;
                }
            }
        }

        return $data;
    }

    private function flattenRepeaterValues(array $items): array
    {
        return collect($items)
            ->map(function ($item) {
                if (is_array($item)) {
                    return trim((string) ($item['value'] ?? ''));
                }

                return trim((string) $item);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function expandRepeaterValues(array $items): array
    {
        return collect($items)
            ->map(function ($item) {
                $value = is_array($item) ? ($item['value'] ?? '') : $item;

                return ['value' => $value];
            })
            ->values()
            ->all();
    }

    private function normalizeNullableForeignKey(array &$data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (! is_numeric($value) || (int) $value < 1) {
            $data[$key] = null;

            return null;
        }

        $data[$key] = (int) $value;

        return $data[$key];
    }

    private function valueForArticleBlockType(array $block, string $type): ?string
    {
        $value = match ($type) {
            'rich_text', 'text' => $block['rich_text_value'] ?? $block['value'] ?? null,
            'quote' => $block['quote_value'] ?? $block['value'] ?? null,
            'image' => $block['image_file'] ?? $block['image_url_value'] ?? $block['value'] ?? null,
            'cta' => $block['cta_value'] ?? $block['value'] ?? null,
            default => $block['value'] ?? null,
        };

        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        return filled($value) ? (string) $value : null;
    }

    private function clearUnusedArticleBlockFields(array &$block, string $type): void
    {
        if ($type !== 'quote') {
            $block['author'] = null;
            $block['author_title'] = null;
        }

        if ($type !== 'image') {
            $block['caption'] = null;
            $block['alt_text'] = null;
        }

        if ($type !== 'movie_embed') {
            $block['movie_id'] = null;
        }

        if ($type !== 'tv_show_embed') {
            $block['tv_show_id'] = null;
        }

        if ($type !== 'vj_embed') {
            $block['vj_id'] = null;
        }

        if ($type !== 'cta') {
            $block['cta_label'] = null;
            $block['cta_url'] = null;
        }
    }

    private function detectVideoType(?string $videoUrl): string
    {
        if (blank($videoUrl)) {
            return 'none';
        }

        $normalized = strtolower($videoUrl);

        return match (true) {
            str_contains($normalized, 'youtube.com'), str_contains($normalized, 'youtu.be') => 'youtube',
            str_contains($normalized, 'vimeo.com') => 'vimeo',
            ! $this->isExternalUrl($videoUrl) => 'upload',
            default => 'url',
        };
    }

    private function isExternalUrl(?string $value): bool
    {
        return filled($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
