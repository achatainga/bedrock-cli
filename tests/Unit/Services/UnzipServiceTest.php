<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\UnzipService;

class UnzipServiceTest extends TestCase
{
    private UnzipService $service;

    protected function setUp(): void
    {
        $this->service = new UnzipService();
    }

    public function test_it_initializes(): void
    {
        $this->assertInstanceOf(UnzipService::class, $this->service);
    }
}
