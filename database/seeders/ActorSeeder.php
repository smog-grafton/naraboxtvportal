<?php

namespace Database\Seeders;

use App\Models\Actor;
use Illuminate\Database\Seeder;

class ActorSeeder extends Seeder
{
    public function run(): void
    {
        $actors = [
            ['name' => 'Tom Hardy', 'slug' => 'tom-hardy', 'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=600&fit=crop', 'bio' => 'Acclaimed actor known for intense performances'],
            ['name' => 'Zendaya', 'slug' => 'zendaya', 'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&h=600&fit=crop', 'bio' => 'Rising star in modern cinema'],
            ['name' => 'Cillian Murphy', 'slug' => 'cillian-murphy', 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop', 'bio' => 'Versatile actor with remarkable range'],
            ['name' => 'Florence Pugh', 'slug' => 'florence-pugh', 'image' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&h=600&fit=crop', 'bio' => 'Award-winning actress'],
            ['name' => 'Austin Butler', 'slug' => 'austin-butler', 'image' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=400&h=600&fit=crop', 'bio' => 'Talented performer'],
        ];

        foreach ($actors as $actor) {
            Actor::firstOrCreate(['slug' => $actor['slug']], $actor);
        }
    }
}
