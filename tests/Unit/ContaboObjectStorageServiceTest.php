<?php

namespace Tests\Unit;

use App\Services\ContaboObjectStorageService;
use ReflectionClass;
use Tests\TestCase;

class ContaboObjectStorageServiceTest extends TestCase
{
    public function test_it_replaces_embedded_domains_in_generated_filenames(): void
    {
        $service = app(ContaboObjectStorageService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('filenameFromUrl');
        $method->setAccessible(true);

        $filename = $method->invoke(
            $service,
            'https://mobifliks.info/downloadmp4.php?file=luganda/My%20Dearest%20Assassin%20by%20Vj%20Junior%20-%20Mobifliks.com.mp4',
            'video/mp4',
            'mp4'
        );

        $this->assertSame('My_Dearest_Assassin_by_Vj_Junior_naraboxtv.com.mp4', $filename);
        $this->assertStringNotContainsStringIgnoringCase('mobifliks', $filename);
    }
}
