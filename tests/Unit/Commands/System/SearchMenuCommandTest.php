<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\SearchMenuCommand;
use Symfony\Component\Console\Application;

class SearchMenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new SearchMenuCommand();
        
        $this->assertEquals('search:menu', $command->getName());
        $this->assertEquals('Menú de búsqueda WordPress.org', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new SearchMenuCommand());
        
        $this->assertTrue($application->has('search:menu'));
    }
}
