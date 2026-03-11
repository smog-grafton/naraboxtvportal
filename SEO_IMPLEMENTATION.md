# SEO Gateway Implementation - Laravel + Next.js

## Overview

This implementation provides SEO-friendly dynamic meta rendering using Laravel, while keeping Next.js as the UI layer. Laravel serves SEO-optimized HTML with meta tags for crawlers and social media, then redirects real users to the Next.js frontend.

## Architecture

### How It Works

1. **Crawlers/Bots** (Google, Facebook, Twitter, etc.):
   - Visit Laravel routes (e.g., `/movie/supernatural`)
   - Laravel queries database by slug
   - Returns HTML with complete SEO meta tags
   - Bot reads meta tags and indexes the page
   - Bot does NOT execute JavaScript redirect

2. **Real Users**:
   - Visit Laravel routes
   - Laravel serves HTML with meta tags
   - JavaScript detects it's not a bot
   - Redirects to Next.js frontend (`http://localhost:3000/movie/supernatural`)
   - Next.js handles all client-side routing and UI

## Routes Created

### Laravel Routes (web.php)

- `/movie/{slug}` - Movie SEO page
- `/tv/{slug}` - TV Show SEO page  
- `/vj/{slug}` - VJ Profile SEO page
- `/news/{slug}` - News Article SEO page

## Database Validation

✅ **All required columns exist:**

### Movies Table
- ✅ `title` - Used for meta title
- ✅ `slug` - Used for URL routing
- ✅ `description` - Used for meta description
- ✅ `backdrop` - Used for OG image
- ✅ `vj_id` - Used to append "VJ Name Luganda Version" to title
- ✅ `is_free` - Used to append "– Free" to title

### TV Shows Table
- ✅ `title` - Used for meta title
- ✅ `slug` - Used for URL routing
- ✅ `description` - Used for meta description
- ✅ `backdrop` - Used for OG image

### VJs Table
- ✅ `name` - Used for meta title
- ✅ `slug` - Used for URL routing
- ✅ `bio` - Used for meta description
- ✅ `banner` - Used for OG image (falls back to `image`)
- ✅ `image` - Fallback for OG image

### Articles Table
- ✅ `title` - Used for meta title
- ✅ `slug` - Used for URL routing
- ✅ `excerpt` - Used for meta description (falls back to `title`)
- ✅ `image` - Used for OG image

## SEO Rules Implemented

### Movie SEO

**Meta Title Format:**
```
Watch {movies.title}{VJ suffix} on NaraBox TV
```

**Rules:**
- If `vj_id` is NOT NULL: Append `– {vj.name} Luganda Version`
- If `is_free` is true: Append `– Free`
- Maximum 60 characters (truncated if longer)

**Example:**
- "Watch Zootopia 2 – VJ Junior Luganda Version on NaraBox TV"

**Meta Description:**
- Source: `movies.description`
- Trimmed to 150-160 characters
- HTML stripped
- No mid-word truncation

**OG Image:**
- Primary: `movies.backdrop`
- Fallback: `/assets/images/backdrop/backdrop.jpg`

### TV Show SEO

**Meta Title Format:**
```
Watch {title} TV Series on NaraBox TV
```

**Meta Description:**
- Source: `tv_shows.description`
- Trimmed to 150-160 characters

**OG Image:**
- Primary: `tv_shows.backdrop`
- Fallback: `/assets/images/backdrop/backdrop.jpg`

### VJ SEO

**Meta Title Format:**
```
{VJ Name} – Luganda Movie Translations on NaraBox TV
```

**Meta Description:**
- Source: `vjs.bio`
- If empty: Default description about VJ's translations

**OG Image:**
- Primary: `vjs.banner`
- Fallback: `vjs.image`

### News SEO

**Meta Title Format:**
```
{News Title} – NaraBox TV News
```

**Meta Description:**
- Source: `articles.excerpt` (falls back to `title`)
- Trimmed to 150-160 characters

**OG Image:**
- Source: `articles.image`

## Meta Tags Generated

