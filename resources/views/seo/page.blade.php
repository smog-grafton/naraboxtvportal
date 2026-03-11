<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    {{-- Primary Meta Tags --}}
    <title>{{ $title }}</title>
    <meta name="title" content="{{ $title }}">
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index, follow">
    <meta name="language" content="en">
    <meta name="author" content="NaraBox TV">
    <meta name="copyright" content="NaraBox TV">
    <meta name="rating" content="general">
    <meta name="distribution" content="global">
    
    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:alt" content="{{ $ogImageAlt ?? $title }}">
    <meta property="og:site_name" content="NaraBox TV">
    <meta property="og:locale" content="en_US">
    
    {{-- Twitter --}}
    <meta name="twitter:card" content="{{ $twitterCard ?? 'summary_large_image' }}">
    <meta name="twitter:url" content="{{ $canonical }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{{ $ogImageAlt ?? $title }}">
    <meta name="twitter:creator" content="@naraboxtv">
    <meta name="twitter:site" content="@naraboxtv">
    
    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ $canonical }}">
    
    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ config('app.url') }}/assets/images/logo/nb-white.png">
    
    {{-- Preconnect for performance --}}
    <link rel="preconnect" href="http://localhost:3000">
    <link rel="dns-prefetch" href="http://localhost:3000">
    
    {{-- Next.js will hydrate this --}}
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #0a0a0a;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        #__next {
            min-height: 100vh;
        }
        /* Loading state */
        .seo-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #0a0a0a;
        }
        .seo-loading-text {
            color: #00ff88;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
    </style>
    {{-- Ensure title is set immediately before any redirects --}}
    <script>
        // Set document title immediately to ensure it's visible
        document.title = {{ json_encode($title) }};
    </script>
</head>
<body>
    {{-- Next.js will mount here --}}
    <div id="__next">
        <div class="seo-loading">
            <div class="seo-loading-text">Loading NaraBox TV...</div>
        </div>
    </div>
    
    {{-- Load Next.js bundle --}}
    <script>
        (function() {
            // Ensure title is set (in case script above didn't execute)
            document.title = {{ json_encode($title) }};
            
            // Detect if this is a bot/crawler
            const userAgent = navigator.userAgent.toLowerCase();
            const isBot = /bot|crawler|spider|crawling|facebookexternalhit|twitterbot|linkedinbot|whatsapp|slurp|duckduckbot|baiduspider|yandexbot|sogou|exabot|facebot|ia_archiver|googlebot|bingbot/i.test(userAgent);
            
            // For bots/crawlers: Keep them on this page to read meta tags
            // For real users: Redirect to Next.js for full experience
            if (!isBot && typeof window !== 'undefined') {
                // Get current path and preserve query params
                const currentPath = window.location.pathname;
                const queryString = window.location.search;
                
                // Normalize path: convert /movie/ to /movies/, /tv/ to /tv-shows/, /vj/ to /vjs/ for Next.js
                let nextJsPath = currentPath;
                if (nextJsPath.startsWith('/movie/')) {
                    nextJsPath = nextJsPath.replace('/movie/', '/movies/');
                }
                if (nextJsPath.startsWith('/tv/')) {
                    nextJsPath = nextJsPath.replace('/tv/', '/tv-shows/');
                }
                if (nextJsPath.startsWith('/vj/')) {
                    nextJsPath = nextJsPath.replace('/vj/', '/vjs/');
                }
                
                const nextJsUrl = 'http://localhost:3000' + nextJsPath + queryString;
                
                // Longer delay to ensure meta tags are read by browser before redirect
                setTimeout(function() {
                    window.location.href = nextJsUrl;
                }, 500);
            }
        })();
    </script>
</body>
</html>

