<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleBlock;
use App\Models\ArticleTag;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    private const TEST_MP4 = 'https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4';

    public function run(): void
    {
        $articles = [
            [
                'title' => 'NaraBox 2.0: The Future of African Streaming Architecture',
                'excerpt' => 'We are rebuilding the core relay network to support 8K VR streams for VJ translated content.',
                'author' => 'Nara Editorial Team',
                'category' => 'Updates',
                'date' => '2024-10-24',
                'image' => 'https://images.unsplash.com/photo-1485846234645-a62644f84728?w=1920&h=1080&fit=crop',
                'video_url' => self::TEST_MP4,
                'is_top_news' => true,
                'tags' => ['Architecture', '8K', 'V2.0'],
                'blocks' => [
                    ['type' => 'text', 'value' => 'Today marks a historic pivot for the NaraBox ecosystem. As we scale our narrative relay modules across the continent, the need for a more robust data pipeline has become critical.', 'order' => 0],
                    ['type' => 'quote', 'value' => "Streaming isn't just about data; it's about the emotional latency between the VJ's voice and the viewer's heart.", 'author' => 'Chief Systems Architect', 'order' => 1],
                    ['type' => 'image', 'value' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1200&h=600&fit=crop', 'caption' => 'Technical visualization of the new 8K fiber optic nodes in Kampala.', 'order' => 2],
                    ['type' => 'text', 'value' => 'The new V2.0 architecture reduces latency by 45% while doubling the audio fidelity of our Luganda voice-overs.', 'order' => 3],
                    ['type' => 'gallery', 'gallery_images' => [
                        'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=400&fit=crop',
                        'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&h=400&fit=crop',
                        'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=400&fit=crop',
                    ], 'order' => 4],
                ],
            ],
            [
                'title' => 'Inside the Booth: VJ Junior on Translating Sci-Fi',
                'excerpt' => 'The legend sits down to discuss the challenges of translating futuristic concepts into local dialect.',
                'author' => 'Mark S.',
                'category' => 'Industry',
                'date' => '2024-10-28',
                'image' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?w=1920&h=1080&fit=crop',
                'is_top_news' => true,
                'tags' => ['VJ Culture', 'Luganda', 'Masterclass'],
                'blocks' => [
                    ['type' => 'text', 'value' => 'VJ Junior has been the voice of cinema for over two decades. In our latest exclusive, he breaks down the process of creating "Cyber-Luganda" terms for movies like Cyber Enigma.', 'order' => 0],
                ],
            ],
            [
                'title' => 'Top 5 Series to Binge This Weekend',
                'excerpt' => 'From Concrete Jungle to Neon Shadows, here is your weekend archive survival guide.',
                'author' => 'Sarah J.',
                'category' => 'TV Shows',
                'date' => '2024-11-01',
                'image' => 'https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=1920&h=1080&fit=crop',
                'tags' => ['Curated', 'Binge-Watch'],
                'blocks' => [
                    ['type' => 'text', 'value' => 'The archive is expanding. Here are the top series currently trending in the Luganda Masters section.', 'order' => 0],
                ],
            ],
            [
                'title' => 'The Rise of Digital Cinemas in East Africa',
                'excerpt' => 'Industry analysis on how streaming platforms are disrupting traditional theatre models.',
                'author' => 'Economic Hub',
                'category' => 'Industry',
                'date' => '2024-11-04',
                'image' => 'https://images.unsplash.com/photo-1517604931442-7e0c8ed0963c?w=1920&h=1080&fit=crop',
                'tags' => ['Business', 'Regional'],
                'blocks' => [
                    ['type' => 'text', 'value' => 'East Africa is witnessing a digital renaissance. Mobile money integration has made premium content accessible to everyone.', 'order' => 0],
                ],
            ],
            [
                'title' => 'Platform Maintenance: Hub Synchronization',
                'excerpt' => 'Scheduled maintenance for our central identity servers coming this Sunday.',
                'author' => 'Ops Command',
                'category' => 'Platform',
                'date' => '2024-11-05',
                'image' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1920&h=1080&fit=crop',
                'tags' => ['Technical', 'Maintenance'],
                'blocks' => [
                    ['type' => 'text', 'value' => 'We will be updating our core security protocols to ensure your archive data remains encrypted.', 'order' => 0],
                ],
            ],
        ];

        foreach ($articles as $articleData) {
            $tags = $articleData['tags'] ?? [];
            $blocks = $articleData['blocks'] ?? [];
            unset($articleData['tags'], $articleData['blocks']);

            $slug = \Illuminate\Support\Str::slug($articleData['title']);
            $article = Article::firstOrCreate(['slug' => $slug], array_merge($articleData, ['slug' => $slug]));

            // Create tags (only if not exists)
            foreach ($tags as $tag) {
                ArticleTag::firstOrCreate([
                    'article_id' => $article->id,
                    'tag' => $tag,
                ]);
            }

            // Create blocks (only if not exists)
            foreach ($blocks as $blockData) {
                $galleryImages = $blockData['gallery_images'] ?? null;
                unset($blockData['gallery_images']);

                if ($galleryImages) {
                    $blockData['gallery_images'] = $galleryImages;
                }

                ArticleBlock::firstOrCreate(
                    ['article_id' => $article->id, 'order' => $blockData['order']],
                    array_merge($blockData, ['article_id' => $article->id])
                );
            }
        }
    }
}
