<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class TestCommandWithProjectSelector extends Command
{
    use ProjectSelectorTrait;

    protected function configure(): void
    {
        $this->setName('test:project-selector');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->ensureBedrockProject($input, $output);
        
        if ($project === null) {
            $output->writeln('NO_PROJECT');
            return Command::FAILURE;
        }

        $output->writeln('PROJECT:' . basename($project));
        return Command::SUCCESS;
    }
}

class ProjectSelectorIntegrationTest extends TestCase
{
    public function test_it_handles_multiple_projects_with_user_selection(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);
        
        $project1 = $tempDir . '/project1';
        $project2 = $tempDir . '/project2';
        mkdir($project1);
        mkdir($project2);
        
        $composerJson = ['require' => ['roots/bedrock' => '^1.0']];
        file_put_contents($project1 . '/composer.json', json_encode($composerJson));
        file_put_contents($project2 . '/composer.json', json_encode($composerJson));

        $originalDir = getcwd();
        chdir($tempDir);

        $application = new Application();
        $command = new TestCommandWithProjectSelector();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['2']); // Select project 2
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PROJECT:project2', $output);

        // Cleanup
        chdir($originalDir);
        unlink($project1 . '/composer.json');
        unlink($project2 . '/composer.json');
        rmdir($project1);
        rmdir($project2);
        rmdir($tempDir);
    }

    public function test_it_handles_cancellation(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);
        
        $project1 = $tempDir . '/project1';
        $project2 = $tempDir . '/project2';
        mkdir($project1);
        mkdir($project2);
        
        $composerJson = ['require' => ['roots/bedrock' => '^1.0']];
        file_put_contents($project1 . '/composer.json', json_encode($composerJson));
        file_put_contents($project2 . '/composer.json', json_encode($composerJson));

        $originalDir = getcwd();
        chdir($tempDir);

        $application = new Application();
        $command = new TestCommandWithProjectSelector();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['0']); // Cancel
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('NO_PROJECT', $output);

        // Cleanup
        chdir($originalDir);
        unlink($project1 . '/composer.json');
        unlink($project2 . '/composer.json');
        rmdir($project1);
        rmdir($project2);
        rmdir($tempDir);
    }
}
