<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\VideoFetchController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class VideoFetchControllerTest extends TestCase
{
    public function test_it_normalizes_spaces_in_remote_import_urls_before_validation(): void
    {
        $controller = new VideoFetchController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('normalizeRemoteUrl');
        $method->setAccessible(true);

        $normalized = $method->invoke(
            $controller,
            'https://mobifliks.info/downloadmp4.php?file=luganda/My Dearest Assassin by Vj Junior - Mobifliks.com.mp4'
        );

        $this->assertSame(
            'https://mobifliks.info/downloadmp4.php?file=luganda/My%20Dearest%20Assassin%20by%20Vj%20Junior%20-%20Mobifliks.com.mp4',
            $normalized
        );
    }
}
