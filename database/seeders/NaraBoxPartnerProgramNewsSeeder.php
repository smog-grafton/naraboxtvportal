<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleBlock;
use App\Models\ArticleTag;
use Illuminate\Database\Seeder;

class NaraBoxPartnerProgramNewsSeeder extends Seeder
{
    public function run(): void
    {
        $slug = 'introducing-narabox-partner-program';
        $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');
        if ($frontendUrl === '' || preg_match('/^(https?:\/\/)?(localhost|127\.0\.0\.1)(:\d+)?$/i', $frontendUrl)) {
            $frontendUrl = 'https://naraboxtv.com';
        }

        $article = Article::firstOrCreate(
            ['slug' => $slug],
            [
                'title' => 'Introducing the NaraBox Partner Program',
                'excerpt' => 'Turn your influence into earnings by introducing more viewers to NaraBox TV.',
                'author' => 'NaraBox Editorial Team',
                'category' => 'Updates',
                'date' => '2026-08-14',
                'image' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?w=1920&h=1080&fit=crop',
                'is_top_news' => true,
                'is_published' => true,
            ]
        );

        ArticleTag::firstOrCreate(['article_id' => $article->id, 'tag' => 'Partner Program']);
        ArticleTag::firstOrCreate(['article_id' => $article->id, 'tag' => 'NaraBox']);

        $blocks = [
            [
                'order' => 0,
                'type' => 'text',
                'value' => 'NaraBox is opening its Partner Program to influencers, promoters, creators, personalities and community leaders who want to grow with the platform. Partners can introduce new viewers to NaraBox TV and follow qualified activity from a dedicated workspace.',
            ],
            [
                'order' => 1,
                'type' => 'text',
                'value' => 'Partners receive referral tools, performance analytics, earnings tracking, payout support and their own Partner Portal. Eligible transactions can generate commission, subject to account approval and the programme terms shown in the partner workspace.',
            ],
            [
                'order' => 2,
                'type' => 'cta',
                'value' => 'Ready to grow with NaraBox? Create an account or sign in to apply using your existing NaraBox identity.',
                'cta_label' => 'Become a NaraBox Partner',
                'cta_url' => $frontendUrl.'/partner-program',
            ],
        ];

        foreach ($blocks as $block) {
            ArticleBlock::updateOrCreate(
                ['article_id' => $article->id, 'order' => $block['order']],
                array_merge($block, ['article_id' => $article->id])
            );
        }
    }
}
