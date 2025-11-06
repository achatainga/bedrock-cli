<?php

namespace Tests\Unit\Commands\Theme;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Theme\SearchCommand;
use Symfony\Component\Console\Application;

class SearchCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new SearchCommand();
        
        $this->assertEquals('theme:search', $command->getName());
        $this->assertEquals('Search for WordPress themes interactively', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new SearchCommand());
        
        $this->assertTrue($application->has('theme:search'));
    }
}
