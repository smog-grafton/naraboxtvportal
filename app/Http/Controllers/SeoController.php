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
        return config('app.url', 'https://naraboxtv.com');
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
            return $this->getBaseUrl() . $path;
        }

        // If it starts with /, it's a public asset
        if (str_starts_with($path, '/')) {
            return $this->getBaseUrl() . $path;
        }

        // Otherwise, assume it's a storage file
        return $this->getBaseUrl() . '/storage/' . $path;
    }

    /**
     * Clean and truncate description
     */
    private function cleanDescription(?string $description, int $maxLength = 160): string
    {
        if (empty($description)) {
            return 'Watch movies and TV shows on NaraBox TV. Stream or download English films and Ugandan VJ-translated movies.';
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

        // Build meta title
        $verb = $movie->download_enabled ? 'Watch and download' : 'Watch';
        $title = "{$verb} {$movie->title}";

        $hasVj = $movie->vj_id && $movie->vj;
        if ($hasVj) {
            $title .= " - By {$movie->vj->name}";
            if ($movie->is_free) {
                $title .= " - For free";
            }
        } elseif ($movie->is_free) {
            $title .= " - For free";
        }

        $title .= " on NaraBox TV";
        
        // Ensure title is ≤ 60 characters
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }

        // Meta description
        $description = $this->cleanDescription($movie->description, 160);

        // OG Image
        $ogImage = $this->getImageUrl($movie->backdrop);

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

        // Build meta title
        $verb = $tvShow->download_enabled ? 'Watch and download' : 'Watch';
        $title = "{$verb} {$tvShow->title} TV Series";

        $hasVj = $tvShow->vj_id && $tvShow->vj;
        if ($hasVj) {
            $title .= " - By {$tvShow->vj->name}";
            if ($tvShow->is_free) {
                $title .= " - For free";
            }
        } elseif ($tvShow->is_free) {
            $title .= " - For free";
        }

        $title .= " on NaraBox TV";

        // Ensure title is ≤ 60 characters
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }

        // Meta description
        $description = $this->cleanDescription($tvShow->description, 160);

        // OG Image
        $ogImage = $this->getImageUrl($tvShow->backdrop);

        // Canonical URL
        $canonical = $this->getBaseUrl() . "/tv/{$slug}";

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

        // Build meta title
        $title = "{$vj->name} – Luganda Movie Translations on NaraBox TV";
        
        // Ensure title is ≤ 60 characters
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }

        // Meta description
        $description = $this->cleanDescription($vj->bio, 160);
        if (empty($description)) {
            $description = "Watch {$vj->name}'s Luganda translated movies and TV shows on NaraBox TV. Stream or download VJ-translated content.";
        }

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
        $vjCount = VJ::where('is_active', true)->count();
        $title = "VJ Masters – Luganda Movie Translators | NaraBox TV";
        $description = "Meet the Video Jockey (VJ) masters who bring global cinema to local hearts. Discover VJ Junior, VJ Emmy, VJ Jingo, and other expert Luganda translators who make movies accessible to Ugandan audiences.";
        $canonical = $this->getBaseUrl() . "/vjs";
        $ogImage = $this->getBaseUrl() . "/assets/images/meta/metaog.jpeg";

        return view('seo.page', [
            'title' => $title,
            'description' => $description,
            'ogType' => 'website',
            'ogImage' => $ogImage,
            'ogImageAlt' => 'NaraBox TV VJ Masters',
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

        // Use the article's slug for canonical URL (not the input parameter)
        $articleSlug = $article->slug;

        // Build meta title
        $title = "{$article->title} – NaraBox TV News";
        
        // Ensure title is ≤ 60 characters
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }

        // Meta description - use excerpt if available
        $description = $this->cleanDescription($article->excerpt ?: $article->title, 160);

        // OG Image
        $ogImage = $this->getImageUrl($article->image);

        // Canonical URL - always use slug
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
        $title = "News & Updates – Latest Articles & Industry News | NaraBox TV";
        $description = "Stay updated with the latest news, updates, and industry insights from NaraBox TV. Read about platform updates, movie releases, TV show announcements, and VJ-translated content news.";
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
        $movieCount = Movie::where('is_active', true)->count();
        $title = "Movies – Watch & Download Latest Films | NaraBox TV";
        $description = "Browse and watch the latest movies on NaraBox TV. Stream or download English films, action movies, comedies, dramas, and popular Ugandan VJ-translated movies. Over {$movieCount}+ movies available.";
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
        $tvShowCount = TVShow::where('is_active', true)->count();
        $title = "TV Shows – Watch & Stream Series | NaraBox TV";
        $description = "Browse and watch TV shows and series on NaraBox TV. Stream or download English TV series, dramas, comedies, and popular Ugandan VJ-translated shows. Multiple seasons and episodes available.";
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

