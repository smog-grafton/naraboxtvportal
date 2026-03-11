<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subtitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubtitleFetchController extends Controller
{
    /**
     * Fetch a subtitle file from a URL and save it to the server
     */
    public function fetch(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'subtitleable_type' => 'required|string|in:App\Models\Movie,App\Models\Episode',
            'subtitleable_id' => 'required|integer|exists:' . ($request->subtitleable_type === 'App\Models\Movie' ? 'movies' : 'episodes') . ',id',
            'language' => 'nullable|string|max:10',
            'label' => 'nullable|string|max:255',
        ]);

        $url = $request->url;
        $subtitleableType = $request->subtitleable_type;
        $subtitleableId = $request->subtitleable_id;
        $language = $request->language ?? 'en';
        $label = $request->label ?? 'English';

        try {
            // Determine file extension from URL or Content-Type
            $extension = $this->getFileExtension($url);
            $fileName = 'fetched_' . Str::random(16) . '.' . $extension;
            $directory = 'subtitles/fetched';
            $filePath = $directory . '/' . $fileName;

            // Create directory if it doesn't exist
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory, 0755, true);
            }

            $fullPath = Storage::disk('public')->path($filePath);

            // Fetch the file using cURL
            $ch = curl_init($url);
            $fp = fopen($fullPath, 'wb');
            
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $success = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            fclose($fp);

            if (!$success || $httpCode !== 200 || !empty($error)) {
                @unlink($fullPath); // Clean up on failure
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch subtitle: ' . ($error ?: "HTTP $httpCode"),
                ], 400);
            }

            // Verify file was downloaded
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Downloaded file is empty or missing',
                ], 400);
            }

            // Get actual file size
            $actualFileSize = filesize($fullPath);

            // Check if a subtitle with this URL already exists for this movie/episode
            $existingSubtitle = Subtitle::where('subtitleable_type', $subtitleableType)
                ->where('subtitleable_id', $subtitleableId)
                ->where('url', $url)
                ->where('type', 'fetched')
                ->first();
            
            if ($existingSubtitle) {
                // Update existing record
                $existingSubtitle->update([
                    'file_path' => $filePath,
                    'language' => $language,
                    'label' => $label,
                    'format' => $extension,
                    'is_active' => true,
                ]);
                $subtitle = $existingSubtitle;
            } else {
                // Create new subtitle record
                $subtitle = Subtitle::create([
                    'subtitleable_type' => $subtitleableType,
                    'subtitleable_id' => $subtitleableId,
                    'type' => 'fetched',
                    'url' => $url, // Store original URL for reference
                    'file_path' => $filePath,
                    'language' => $language,
                    'label' => $label,
                    'format' => $extension,
                    'is_default' => false,
                    'is_active' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subtitle fetched and saved successfully',
                'subtitle' => [
                    'id' => $subtitle->id,
                    'file_path' => $subtitle->file_path,
                    'language' => $subtitle->language,
                    'label' => $subtitle->label,
                    'format' => $subtitle->format,
                    'type' => $subtitle->type,
                ],
                'file_size' => $actualFileSize,
                'file_path' => Storage::disk('public')->url($filePath),
            ]);

        } catch (\Exception $e) {
            Log::error('Subtitle fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching subtitle: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get file extension from URL or Content-Type header
     */
    private function getFileExtension($url)
    {
        // Try to get extension from URL
        $path = parse_url($url, PHP_URL_PATH);
        if ($path && preg_match('/\.([a-z0-9]+)$/i', $path, $matches)) {
            return strtolower($matches[1]);
        }

        // Try to get from Content-Type header
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($contentType) {
            $mimeToExt = [
                'text/vtt' => 'vtt',
                'application/x-subrip' => 'srt',
                'text/x-subviewer' => 'sub',
                'text/x-ssa' => 'ass',
            ];
            if (isset($mimeToExt[$contentType])) {
                return $mimeToExt[$contentType];
            }
        }

        // Default to vtt
        return 'vtt';
    }
}
