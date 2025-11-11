<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\ComposerService;
use Roots\BedrockCli\Services\VcsValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class ApplyCommand extends Command
{
    use ProjectSelectorTrait;
    
    private ProfileService $profileService;
    private ComposerService $composerService;
    private VcsValidator $vcsValidator;

    public function __construct()
    {
        parent::__construct();
        $this->profileService = new ProfileService();
        $this->composerService = new ComposerService();
        $this->vcsValidator = new VcsValidator();
    }

    protected function configure(): void
    {
        $this
            ->setName('profile:apply')
            ->setDescription('Aplicar un profile a un proyecto existente')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile')
            ->addOption('yes', 'y', null, 'Confirmar automáticamente sin preguntar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (!$this->profileService->profileExists($name)) {
            $output->writeln("<error>El profile '{$name}' no existe</error>");
            return Command::FAILURE;
        }

        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }
        
        $projectRoot = getcwd();

        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "<question>¿Aplicar profile '{$name}' a este proyecto? Esto modificará composer.json (Y/n):</question> ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operación cancelada</comment>');
                return Command::SUCCESS;
            }
        }

        $profile = $this->profileService->loadProfile($name);
        
        // Validar VCS plugins si existen
        if ($this->hasVcsPlugins($profile)) {
            $output->writeln('');
            $output->writeln('<info>🔍 Validando VCS plugins...</info>');
            $profile = $this->validateVcsPlugins($profile, $output);
            $this->profileService->saveProfile($name, $profile);
        }
        
        $output->writeln('');
        $output->writeln('<info>Aplicando profile...</info>');
        
        // IMPORTANTE: Regenerar composer.json ANTES de copiar el nuevo profile
        // para que cleanPreviousProfilePackages() pueda leer el profile anterior
        $this->composerService->generateFromProfile($profile, $projectRoot);
        $output->writeln('✓ composer.json actualizado');
        
        // Actualizar .bedrock/profile.json DESPUÉS de generar composer.json
        $this->composerService->copyProfileToProject($profile, $projectRoot);
        $output->writeln('✓ .bedrock/profile.json actualizado');
        
        // Copiar archivos .zip custom
        $this->composerService->copyCustomZipFiles($profile, $projectRoot);
        $output->writeln('✓ Archivos .zip copiados');
        
        $output->writeln('');
        $output->writeln('<info>Ejecutando composer update...</info>');
        
        $process = new \Symfony\Component\Process\Process(['composer', 'update', '--no-interaction'], $projectRoot);
        $process->setTimeout(600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al ejecutar composer update</error>');
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }
        
        $output->writeln('');
        $output->writeln('<info>✓ Profile aplicado y dependencias instaladas</info>');
        $output->writeln('');
        
        // Verificar si profile tiene activation_order
        if (isset($profile['activation_order'])) {
            $this->applyActivationOrder($input, $output, $profile, $projectRoot);
        }

        return Command::SUCCESS;
    }
    
    private function applyActivationOrder(InputInterface $input, OutputInterface $output, array $profile, string $projectRoot): void
    {
        $orderData = $profile['activation_order'];
        
        // Guardar en config/plugins/activation-order.json
        $configDir = $projectRoot . '/config/plugins';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        $orderFile = $configDir . '/activation-order.json';
        file_put_contents($orderFile, json_encode([
            'activation_order' => $orderData['order'],
            'dependencies' => $orderData['dependencies'] ?? [],
            'created_at' => $orderData['updated_at']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $output->writeln('<info>✓ Orden de activación guardado</info>');
        
        // Preguntar si aplicar ahora
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<fg=yellow>¿Aplicar orden de activación ahora? [S/n]:</> ',
                true
            );
            
            if ($helper->ask($input, $output, $question)) {
                $output->writeln('');
                $output->writeln('<info>Activando plugins en orden...</info>');
                
                $process = new \Symfony\Component\Process\Process(
                    ['php', 'vendor/bin/bedrock', 'plugins:order', 'activate'],
                    $projectRoot
                );
                $process->setTimeout(300);
                $process->run(function ($type, $buffer) use ($output) {
                    $output->write($buffer);
                });
                
                if ($process->isSuccessful()) {
                    $output->writeln('<info>✓ Plugins activados correctamente</info>');
                } else {
                    $output->writeln('<error>Error al activar plugins</error>');
                }
            }
        }
    }
    
    private function hasVcsPlugins(array $profile): bool
    {
        if (empty($profile['plugins']['premium'])) {
            return false;
        }
        
        foreach ($profile['plugins']['premium'] as $plugin) {
            if ($plugin['source'] === 'vcs') {
                return true;
            }
        }
        
        return false;
    }
    
    private function validateVcsPlugins(array $profile, OutputInterface $output): array
    {
        $updated = 0;
        
        foreach ($profile['plugins']['premium'] as $plugin) {
            if ($plugin['source'] === 'vcs') {
                $info = $this->vcsValidator->getPackageInfo($plugin['url']);
                
                if ($info) {
                    $profile['require'][$info['name']] = "dev-{$info['branch']}";
                    $updated++;
                }
            }
        }
        
        if ($updated > 0) {
            $output->writeln("  ✓ {$updated} VCS plugins validados");
        }
        
        return $profile;
    }


}
