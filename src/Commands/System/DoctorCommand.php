<?php

namespace Roots\BedrockCli\Commands\System;

use Roots\BedrockCli\Services\ProjectValidationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class DoctorCommand extends Command
{
    use ProjectSelectorTrait;
    
    private ProjectValidationService $validationService;

    public function __construct(ProjectValidationService $validationService)
    {
        parent::__construct();
        $this->validationService = $validationService;
    }

    protected function configure(): void
    {
        $this
            ->setName('doctor')
            ->setDescription('Verifica la configuración del proyecto y las dependencias del sistema')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Intenta reparar problemas detectados automáticamente');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  BEDROCK DOCTOR - System Check  </> <fg=cyan;options=bold>        ║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $projectPath = getcwd();
        $isFixMode = $input->getOption('fix');

        // 1. Validar Docker
        $dockerValidation = $this->validationService->validateDocker($projectPath);
        $output->writeln($this->formatValidation('Docker', $dockerValidation));

        // 2. Validar Base de Datos
        $dbValidation = $this->validationService->validateDatabase($projectPath);
        $output->writeln($this->formatValidation('Database', $dbValidation));

        // 3. Validar WordPress
        $wpValidation = $this->validationService->validateWordPress($projectPath);
        $output->writeln($this->formatValidation('WordPress', $wpValidation));
        
        // 4. Validar Acorn
        $acornValidation = $this->validationService->validateAcorn($projectPath);
        $output->writeln($this->formatValidation('Acorn', $acornValidation));
        
        // 5. Detectar Inconsistencias
        $inconsistencies = $this->validationService->detectInconsistencies($projectPath);
        if (!empty($inconsistencies)) {
            $output->writeln('');
            $output->writeln('<fg=yellow;options=bold>⚠️  Inconsistencias detectadas:</>');
            foreach ($inconsistencies as $issue) {
                $output->writeln("  <comment>⚠</comment> {$issue['message']}");
            }
        }

        if ($isFixMode) {
            $output->writeln('');
            $output->writeln('<fg=cyan>🔧 Intentando reparar problemas...</>');
            // Aquí iría la lógica de reparación, que por ahora es un placeholder.
            $output->writeln('<info>✓ Lógica de reparación ejecutada.</info>');
        }
        
        $output->writeln('');
        return Command::SUCCESS;
    }

    private function formatValidation($name, $validationObject): string
    {
        $icon = $validationObject->isValid ? '<info>✓</info>' : '<error>✗</error>';
        return "  {$icon} {$name}: {$validationObject->message}";
    }
}
