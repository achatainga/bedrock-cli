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
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</>   🔍 DIAGNÓSTICO PROFUNDO DEL SISTEMA <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        // Log system info
        $this->errorLogger->logSystemInfo();
        
        $output->writeln('<fg=yellow;options=bold>📦 REQUISITOS DE SOFTWARE:</>');
        $executables = [
            'php' => 'Motor PHP',
            'composer' => 'Gestor de dependencias',
            'docker' => 'Motor de contenedores',
            'docker-compose' => 'Orquestador Docker',
            'git' => 'Control de versiones',
            'wp' => 'WP-CLI Local'
        ];
        
        foreach ($executables as $executable => $label) {
            $output->write(str_pad("  • {$label}: ", 35));
            
            if ($this->cliRunner->isExecutableAvailable($executable)) {
                $path = $this->cliRunner->getExecutablePath($executable);
                $version = $this->cliRunner->runCommand($executable, [$executable === 'php' ? '-v' : '--version']);
                
                $versionStr = 'N/A';
                if ($version) {
                    $firstLine = explode("\n", trim($version))[0];
                    $versionStr = preg_replace('/(\(.*\)|Copyright.*)/', '', $firstLine);
                }
                
                $output->writeln("<fg=green>✓</> <fg=gray>[" . trim($versionStr) . "]</>");
                // $output->writeln("    <fg=gray;options=italic>Ruta: {$path}</>");
            } else {
                $output->writeln('<error>✗ No encontrado</error>');
            }
        }
        
        $output->writeln('');
        $output->writeln('<fg=yellow;options=bold>🌐 CONECTIVIDAD & AMBIENTE:</>');
        
        $checks = [
            'Conexión WordPress.org' => 'https://api.wordpress.org/core/version-check/1.7/',
            'Conexión GitHub' => 'https://api.github.com',
            'Conexión GitLab' => 'https://gitlab.com/api/v4/version'
        ];
        
        foreach ($checks as $name => $url) {
            $output->write(str_pad("  • {$name}: ", 35));
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Bedrock-CLI-Diagnostics');
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($code >= 200 && $code < 400) {
                $output->writeln('<fg=green>✓ Online</>');
            } else {
                $output->writeln('<error>✗ Error/Offline (' . $code . ')</error>');
            }
        }
        
        $output->writeln('');
        $output->writeln('<info>📝 Informe detallado guardado en:</info>');
        $output->writeln('<comment>' . $this->errorLogger->getLogPath() . '</comment>');
        $output->writeln('');
        
        return Command::SUCCESS;
    }
}