<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\ProfileService;
use RuntimeException;

class ProfileServiceTest extends TestCase
{
    private string $tempDir;
    private string $originalHome;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/profile_test_' . uniqid();
        mkdir($this->tempDir);
        
        $this->originalHome = getenv('HOME') ?: getenv('USERPROFILE');
        putenv('HOME=' . $this->tempDir);
        putenv('USERPROFILE=' . $this->tempDir);
    }

    protected function tearDown(): void
    {
        putenv('HOME=' . $this->originalHome);
        putenv('USERPROFILE=' . $this->originalHome);
        
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

    public function test_it_creates_directory_structure_on_initialization(): void
    {
        $service = new ProfileService();
        
        $this->assertDirectoryExists($this->tempDir . '/.bedrock-cli');
        $this->assertDirectoryExists($this->tempDir . '/.bedrock-cli/profiles');
        $this->assertFileExists($this->tempDir . '/.bedrock-cli/config.json');
    }

    public function test_it_checks_if_profile_exists(): void
    {
        $service = new ProfileService();
        
        $profileData = ['name' => 'test', 'description' => 'Test profile'];
        $service->saveProfile('test', $profileData);
        
        $this->assertTrue($service->profileExists('test'));
        $this->assertFalse($service->profileExists('nonexistent'));
    }

    public function test_it_saves_and_loads_profile(): void
    {
        $service = new ProfileService();
        
        $profileData = [
            'name' => 'test-profile',
            'description' => 'Test description',
            'require' => ['wpackagist-plugin/akismet' => '^5.0']
        ];
        
        $service->saveProfile('test', $profileData);
        $loaded = $service->loadProfile('test');
        
        $this->assertEquals('test-profile', $loaded['name']);
        $this->assertEquals('Test description', $loaded['description']);
        $this->assertArrayHasKey('wpackagist-plugin/akismet', $loaded['require']);
    }

    public function test_it_throws_exception_when_loading_nonexistent_profile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Profile 'nonexistent' no existe");
        
        $service = new ProfileService();
        $service->loadProfile('nonexistent');
    }

    public function test_it_lists_all_profiles(): void
    {
        $service = new ProfileService();
        
        $service->saveProfile('profile1', ['name' => 'Profile 1', 'description' => 'First']);
        $service->saveProfile('profile2', ['name' => 'Profile 2', 'description' => 'Second']);
        
        $profiles = $service->listProfiles();
        
        $this->assertGreaterThanOrEqual(2, count($profiles));
        $this->assertArrayHasKey('profile1', $profiles);
        $this->assertArrayHasKey('profile2', $profiles);
        $this->assertEquals('First', $profiles['profile1']['description']);
    }

    public function test_it_deletes_profile(): void
    {
        $service = new ProfileService();
        
        $service->saveProfile('test', ['name' => 'Test']);
        $this->assertTrue($service->profileExists('test'));
        
        $result = $service->deleteProfile('test');
        
        $this->assertTrue($result);
        $this->assertFalse($service->profileExists('test'));
    }

    public function test_it_throws_exception_when_deleting_nonexistent_profile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Profile 'nonexistent' no existe");
        
        $service = new ProfileService();
        $service->deleteProfile('nonexistent');
    }

    public function test_it_gets_and_updates_config(): void
    {
        $service = new ProfileService();
        
        $config = $service->getConfig();
        $this->assertArrayHasKey('version', $config);
        $this->assertEquals('default', $config['default_profile']);
        
        $service->updateConfig(['default_profile' => 'custom']);
        $updated = $service->getConfig();
        
        $this->assertEquals('custom', $updated['default_profile']);
    }
}
