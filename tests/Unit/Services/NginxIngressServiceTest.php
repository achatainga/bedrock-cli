<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\Ingress\NginxIngressService;
use Symfony\Component\Console\Output\BufferedOutput;

class NginxIngressServiceTest extends TestCase
{
    private NginxIngressService $service;

    protected function setUp(): void
    {
        $this->service = new NginxIngressService();
    }

    public function test_it_validates_domain_syntax(): void
    {
        $output = new BufferedOutput();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nombre de dominio inválido');
        $this->service->configureSubdomainProxy('bad domain name with spaces', 8082, $output);
    }

    public function test_it_rejects_path_traversal_in_domain(): void
    {
        $output = new BufferedOutput();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nombre de dominio inválido');
        $this->service->configureSubdomainProxy('sub..domain.com', 8082, $output);
    }

    public function test_it_validates_port_range(): void
    {
        $output = new BufferedOutput();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Puerto inválido para Ingress');
        $this->service->configureSubdomainProxy('staging.detodo24.com', 70000, $output);
    }

    public function test_it_detects_cpanel_user_reliably(): void
    {
        $user = $this->service->detectCpanelUser();
        $this->assertNotEmpty($user);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_\-]+$/', $user);
    }
}
