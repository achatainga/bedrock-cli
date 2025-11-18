<?php

namespace Roots\BedrockCli\Commands\Profile;

use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\VcsValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateVcsCommand extends Command
{
    private ProfileService $profileService;
    private VcsValidator $vcsValidator;
    
    public function __construct(
        ProfileService $profileService,
        VcsValidator $vcsValidator
    ) {
        $this->profileService = $profileService;
        $this->vcsValidator = $vcsValidator;
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this->setName('profile:validate-vcs')
            ->setDescription('Validar y agregar VCS plugins al require del profile')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (!$this->profileService->profileExists($name)) {
            $output->writeln("<error>Profile '{$name}' no existe</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($name);
        
        $output->writeln('');
        $output->writeln('<info>🔍 Validando VCS plugins...</info>');
        $output->writeln('');

        $updated = 0;
        $failed = [];

        if (!empty($profile['plugins']['premium'])) {
            foreach ($profile['plugins']['premium'] as $plugin) {
                if ($plugin['source'] === 'vcs') {
                    $output->write("  Validando {$plugin['url']}... ");
                    
                    $info = $this->vcsValidator->getPackageInfo($plugin['url']);
                    
                    if ($info) {
                        $profile['require'][$info['name']] = "dev-{$info['branch']}";
                        $output->writeln("<info>✓ {$info['name']}</info>");
                        $updated++;
                    } else {
                        $output->writeln('<error>✗ No se pudo validar</error>');
                        $failed[] = $plugin['url'];
                    }
                }
            }
        }

        $output->writeln('');

        if ($updated > 0) {
            $this->profileService->saveProfile($name, $profile);
            $output->writeln("<info>✅ Profile actualizado: {$updated} packages agregados</info>");
        }

        if (!empty($failed)) {
            $output->writeln('');
            $output->writeln("<comment>⚠️  {count($failed)} plugins no pudieron validarse:</comment>");
            foreach ($failed as $url) {
                $output->writeln("  • {$url}");
            }
            $output->writeln('');
            $output->writeln('<comment>Verifica que tengas credenciales configuradas:</comment>');
            $output->writeln('  bedrock auth:add gitlab');
        }

        $output->writeln('');
        return Command::SUCCESS;
    }
}
