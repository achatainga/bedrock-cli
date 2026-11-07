<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\EnvironmentSecurityService;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

class EnvironmentSecurityServiceTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/bedrock_test_sec_' . uniqid();
        $this->fs->mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    public function test_generate_secure_secret_returns_hex_string_of_correct_length(): void
    {
        $secret = EnvironmentSecurityService::generateSecureSecret(32);
        $this->assertEquals(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $secret);

        $secret24 = EnvironmentSecurityService::generateSecureSecret(24);
        $this->assertEquals(24, strlen($secret24));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{24}$/', $secret24);
    }

    public function test_get_safe_port_binding_forces_localhost(): void
    {
        $binding = EnvironmentSecurityService::getSafePortBinding(3306, true);
        $this->assertEquals('127.0.0.1:3306', $binding);

        $alreadyBound = EnvironmentSecurityService::getSafePortBinding('127.0.0.1:8080', true);
        $this->assertEquals('127.0.0.1:8080', $alreadyBound);
    }

    public function test_audit_project_security_detects_insecure_compose_and_env(): void
    {
        $composeContent = <<<YAML
services:
  nginx:
    ports:
      - "8080:80"
  mysql:
    ports:
      - "3307:3306"
  redis:
    image: redis:7-alpine
    command: redis-server --maxmemory 256mb
    ports:
      - "6379:6379"
YAML;
        file_put_contents("{$this->tmpDir}/docker-compose.yml", $composeContent);

        $envContent = <<<ENV
DB_PASSWORD='mysql'
REDIS_PASSWORD=
ENV;
        file_put_contents("{$this->tmpDir}/.env", $envContent);

        $issues = EnvironmentSecurityService::auditProjectSecurity($this->tmpDir);

        $issueTypes = array_column($issues, 'type');
        $this->assertContains('mysql_public_exposure', $issueTypes);
        $this->assertContains('redis_public_exposure', $issueTypes);
        $this->assertContains('redis_missing_requirepass', $issueTypes);
        $this->assertContains('redis_empty_password', $issueTypes);
        $this->assertContains('mysql_weak_password', $issueTypes);
    }

    public function test_harden_project_security_fixes_docker_compose_and_env(): void
    {
        $composeContent = <<<YAML
services:
  mysql:
    ports:
      - "3307:3306"
  redis:
    image: redis:7-alpine
    command: redis-server --maxmemory 256mb
    ports:
      - "6379:6379"
YAML;
        file_put_contents("{$this->tmpDir}/docker-compose.yml", $composeContent);

        $envContent = <<<ENV
DB_PASSWORD='mysql'
REDIS_PASSWORD=
ENV;
        file_put_contents("{$this->tmpDir}/.env", $envContent);

        $output = new BufferedOutput();
        $hardened = EnvironmentSecurityService::hardenProjectSecurity($this->tmpDir, $output);

        $this->assertTrue($hardened);

        $updatedCompose = file_get_contents("{$this->tmpDir}/docker-compose.yml");
        $this->assertStringContainsString('127.0.0.1:3307:3306', $updatedCompose);
        $this->assertStringContainsString('127.0.0.1:6379:6379', $updatedCompose);
        $this->assertStringContainsString('--requirepass', $updatedCompose);
        $this->assertStringContainsString('--save ""', $updatedCompose);

        $updatedEnv = file_get_contents("{$this->tmpDir}/.env");
        $this->assertMatchesRegularExpression("/REDIS_PASSWORD='[a-f0-9]{32}'/", $updatedEnv);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Redis vinculado exclusivamente a 127.0.0.1', $outputText);
        $this->assertStringContainsString('MySQL vinculado exclusivamente a 127.0.0.1', $outputText);
        $this->assertStringContainsString('Contraseña de MySQL en .env es por defecto', $outputText);
    }
}
