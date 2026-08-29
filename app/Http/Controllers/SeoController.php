<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VJ;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    /**
     * Get base URL for images and canonical URLs
     */
    private function getBaseUrl(): string
    {
        return rtrim((string) config('app.frontend_url', 'https://naraboxtv.com'), '/');
    }

    /**
     * Get image URL (handles both storage and external URLs)
     */
    private function getImageUrl(?string $path, string $fallback = '/assets/images/backdrop/backdrop.jpg'): string
    {
        if (empty($path)) {
            return $this->getBaseUrl() . $fallback;
        }

        // If it's already a full URL, return as is
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // If it starts with /storage, it's a storage file
        if (str_starts_with($path, '/storage/')) {
            return rtrim((string) config('app.url'), '/') . $path;
        }

        // If it starts with /, it's a public asset
        if (str_starts_with($path, '/')) {
            return $this->getBaseUrl() . $path;
        }

        // Otherwise, assume it's a storage file
        return rtrim((string) config('app.url'), '/') . '/storage/' . ltrim($path, '/');
    }

    /**
     * Clean and truncate description
     */
    private function cleanDescription(?string $description, int $maxLength = 160): string
    {
        if (empty($description)) {
            return 'Watch and download VJ-translated movies and TV shows on NaraBox TV, including Luganda titles and archives from Ugandan VJs.';
        }

        // Strip HTML tags
        $cleaned = strip_tags($description);
        
        // Remove extra whitespace
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);

        // Truncate to max length without cutting words
        if (strlen($cleaned) > $maxLength) {
            $cleaned = substr($cleaned, 0, $maxLength);
            $lastSpace = strrpos($cleaned, ' ');
            if ($lastSpace !== false) {
                $cleaned = substr($cleaned, 0, $lastSpace);
            }
            $cleaned .= '...';
        }

        return $cleaned;
    }

    private function articleTitle(Article $article): string
    {
        $title = $article->seo_title ?: match ($article->post_type) {
            'review' => "{$article->title} Review | NaraBox TV",
            'movie_spotlight' => "{$article->title} | Movie Spotlight | NaraBox TV",
            'vj_profile' => "{$article->title} | VJ Profile | NaraBox TV",
            'feature' => "{$article->title} | Feature | NaraBox TV",
            default => "{$article->title} | NaraBox TV News",
        };

        return Str::limit($title, 60, '...');
    }

    private function articleDescription(Article $article): string
    {
        return $this->cleanDescription($article->seo_description ?: $article->excerpt ?: $article->title, 160);
    }

    /**
     * Movie SEO page
     */
    public function movie(string $slug)
    {
        $movie = Movie::where('slug', $slug)
            ->where('is_active', true)
            ->with('vj')
            ->first();

        if (!$movie) {
            abort(404);
        }

        $hasVj = $movie->vj_id && $movie->vj;
        $title = $hasVj
            ? "{$movie->title} – {$movie->vj->name} Translated Movie | NaraBox TV"
            : "{$movie->title} Movie | NaraBox TV";
        $title = Str::limit($title, 60, '...');
        $lead = $hasVj
            ? "Watch {$movie->title}, translated by {$movie->vj->name}, on NaraBox TV."
            : "Watch {$movie->title} on NaraBox TV.";
        $description = $this->cleanDescription($lead.' '.$movie->description, 160);

        // OG Image
        $ogImage = $this->getImageUrl($movie->backdrop ?: $movie->thumbnail);

        // Canonical URL - use /movies/ to match Next.js route structure
        $canonical = $this->getBaseUrl() . "/movies/{$slug}";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'video.movie',
            'ogImage' => $ogImage,
            'ogImageAlt' => $movie->title,
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * TV Show SEO page
     */
    public function tv(string $slug)
    {
        $tvShow = TVShow::where('slug', $slug)
            ->where('is_active', true)
            ->with('vj')
            ->first();

        if (!$tvShow) {
            abort(404);
        }

        $hasVj = $tvShow->vj_id && $tvShow->vj;
        $title = $hasVj
            ? "{$tvShow->title} – {$tvShow->vj->name} Translated Series | NaraBox TV"
            : "{$tvShow->title} TV Series | NaraBox TV";
        $title = Str::limit($title, 60, '...');
        $lead = $hasVj
            ? "Watch {$tvShow->title}, a VJ-translated series by {$tvShow->vj->name}, on NaraBox TV."
            : "Watch {$tvShow->title} on NaraBox TV.";
        $description = $this->cleanDescription($lead.' '.$tvShow->description, 160);

        // OG Image
        $ogImage = $this->getImageUrl($tvShow->backdrop ?: $tvShow->thumbnail);

        // Canonical URL
        $canonical = $this->getBaseUrl() . "/tv-shows/{$slug}";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'video.tv_show',
            'ogImage' => $ogImage,
            'ogImageAlt' => $tvShow->title,
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * VJ SEO page
     */
    public function vj(string $slug)
    {
        // Support both slug and ID (backward compatibility)
        $vj = VJ::where('is_active', true)
            ->where(function ($query) use ($slug) {
                $query->where('slug', $slug)
                      ->orWhere('id', $slug);
            })
            ->first();

        if (!$vj) {
            abort(404);
        }

        // Use the VJ's slug for canonical URL (not the input parameter)
        $vjSlug = $vj->slug;

        $title = Str::limit("{$vj->name} Translated Movies & TV Shows | NaraBox TV", 60, '...');
        $description = $this->cleanDescription(
            "Browse {$vj->name}'s VJ-translated movies and TV shows on NaraBox TV. ".($vj->bio ?? ''),
            160
        );

        // OG Image - use banner if available, otherwise image
        $ogImage = $this->getImageUrl($vj->banner ?: $vj->image);

        // Canonical URL - always use slug
        $canonical = $this->getBaseUrl() . "/vjs/{$vjSlug}";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'profile',
            'ogImage' => $ogImage,
            'ogImageAlt' => $vj->name,
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * VJ listing page SEO
     */
    public function vjs()
    {
        $names = VJ::where('is_active', true)->orderByDesc('is_featured')->limit(4)->pluck('name')->implode(', ');
        $archiveNames = $names !== '' ? $names : 'Ugandan VJs';
        $title = "Ugandan VJs & Translated Movie Archives | NaraBox TV";
        $description = $this->cleanDescription("Browse VJ-translated movie and TV-show archives from {$archiveNames} on NaraBox TV, with latest, trending, and top-rated titles.");
        $canonical = $this->getBaseUrl() . "/vjs";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV VJ translated-movie archives',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * News/Article SEO page
     */
    public function news(string $slug)
    {
        // Support both slug and ID (backward compatibility)
        $article = Article::where('is_published', true)
            ->where(function ($query) use ($slug) {
                $query->where('slug', $slug)
                      ->orWhere('id', $slug);
            })
            ->first();

        if (!$article) {
            abort(404);
        }

        $articleSlug = $article->slug ?: $article->id;
        $title = $this->articleTitle($article);
        $description = $this->articleDescription($article);
        $ogImage = $this->getImageUrl($article->og_image ?: $article->image);
        $canonical = $this->getBaseUrl() . "/news/{$articleSlug}";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'article',
            'ogImage' => $ogImage,
            'ogImageAlt' => $article->title,
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * News listing page SEO
     */
    public function newsListing()
    {
        $articleCount = Article::where('is_published', true)->count();
        $title = "Movie News, Reviews & Editorial Features | NaraBox TV";
        $description = "Explore {$articleCount}+ NaraBox editorial stories covering movie news, factual reviews, VJ profiles, promotional spotlights, and entertainment features.";
        $canonical = $this->getBaseUrl() . "/news";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV News',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * Contact page SEO
     */
    public function contact()
    {
        $title = "Contact Us – NaraBox TV Support & Inquiries";
        $description = "Get in touch with NaraBox TV. Contact us for technical support, billing inquiries, VJ cooperation, or general questions. Email: info@naraboxtv.com, Phone: +256 702 093354";
        $canonical = $this->getBaseUrl() . "/contact";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV Contact',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * About page SEO
     */
    public function about()
    {
        $title = "About NaraBox TV – SMOG CODERS | Mulinda Akiibu";
        $description = "NaraBox TV is owned by SMOG CODERS, developed and designed by software engineer Mulinda Akiibu. Part of Nara Group of Companies, operating since 2021-08-16.";
        $canonical = $this->getBaseUrl() . "/about";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV About',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * Help Center page SEO
     */
    public function helpCenter()
    {
        $title = "Help Center – NaraBox TV Technical Support";
        $description = "Get help with NaraBox TV. Find answers to frequently asked questions about billing, streaming, account management, and technical support.";
        $canonical = $this->getBaseUrl() . "/help-center";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV Help Center',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * Device Support page SEO
     */
    public function deviceSupport()
    {
        $title = "Device Support – All Devices Supported | NaraBox TV";
        $description = "NaraBox TV supports all devices: iOS, Android, Smart TVs, PC, Gaming Consoles, Tablets, and streaming devices. Check compatibility and firmware requirements.";
        $canonical = $this->getBaseUrl() . "/device-support";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV Device Support',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * Privacy Policy page SEO
     */
    public function privacyPolicy()
    {
        $title = "Privacy Policy – NaraBox TV Data Protection";
        $description = "NaraBox TV privacy protocol. Learn how we collect, store, and protect your data. Identity encryption rules and operator telemetry policies.";
        $canonical = $this->getBaseUrl() . "/privacy-policy";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV Privacy Policy',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * Terms of Service page SEO
     */
    public function terms()
    {
        $title = "Terms of Service – NaraBox TV Mission Terms";
        $description = "NaraBox TV terms of mission. Service authorization, identity integrity, and mission duration policies. Review conduct and compliance rules.";
        $canonical = $this->getBaseUrl() . "/terms";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV Terms of Service',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * Live Streams page SEO
     */
    public function live()
    {
        $title = "Live Streams – Watch Live & Archived Streams | NaraBox TV";
        $description = "Watch live streams and archived broadcasts on NaraBox TV. Enjoy VJ-translated live content, exclusive shows, and real-time commentary.";
        $canonical = $this->getBaseUrl() . "/live";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV Live Streams',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * Movies listing page SEO
     */
    public function movies()
    {
        $title = "VJ Translated Movies & Luganda Films | NaraBox TV";
        $description = "Watch and download VJ-translated movies on NaraBox TV. Browse Luganda translated movies, latest releases, trending titles, and archives by Ugandan VJs.";
        $canonical = $this->getBaseUrl() . "/movies";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV Movies',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }

    /**
     * TV Shows listing page SEO
     */
    public function tvShows()
    {
        $title = "VJ Translated TV Shows & Series | NaraBox TV";
        $description = "Watch and download VJ-translated TV shows and series on NaraBox TV. Explore Luganda translated series, trending shows, seasons, and VJ archives.";
        $canonical = $this->getBaseUrl() . "/tv-shows";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV TV Shows',
            'canonical' => $canonical,
            'twitterCard' => 'summary_large_image',
        ]);
    }
}
