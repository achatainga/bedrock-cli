<?php

declare(strict_types=1);

namespace Tests\Unit\Commands\Snapshot;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Snapshot\CloneCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CloneCommandTest extends TestCase
{
    private Application $app;
    private CloneCommand $command;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->command = new CloneCommand();
        $this->app->add($this->command);
    }

    public function test_it_configures_command_properly(): void
    {
        $this->assertEquals('snapshot:clone', $this->command->getName());
        $this->assertContains('clone', $this->command->getAliases());
        $this->assertTrue($this->command->getDefinition()->hasArgument('name'));
        $this->assertTrue($this->command->getDefinition()->hasOption('source'));
        $this->assertTrue($this->command->getDefinition()->hasOption('remote'));
        $this->assertTrue($this->command->getDefinition()->hasOption('proxy-uploads'));
        $this->assertTrue($this->command->getDefinition()->hasOption('dry-run'));
    }

    public function test_it_fails_when_no_source_or_remote_provided(): void
    {
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            'name' => 'test-project',
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Debes especificar un origen local', $tester->getDisplay());
    }

    public function test_it_runs_in_dry_run_mode(): void
    {
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute([
            'name' => 'test-project',
            '--source' => sys_get_temp_dir(),
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('[DRY-RUN]', $tester->getDisplay());
    }

    public function test_it_extracts_source_config_from_local_wp_config(): void
    {
        $tmpDir = sys_get_temp_dir() . '/bedrock_test_wp_' . uniqid();
        mkdir($tmpDir, 0755, true);
        $sampleWpConfig = <<<'PHP'
<?php
define( 'DB_NAME', 'test_marketplace' );
define( 'DB_USER', 'test_user' );
define( 'DB_PASSWORD', 'test_pass' );
define( 'DB_HOST', '127.0.0.1:3306' );
$table_prefix = 'elgg_';
PHP;
        file_put_contents($tmpDir . '/wp-config.php', $sampleWpConfig);

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $config = $this->command->extractSourceConfig($tmpDir, null, '', $output);

        $this->assertEquals('test_marketplace', $config['db_name']);
        $this->assertEquals('test_user', $config['db_user']);
        $this->assertEquals('test_pass', $config['db_pass']);
        $this->assertEquals('127.0.0.1:3306', $config['db_host']);
        $this->assertEquals('elgg_', $config['table_prefix']);

        unlink($tmpDir . '/wp-config.php');
        rmdir($tmpDir);
    }

    public function test_it_updates_env_file_with_target_url_and_db_prefix(): void
    {
        $tmpDir = sys_get_temp_dir() . '/bedrock_test_env_' . uniqid();
        mkdir($tmpDir, 0755, true);
        $initialEnv = <<<'ENV'
WP_ENV='development'
WP_PORT=8080
WP_HOME='http://localhost:${WP_PORT}'
WP_SITEURL="${WP_HOME}/wp"
ENV;
        file_put_contents($tmpDir . '/.env', $initialEnv);

        $this->command->updateEnvFile($tmpDir, 'https://staging.detodo24.com', 'elgg_');

        $updated = file_get_contents($tmpDir . '/.env');
        $this->assertStringContainsString("WP_HOME='https://staging.detodo24.com'", $updated);
        $this->assertStringContainsString("WP_SITEURL='https://staging.detodo24.com/wp'", $updated);
        $this->assertStringContainsString("DB_PREFIX='elgg_'", $updated);

        unlink($tmpDir . '/.env');
        rmdir($tmpDir);
    }
}
