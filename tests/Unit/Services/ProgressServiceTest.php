<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\ProgressService;

class ProgressServiceTest extends TestCase
{
    private ProgressService $service;

    protected function setUp(): void
    {
        $this->service = new ProgressService();
    }

    public function test_it_initializes(): void
    {
        $this->assertInstanceOf(ProgressService::class, $this->service);
    }
}
