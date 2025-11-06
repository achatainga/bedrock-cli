<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\ComposerService;

class ComposerServiceTest extends TestCase
{
    private ComposerService $service;

    protected function setUp(): void
    {
        $this->service = new ComposerService();
    }

    public function test_it_throws_exception_when_composer_json_does_not_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('composer.json no existe');

        $tempDir = sys_get_temp_dir() . '/composer_test_' . uniqid();
        mkdir($tempDir);

        try {
            $this->service->generateFromProfile([], $tempDir);
        } finally {
            rmdir($tempDir);
        }
    }

    public function test_it_adds_wpackagist_repository_when_missing(): void
    {
        $tempDir = sys_get_temp_dir() . '/composer_test_' . uniqid();
        mkdir($tempDir);

        $composerData = ['require' => ['roots/bedrock' => '^1.0']];
        file_put_contents($tempDir . '/composer.json', json_encode($composerData));

        $profile = [];
        $this->service->generateFromProfile($profile, $tempDir);

        $result = json_decode(file_get_contents($tempDir . '/composer.json'), true);

        $this->assertArrayHasKey('repositories', $result);
        $this->assertCount(1, $result['repositories']);
        $this->assertEquals('https://wpackagist.org', $result['repositories'][0]['url']);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function test_it_does_not_duplicate_wpackagist_repository(): void
    {
        $tempDir = sys_get_temp_dir() . '/composer_test_' . uniqid();
        mkdir($tempDir);

        $composerData = [
            'require' => ['roots/bedrock' => '^1.0'],
            'repositories' => [
                ['type' => 'composer', 'url' => 'https://wpackagist.org']
            ]
        ];
        file_put_contents($tempDir . '/composer.json', json_encode($composerData));

        $profile = [];
        $this->service->generateFromProfile($profile, $tempDir);

        $result = json_decode(file_get_contents($tempDir . '/composer.json'), true);

        $this->assertCount(1, $result['repositories']);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function test_it_adds_profile_repositories(): void
    {
        $tempDir = sys_get_temp_dir() . '/composer_test_' . uniqid();
        mkdir($tempDir);

        $composerData = ['require' => ['roots/bedrock' => '^1.0']];
        file_put_contents($tempDir . '/composer.json', json_encode($composerData));

        $profile = [
            'repositories' => [
                ['type' => 'vcs', 'url' => 'https://github.com/example/repo']
            ]
        ];
        $this->service->generateFromProfile($profile, $tempDir);

        $result = json_decode(file_get_contents($tempDir . '/composer.json'), true);

        $this->assertCount(2, $result['repositories']); // wpackagist + profile repo
        $this->assertEquals('https://github.com/example/repo', $result['repositories'][1]['url']);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function test_it_adds_profile_dependencies(): void
    {
        $tempDir = sys_get_temp_dir() . '/composer_test_' . uniqid();
        mkdir($tempDir);

        $composerData = ['require' => ['roots/bedrock' => '^1.0']];
        file_put_contents($tempDir . '/composer.json', json_encode($composerData));

        $profile = [
            'require' => [
                'wpackagist-plugin/akismet' => '^5.0',
                'wpackagist-theme/twentytwentyfour' => '^1.0'
            ]
        ];
        $this->service->generateFromProfile($profile, $tempDir);

        $result = json_decode(file_get_contents($tempDir . '/composer.json'), true);

        $this->assertArrayHasKey('wpackagist-plugin/akismet', $result['require']);
        $this->assertArrayHasKey('wpackagist-theme/twentytwentyfour', $result['require']);
        $this->assertEquals('^5.0', $result['require']['wpackagist-plugin/akismet']);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function test_it_copies_profile_to_project(): void
    {
        $tempDir = sys_get_temp_dir() . '/composer_test_' . uniqid();
        mkdir($tempDir);

        $profile = [
            'name' => 'test-profile',
            'require' => ['wpackagist-plugin/akismet' => '^5.0']
        ];

        $this->service->copyProfileToProject($profile, $tempDir);

        $profilePath = $tempDir . '/.bedrock/profile.json';
        $this->assertFileExists($profilePath);

        $savedProfile = json_decode(file_get_contents($profilePath), true);
        $this->assertEquals('test-profile', $savedProfile['name']);
        $this->assertArrayHasKey('wpackagist-plugin/akismet', $savedProfile['require']);

        // Cleanup
        unlink($profilePath);
        rmdir($tempDir . '/.bedrock');
        rmdir($tempDir);
    }
}
