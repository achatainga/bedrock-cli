<?php

namespace Tests\Unit\Commands\Plugin;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugin\SearchCommand;
use Symfony\Component\Console\Application;

class SearchCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new SearchCommand();
        
        $this->assertEquals('plugin:search', $command->getName());
        $this->assertEquals('Buscar plugins en WordPress.org', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new SearchCommand());
        
        $this->assertTrue($application->has('plugin:search'));
    }
}
