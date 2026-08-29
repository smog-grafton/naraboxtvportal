<?php

namespace Tests\Unit;

use App\Http\Controllers\SeoController;
use ReflectionMethod;
use Tests\TestCase;

class SeoImageUrlTest extends TestCase
{
    public function test_storage_keys_use_the_portal_origin_while_fallbacks_use_the_frontend_origin(): void
    {
        config([
            'app.url' => 'https://portal.naraboxtv.com',
            'app.frontend_url' => 'https://naraboxtv.com',
        ]);

        $method = new ReflectionMethod(SeoController::class, 'getImageUrl');
        $controller = app(SeoController::class);

        $this->assertSame(
            'https://portal.naraboxtv.com/storage/tmdb/backdrops/motor-city.jpg',
            $method->invoke($controller, 'tmdb/backdrops/motor-city.jpg')
        );
        $this->assertSame(
            'https://portal.naraboxtv.com/storage/vjs/mark.jpg',
            $method->invoke($controller, '/storage/vjs/mark.jpg')
        );
        $this->assertSame(
            'https://naraboxtv.com/assets/images/backdrop/backdrop.jpg',
            $method->invoke($controller, null)
        );
        $this->assertSame(
            'https://images.example/movie.jpg',
            $method->invoke($controller, 'https://images.example/movie.jpg')
        );
    }
}
