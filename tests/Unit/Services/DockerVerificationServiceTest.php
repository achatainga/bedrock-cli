<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\DockerVerificationService;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

class DockerVerificationServiceTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/bedrock_test_docker_verif_' . uniqid();
        $this->fs->mkdir($this->tmpDir . '/config');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    public function test_it_injects_fs_method_into_application_php_and_env(): void
    {
        $appConfig = "{$this->tmpDir}/config/application.php";
        $initialContent = "<?php\nuse Roots\\WPConfig\\Config;\nConfig::define('WP_ENV', 'development');\nConfig::apply();\n";
        file_put_contents($appConfig, $initialContent);

        $envFile = "{$this->tmpDir}/.env";
        file_put_contents($envFile, "WP_ENV='development'\n");

        $service = new DockerVerificationService();
        $output = new BufferedOutput();

        $service->ensureFilesystemConfig($this->tmpDir, $output);

        $updatedApp = file_get_contents($appConfig);
        $this->assertStringContainsString("Config::define('FS_METHOD', env('FS_METHOD') ?: 'direct');", $updatedApp);

        $updatedEnv = file_get_contents($envFile);
        $this->assertStringContainsString("FS_METHOD='direct'", $updatedEnv);
    }
}
