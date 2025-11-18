<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\AuthService;

class AuthServiceTest extends TestCase
{
    private AuthService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/auth_test_' . uniqid();
        mkdir($this->tempDir);
        
        // Mock composer home
        putenv('APPDATA=' . $this->tempDir);
        
        $this->service = new AuthService();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function test_it_adds_gitlab_auth(): void
    {
        $this->service->addAuth('gitlab', 'gitlab.com', ['token' => 'test-token']);
        
        $this->assertTrue($this->service->hasAuth('gitlab.com'));
    }

    public function test_it_adds_http_basic_auth(): void
    {
        $this->service->addAuth('http-basic', 'example.com', [
            'username' => 'user',
            'password' => 'pass'
        ]);
        
        $this->assertTrue($this->service->hasAuth('example.com'));
    }

    public function test_it_lists_auth(): void
    {
        $this->service->addAuth('github', 'github.com', ['token' => 'token1']);
        $this->service->addAuth('gitlab', 'gitlab.com', ['token' => 'token2']);
        
        $list = $this->service->listAuth();
        
        $this->assertCount(2, $list);
    }

    public function test_it_removes_auth(): void
    {
        $this->service->addAuth('github', 'github.com', ['token' => 'test']);
        $this->assertTrue($this->service->hasAuth('github.com'));
        
        $this->service->removeAuth('github', 'github.com');
        $this->assertFalse($this->service->hasAuth('github.com'));
    }

    public function test_it_loads_auth_for_domain(): void
    {
        $this->service->addAuth('gitlab', 'gitlab.com', ['token' => 'my-token']);
        
        $auth = $this->service->loadAuthForDomain('gitlab.com');
        
        $this->assertNotNull($auth);
        $this->assertEquals('gitlab-oauth', $auth['type']);
        $this->assertEquals('my-token', $auth['token']);
    }
}
