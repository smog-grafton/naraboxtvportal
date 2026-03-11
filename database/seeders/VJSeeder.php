<?php

namespace Database\Seeders;

use App\Models\VJ;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class VJSeeder extends Seeder
{
    public function run(): void
    {
        $vjs = [
            [
                'name' => 'VJ Junior',
                'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop',
                'banner' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1920&h=600&fit=crop',
                'rating' => 4.9,
                'bio' => 'The legend of Luganda translation. Over two decades of bringing global cinema to local hearts.',
                'translated_count' => 1542,
                'specialties' => ['Action', 'Sci-Fi'],
            ],
            [
                'name' => 'VJ Jingo',
                'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop',
                'rating' => 4.8,
                'bio' => 'Expert storytelling through voice. Known for his unique comedic timing.',
                'translated_count' => 980,
                'specialties' => ['Comedy', 'Drama'],
            ],
            [
                'name' => 'VJ Mark',
                'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=400&h=400&fit=crop',
                'rating' => 4.7,
                'bio' => 'The voice of tension. Specialized in high-stakes thrillers and horror.',
                'translated_count' => 650,
                'specialties' => ['Horror', 'Mystery'],
            ],
            [
                'name' => 'VJ Emmy',
                'image' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=400&h=400&fit=crop',
                'rating' => 4.6,
                'bio' => 'The voice of the youth. Combining modern slang with traditional storytelling.',
                'translated_count' => 420,
                'specialties' => ['Romance', 'Action'],
            ],
        ];

        foreach ($vjs as $vjData) {
            $specialties = $vjData['specialties'];
            unset($vjData['specialties']);
            
            $slug = \Illuminate\Support\Str::slug($vjData['name']);
            $vj = VJ::firstOrCreate(['slug' => $slug], array_merge($vjData, ['slug' => $slug]));
            
            // Attach genres (only if not already attached)
            foreach ($specialties as $specialty) {
                $genre = Genre::where('name', $specialty)->first();
                if ($genre) {
                    // Use syncWithoutDetaching to avoid duplicates
                    $vj->genres()->syncWithoutDetaching([$genre->id]);
                }
            }
        }
    }
}
