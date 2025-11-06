<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Traits\PremiumAssetsTrait;

class PremiumAssetsTraitTest extends TestCase
{
    use PremiumAssetsTrait;

    public function test_trait_can_be_used(): void
    {
        $this->assertTrue(true);
    }
}