All routes generate the following meta tags:

### Primary Meta Tags
- `<title>` - Page title
- `<meta name="description">` - Page description
- `<meta name="robots">` - index, follow
- `<meta name="author">` - NaraBox TV
- `<meta name="copyright">` - NaraBox TV
- `<link rel="canonical">` - Canonical URL

### Open Graph Tags
- `og:type` - video.movie, video.tv_show, profile, or article
- `og:url` - Canonical URL
- `og:title` - SEO title
- `og:description` - SEO description
- `og:image` - Image URL
- `og:image:alt` - Image alt text
- `og:site_name` - NaraBox TV
- `og:locale` - en_US

### Twitter Card Tags
- `twitter:card` - summary_large_image
- `twitter:url` - Canonical URL
- `twitter:title` - SEO title
- `twitter:description` - SEO description
- `twitter:image` - Image URL
- `twitter:creator` - @naraboxtv
- `twitter:site` - @naraboxtv

## Configuration

### Environment Variables

Make sure your `.env` file has:

```env
APP_URL=http://127.0.0.1:8000
```

For production:
```env
APP_URL=https://www.narabox.tv
```

### Next.js Configuration

Ensure Next.js is running on `http://localhost:3000` (or update the redirect URL in the Blade template).

## Testing

### Test SEO Routes

1. **Movie:**
   ```
   http://127.0.0.1:8000/movie/{slug}
   ```

2. **TV Show:**
   ```
   http://127.0.0.1:8000/tv/{slug}
   ```

3. **VJ:**
   ```
   http://127.0.0.1:8000/vj/{slug}
   ```

4. **News:**
   ```
   http://127.0.0.1:8000/news/{slug}
   ```

### Test with Social Media Debuggers

- **Facebook:** https://developers.facebook.com/tools/debug/
- **Twitter:** https://cards-dev.twitter.com/validator
- **LinkedIn:** https://www.linkedin.com/post-inspector/

### Verify Meta Tags

View page source or use browser dev tools to verify:
- Title tag is correct
- Description is present and properly truncated
- OG tags are complete
- Twitter card tags are present
- Canonical URL is correct
- Image URLs are accessible

## How Next.js Loads

1. Laravel serves HTML with SEO meta tags
2. JavaScript detects if user is a bot
3. If NOT a bot: Redirects to `http://localhost:3000{path}`
4. Next.js handles the route client-side
5. User gets full Next.js experience

## Important Notes

1. **Bot Detection:** The implementation uses user-agent detection to identify crawlers. Bots stay on Laravel page to read meta tags; real users are redirected to Next.js.

2. **Image URLs:** The `getImageUrl()` method handles:
   - Full URLs (returns as-is)
   - Storage paths (`/storage/...`)
   - Public assets (`/assets/...`)
   - Relative paths (assumes storage)

3. **Description Truncation:** Descriptions are cleaned (HTML stripped) and truncated at word boundaries to avoid mid-word cuts.

4. **404 Handling:** If slug is not found, Laravel returns 404.

5. **Active/Published Check:** Only active movies/TV shows and published articles are accessible.

## Files Created/Modified

### Created Files
- `app/Http/Controllers/SeoController.php` - SEO controller
- `resources/views/seo/page.blade.php` - SEO Blade template

### Modified Files
- `routes/web.php` - Added SEO routes

## Next Steps

1. **Test all routes** with real slugs from database
2. **Verify meta tags** using social media debuggers
3. **Update Next.js routes** if needed to match Laravel SEO routes
4. **Configure production URLs** in `.env`
5. **Set up reverse proxy** (if needed) to route requests appropriately

## Production Considerations

For production deployment:

1. Update `APP_URL` in `.env` to production domain
2. Update Next.js redirect URL in Blade template to production Next.js URL
3. Ensure image URLs are accessible (CDN if needed)
4. Set up proper caching headers for SEO pages
5. Consider using a reverse proxy (Nginx) to route:
   - Bot requests → Laravel SEO routes
   - User requests → Next.js frontend

