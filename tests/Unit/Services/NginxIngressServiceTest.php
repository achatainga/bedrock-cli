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

    public function test_it_finds_host_ssl_certificate_returns_null_for_invalid_domain(): void
    {
        $this->assertNull($this->service->findHostSslCertificate('invalid;domain'));
        $this->assertNull($this->service->findHostSslCertificate('non_existent_fake_domain_12345.com'));
    }

    public function test_it_enables_container_ssl_with_custom_cert_and_updates_configs(): void
    {
        $tmpDir = sys_get_temp_dir() . '/bedrock_ssl_test_' . uniqid();
        mkdir("{$tmpDir}/docker/nginx", 0755, true);

        $dummyCompose = <<<'YAML'
services:
  nginx:
    ports:
      - "9010:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
YAML;
        file_put_contents("{$tmpDir}/docker-compose.yml", $dummyCompose);

        $dummyNginx = <<<'CONF'
server {
    listen 80;
    server_name 127.0.0.1;
    location ~ \.php$ {
        fastcgi_pass web:9000;
        include fastcgi_params;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }
}
CONF;
        file_put_contents("{$tmpDir}/docker/nginx/default.conf", $dummyNginx);

        $dummyCert = tempnam(sys_get_temp_dir(), 'cert_');
        file_put_contents($dummyCert, "-----BEGIN CERTIFICATE-----\nMIIB...\n-----END CERTIFICATE-----\n");

        $result = $this->service->enableContainerSsl($tmpDir, 'staging.detodo24.com', $dummyCert);
        $this->assertTrue($result);

        // Verificar archivo de certificado combinado
        $this->assertFileExists("{$tmpDir}/docker/nginx/certs/ssl.combined");
        $combinedContent = file_get_contents("{$tmpDir}/docker/nginx/certs/ssl.combined");
        $this->assertStringContainsString('BEGIN CERTIFICATE', $combinedContent);

        // Verificar docker-compose.yml
        $updatedCompose = file_get_contents("{$tmpDir}/docker-compose.yml");
        $this->assertStringContainsString('9010:443', $updatedCompose);
        $this->assertStringContainsString('./docker/nginx/certs:/etc/nginx/certs:ro', $updatedCompose);

        // Verificar default.conf
        $updatedNginx = file_get_contents("{$tmpDir}/docker/nginx/default.conf");
        $this->assertStringContainsString('listen 443 ssl default_server;', $updatedNginx);
        $this->assertStringContainsString('error_page 497 301 =307 https://$http_host$request_uri;', $updatedNginx);
        $this->assertStringContainsString('fastcgi_param HTTPS on;', $updatedNginx);
        $this->assertStringContainsString('fastcgi_param HTTP_X_FORWARDED_PROTO https;', $updatedNginx);

        // Limpieza
        @unlink($dummyCert);
        @unlink("{$tmpDir}/docker/nginx/certs/ssl.combined");
        @rmdir("{$tmpDir}/docker/nginx/certs");
        @unlink("{$tmpDir}/docker/nginx/default.conf");
        @rmdir("{$tmpDir}/docker/nginx");
        @unlink("{$tmpDir}/docker-compose.yml");
        @rmdir($tmpDir);
    }
}
