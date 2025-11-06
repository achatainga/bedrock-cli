<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\OrderBuilderCommand;
use Symfony\Component\Console\Application;

class OrderBuilderCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new OrderBuilderCommand();
        
        $this->assertEquals('plugins:order:build', $command->getName());
        $this->assertEquals('Constructor interactivo de orden de activación', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new OrderBuilderCommand());
        
        $this->assertTrue($application->has('plugins:order:build'));
    }
}
