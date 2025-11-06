<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\PremiumRepoService;

class PremiumRepoServiceTest extends TestCase
{
    private PremiumRepoService $service;

    protected function setUp(): void
    {
        $this->service = new PremiumRepoService();
    }

    public function test_it_initializes(): void
    {
        $this->assertInstanceOf(PremiumRepoService::class, $this->service);
    }

    public function test_it_initializes_with_repo_url(): void
    {
        $service = new PremiumRepoService('https://github.com/user/repo', 'main');
        $this->assertInstanceOf(PremiumRepoService::class, $service);
    }
}
