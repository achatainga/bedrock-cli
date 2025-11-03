<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;

class SearchMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('search:menu')
             ->setDescription('Menú de búsqueda WordPress.org');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan>║</>   🔍 SEARCH - WordPress.org      <fg=cyan>║</>');
            $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            $output->writeln('<comment>Busca plugins y temas en el repositorio de WordPress.org.</comment>');
            $output->writeln('');

            $output->writeln(' <fg=cyan>[1]</> 🔌 Buscar plugins');
            $output->writeln(' <fg=cyan>[2]</> ℹ️  Info de plugin');
            $output->writeln(' <fg=cyan>[3]</> 🎨 Buscar temas');
            $output->writeln(' <fg=cyan>[4]</> ℹ️  Info de tema');
            $output->writeln(' <fg=cyan>[0]</> ❌ Volver');
            $output->writeln('');

            $question = new Question('<fg=yellow>Opción [0-4]: </>', '0');
            $selectedIndex = $helper->ask($input, $output, $question);
            
            if (!is_numeric($selectedIndex) || $selectedIndex < 0 || $selectedIndex > 4) {
                $output->writeln('<error>Opción inválida</error>');
                continue;
            }

            if ($selectedIndex === '0') {
                return Command::SUCCESS;
            }

            $output->writeln('');
            
            switch ($selectedIndex) {
                case '1':
                    $this->searchPlugins($input, $output);
                    break;
                case '2':
                    $this->pluginInfo($input, $output);
                    break;
                case '3':
                    $this->searchThemes($input, $output);
                    break;
                case '4':
                    $this->themeInfo($input, $output);
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function searchPlugins(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $query = $helper->ask($input, $output, new Question('<fg=cyan>Ingresa tu búsqueda de plugin:</> '));
        
        if (empty($query)) {
            $output->writeln('<error>❌ Búsqueda vacía</error>');
            return Command::FAILURE;
        }
        
        $command = $this->getApplication()->find('plugin:search');
        $arrayInput = new ArrayInput(['query' => $query]);
        return $command->run($arrayInput, $output);
    }

    private function pluginInfo(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $slug = $helper->ask($input, $output, new Question('<fg=cyan>Ingresa el slug del plugin:</> '));
        
        if (empty($slug)) {
            $output->writeln('<error>❌ Slug vacío</error>');
            return Command::FAILURE;
        }
        
        $command = $this->getApplication()->find('plugin:info');
        $arrayInput = new ArrayInput(['slug' => $slug]);
        return $command->run($arrayInput, $output);
    }

    private function searchThemes(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $query = $helper->ask($input, $output, new Question('<fg=cyan>Ingresa tu búsqueda de tema:</> '));
        
        if (empty($query)) {
            $output->writeln('<error>❌ Búsqueda vacía</error>');
            return Command::FAILURE;
        }
        
        $command = $this->getApplication()->find('theme:search');
        $arrayInput = new ArrayInput(['query' => $query]);
        return $command->run($arrayInput, $output);
    }

    private function themeInfo(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $slug = $helper->ask($input, $output, new Question('<fg=cyan>Ingresa el slug del tema:</> '));
        
        if (empty($slug)) {
            $output->writeln('<error>❌ Slug vacío</error>');
            return Command::FAILURE;
        }
        
        $command = $this->getApplication()->find('theme:info');
        $arrayInput = new ArrayInput(['slug' => $slug]);
        return $command->run($arrayInput, $output);
    }
}
