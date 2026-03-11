<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            'Action', 'Adventure', 'Comedy', 'Crime', 'Drama', 'Fantasy',
            'History', 'Horror', 'Medical', 'Mystery', 'Music', 'Romance',
            'Racing', 'Sci-Fi', 'Thriller', 'Western'
        ];

        foreach ($genres as $genre) {
            $slug = \Illuminate\Support\Str::slug($genre);
            Genre::firstOrCreate(['slug' => $slug], [
                'name' => $genre,
                'slug' => $slug,
            ]);
        }
    }
}
