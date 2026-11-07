<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\PortFinderService;

class PortFinderServiceTest extends TestCase
{
    private PortFinderService $service;

    protected function setUp(): void
    {
        $this->service = new PortFinderService();
    }

    public function test_it_discovers_available_port(): void
    {
        $port = $this->service->findAvailablePort(45000);
        $this->assertIsInt($port);
        $this->assertGreaterThanOrEqual(45000, $port);
        $this->assertTrue($this->service->isPortAvailable($port));
    }

    public function test_it_allocates_stack_ports_without_collisions(): void
    {
        $stack = $this->service->findStackPorts(46000, 46100, 46200);
        $this->assertArrayHasKey('http', $stack);
        $this->assertArrayHasKey('mysql', $stack);
        $this->assertArrayHasKey('redis', $stack);

        $this->assertNotEquals($stack['http'], $stack['mysql']);
        $this->assertNotEquals($stack['http'], $stack['redis']);
        $this->assertNotEquals($stack['mysql'], $stack['redis']);
    }
}
