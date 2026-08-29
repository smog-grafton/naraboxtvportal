<?php

namespace Tests\Unit;

use App\Services\IdentityNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IdentityNormalizerTest extends TestCase
{
    #[Test]
    public function token_sets_are_case_space_punctuation_and_order_insensitive(): void
    {
        $normalizer = new IdentityNormalizer;

        $this->assertSame('alvarez diego', $normalizer->tokenSet('  DIEGO,   Alvarez '));
        $this->assertSame($normalizer->tokenSet('Diego Alvarez'), $normalizer->tokenSet('Alvarez Diego'));
        $this->assertNotSame($normalizer->tokenSet('Ssemakula John'), $normalizer->tokenSet('Hamza Ssemakula'));
    }

    #[Test]
    public function ugandan_phone_numbers_are_normalized_without_changing_identity(): void
    {
        $normalizer = new IdentityNormalizer;

        $this->assertSame('256777546225', $normalizer->phone('+256 777 546 225'));
        $this->assertSame('256777546225', $normalizer->phone('0777546225'));
    }
}
