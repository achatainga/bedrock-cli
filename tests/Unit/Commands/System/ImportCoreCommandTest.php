<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\ImportCoreCommand;
use Symfony\Component\Console\Application;

class ImportCoreCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ImportCoreCommand();
        
        $this->assertEquals('import-core', $command->getName());
        $this->assertEquals('Importar WordPress core', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ImportCoreCommand());
        
        $this->assertTrue($application->has('import-core'));
    }
}
