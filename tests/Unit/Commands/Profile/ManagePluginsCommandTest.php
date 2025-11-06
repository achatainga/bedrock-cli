<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\ManagePluginsCommand;
use Roots\BedrockCli\Application;

class ManagePluginsCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ManagePluginsCommand();
        
        $this->assertEquals('profile:manage-plugins', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $app = new Application();
        $command = $app->find('profile:manage-plugins');
        
        $this->assertInstanceOf(ManagePluginsCommand::class, $command);
    }
}
