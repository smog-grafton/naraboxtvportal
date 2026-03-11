<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Movie', 'slug' => 'movie', 'description' => 'Standalone movies'],
            ['name' => 'Series', 'slug' => 'series', 'description' => 'TV series and shows'],
            ['name' => 'Live', 'slug' => 'live', 'description' => 'Live streaming content'],
            ['name' => 'VJ Translated', 'slug' => 'vj-translated', 'description' => 'Content translated by VJs'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
