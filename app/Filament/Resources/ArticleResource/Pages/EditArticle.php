<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle image: use URL if provided, otherwise use uploaded file
        if (isset($data['image_url']) && !empty($data['image_url'])) {
            $data['image'] = $data['image_url'];
            unset($data['image_url']);
        } elseif (empty($data['image'])) {
            $data['image'] = null;
        }

        // Handle video based on type
        if (isset($data['video_type'])) {
            if ($data['video_type'] === 'upload' && isset($data['video_file'])) {
                $data['video_url'] = $data['video_file']; // Store file path
            } elseif (in_array($data['video_type'], ['youtube', 'vimeo', 'url']) && isset($data['video_url'])) {
                // Keep video_url as is, but format YouTube/Vimeo URLs
                if ($data['video_type'] === 'youtube') {
                    // Extract YouTube ID if full URL provided
                    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $data['video_url'], $matches)) {
                        $data['video_url'] = 'https://www.youtube.com/watch?v=' . $matches[1];
                    } elseif (!str_contains($data['video_url'], 'youtube.com') && !str_contains($data['video_url'], 'youtu.be')) {
                        $data['video_url'] = 'https://www.youtube.com/watch?v=' . $data['video_url'];
                    }
                } elseif ($data['video_type'] === 'vimeo') {
                    // Extract Vimeo ID if full URL provided
                    if (preg_match('/vimeo\.com\/(\d+)/', $data['video_url'], $matches)) {
                        $data['video_url'] = 'https://vimeo.com/' . $matches[1];
                    } elseif (!str_contains($data['video_url'], 'vimeo.com')) {
                        $data['video_url'] = 'https://vimeo.com/' . $data['video_url'];
                    }
                }
            } else {
                $data['video_url'] = null;
            }
            unset($data['video_type'], $data['video_file']);
        }

        // Handle content blocks
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            foreach ($data['blocks'] as &$block) {
                // Handle image block: use URL if provided, otherwise use uploaded file
                if (isset($block['image_file']) && !empty($block['image_file'])) {
                    $block['value'] = $block['image_file'];
                    unset($block['image_file']);
                } elseif (isset($block['value']) && !empty($block['value']) && $block['type'] === 'image') {
                    // Keep URL value
                }

                // Handle gallery images
                if (isset($block['gallery_images']) && is_array($block['gallery_images'])) {
                    $galleryUrls = [];
                    foreach ($block['gallery_images'] as $galleryItem) {
                        if (isset($galleryItem['image_file']) && !empty($galleryItem['image_file'])) {
                            $galleryUrls[] = $galleryItem['image_file'];
                        } elseif (isset($galleryItem['image_url']) && !empty($galleryItem['image_url'])) {
                            $galleryUrls[] = $galleryItem['image_url'];
                        }
                    }
                    $block['gallery_images'] = $galleryUrls;
                }
            }
        }

        return $data;
    }
}
