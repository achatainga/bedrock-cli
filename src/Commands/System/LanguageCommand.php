<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class LanguageCommand extends Command
{
    use ProjectSelectorTrait;
    
    private WpCliService $wpCliService;
    
    public function __construct(WpCliService $wpCliService)
    {
        $this->wpCliService = $wpCliService;
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this->setName('language')
             ->setDescription('Gestionar idioma de WordPress');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }
        
        $helper = $this->getHelper('question');
        $wpcli = $this->wpCliService;
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>   Gestión de Idioma - WordPress   </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        // Idiomas principales
        $languages = [
            'en_US' => 'English (United States)',
            'es_ES' => 'Español (España)',
            'es_VE' => 'Español (Venezuela)',
            'es_MX' => 'Español (México)',
            'es_AR' => 'Español (Argentina)',
            'es_CO' => 'Español (Colombia)',
            'pt_BR' => 'Português (Brasil)',
            'fr_FR' => 'Français (France)',
            'de_DE' => 'Deutsch (Deutschland)',
            'it_IT' => 'Italiano (Italia)',
        ];
        
        // Obtener idioma actual
        $currentLangProcess = $wpcli->custom('option get WPLANG 2>/dev/null');
        $currentLangProcess->run();
        $currentLang = trim($currentLangProcess->getOutput()) ?: 'en_US';
        
        $output->writeln("<info>Idioma actual: {$languages[$currentLang]}</info>");
        $output->writeln('');
        
        // Selector de idioma
        $question = new ChoiceQuestion(
            'Selecciona el nuevo idioma:',
            array_values($languages),
            array_search($languages[$currentLang], array_values($languages))
        );
        
        $selectedLanguageName = $helper->ask($input, $output, $question);
        $selectedLocale = array_search($selectedLanguageName, $languages);
        
        if ($selectedLocale === $currentLang) {
            $output->writeln('<comment>El idioma seleccionado ya está activo</comment>');
            return Command::SUCCESS;
        }
        
        $output->writeln('');
        $output->writeln("<comment>Instalando {$selectedLanguageName}...</comment>");
        
        // Instalar idioma
        $process = $wpcli->custom("language core install {$selectedLocale}");
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>✗ Error al instalar idioma</error>');
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }
        
        // Activar idioma
        $output->writeln("<comment>Activando {$selectedLanguageName}...</comment>");
        $process = $wpcli->custom("site switch-language {$selectedLocale}");
        $process->run();
        
        if (!$process->isSuccessful()) {
            // Fallback: actualizar opción directamente
            $process = $wpcli->custom("option update WPLANG {$selectedLocale}");
            $process->run();
        }
        
        $output->writeln('');
        $output->writeln("<info>✓ Idioma cambiado a {$selectedLanguageName}</info>");
        $output->writeln('');
        $output->writeln('<comment>Refresca el admin de WordPress para ver los cambios</comment>');
        
        return Command::SUCCESS;
    }
}
