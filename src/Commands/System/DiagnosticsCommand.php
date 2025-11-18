<?php

namespace Roots\BedrockCli\Commands\System;

use Roots\BedrockCli\Services\ErrorLoggerService;
use Roots\BedrockCli\Services\CliRunnerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DiagnosticsCommand extends Command
{
    private ErrorLoggerService $errorLogger;
    private CliRunnerService $cliRunner;
    
    public function __construct(ErrorLoggerService $errorLogger, CliRunnerService $cliRunner)
    {
        parent::__construct();
        $this->errorLogger = $errorLogger;
        $this->cliRunner = $cliRunner;
    }
    
    protected function configure(): void
    {
        $this->setName('diagnostics')
             ->setDescription('Diagnóstico completo del sistema y dependencias externas');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<fg=cyan;options=bold>🔍 DIAGNÓSTICO COMPLETO DEL SISTEMA</>');
        $output->writeln('');
        
        // Log system info
        $this->errorLogger->logSystemInfo();
        
        $executables = ['git', 'docker', 'docker-compose', 'wp', 'composer', 'php'];
        
        foreach ($executables as $executable) {
            $output->write("Verificando {$executable}... ");
            
            if ($this->cliRunner->isExecutableAvailable($executable)) {
                $path = $this->cliRunner->getExecutablePath($executable);
                $output->writeln("<fg=green>✓ Encontrado en: {$path}</>");
                
                // Test version command
                $version = $this->cliRunner->runCommand($executable, ['--version']);
                if ($version) {
                    $output->writeln("  <comment>Versión: " . substr($version, 0, 100) . "</comment>");
                }
            } else {
                $output->writeln('<fg=red>❌ No encontrado en PATH</>');
            }
        }
        
        $output->writeln('');
        $output->writeln('<info>Log guardado en: ' . $this->errorLogger->getLogPath() . '</info>');
        
        return Command::SUCCESS;
    }
}