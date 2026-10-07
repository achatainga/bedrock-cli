<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\Sync\AssetSyncService;
use Symfony\Component\Console\Output\NullOutput;

class AssetSyncServiceTest extends TestCase
{
    private AssetSyncService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->service = new AssetSyncService();
        $this->tempDir = sys_get_temp_dir() . '/sync_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->removeDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function test_it_copies_directory_excluding_files(): void
    {
        $src = $this->tempDir . '/src';
        $dst = $this->tempDir . '/dst';
        mkdir($src);
        file_put_contents($src . '/file1.txt', 'hello');
        file_put_contents($src . '/file2.zip', 'zipped');

        $result = $this->service->copyDirectory($src, $dst, ['file2.zip']);
        $this->assertTrue($result);
        $this->assertFileExists($dst . '/file1.txt');
        $this->assertFileDoesNotExist($dst . '/file2.zip');
    }

    public function test_it_enables_nginx_uploads_proxy(): void
    {
        $confFile = $this->tempDir . '/nginx.conf';
        $content = <<<CONF
server {
    listen 80;
    location / {
        try_files \$uri \$uri/ /index.php;
    }
    location ~ \.php$ {
        fastcgi_pass web:9000;
    }
}
CONF;
        file_put_contents($confFile, $content);

        $res = $this->service->enableNginxUploadsProxy($confFile, 'https://detodo24.com');
        $this->assertTrue($res);

        $updated = file_get_contents($confFile);
        $this->assertStringContainsString('location @production_uploads', $updated);
        $this->assertStringContainsString('proxy_pass https://detodo24.com;', $updated);
    }
}
