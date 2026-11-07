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

    public function test_it_extracts_safe_zip_and_rejects_zip_slip(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive not available');
        }

        $tmpDir = sys_get_temp_dir() . '/bedrock_test_unzip_' . uniqid();
        mkdir($tmpDir, 0755, true);
        $zipFile = $tmpDir . '/test.zip';
        $dest = $tmpDir . '/out';

        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'world');
        $zip->close();

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $res = $this->service->unzip($zipFile, $dest, $output);
        $this->assertTrue($res);
        $this->assertFileExists($dest . '/hello.txt');

        // Test Zip Slip rejection
        $slipZip = $tmpDir . '/slip.zip';
        $zipSlip = new \ZipArchive();
        $zipSlip->open($slipZip, \ZipArchive::CREATE);
        $zipSlip->addFromString('../escaped.txt', 'evil');
        $zipSlip->close();

        $resSlip = $this->service->unzip($slipZip, $dest, $output);
        $this->assertFalse($resSlip);

        (new \Symfony\Component\Filesystem\Filesystem())->remove($tmpDir);
    }
}
