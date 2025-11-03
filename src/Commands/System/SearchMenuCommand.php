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
            $output->writeln('<cyan>╔═══════════════════════════════════════╗</cyan>');
            $output->writeln('<cyan>║</cyan>   🔍 SEARCH - WordPress.org        <cyan>║</cyan>');
            $output->writeln('<cyan>╚═══════════════════════════════════════╝</cyan>');
            $output->writeln('');

            $choices = [
                '1' => 'Buscar plugins',
                '2' => 'Info de plugin',
                '3' => 'Buscar temas',
                '4' => 'Info de tema',
                '0' => 'Volver',
            ];

            $output->writeln(' <cyan>[1]</cyan> 🔌 Buscar plugins');
            $output->writeln(' <cyan>[2]</cyan> ℹ️  Info de plugin');
            $output->writeln(' <cyan>[3]</cyan> 🎨 Buscar temas');
            $output->writeln(' <cyan>[4]</cyan> ℹ️  Info de tema');
            $output->writeln(' <cyan>[0]</cyan> ❌ Volver');
            $output->writeln('');

            $question = new ChoiceQuestion('', $choices, '0');
            $question->setAutocompleterValues(null);
            $selectedIndex = $helper->ask($input, $output, $question);
            
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();

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
        $query = $helper->ask($input, $output, new Question('<cyan>Ingresa tu búsqueda de plugin:</cyan> '));
        
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
        $slug = $helper->ask($input, $output, new Question('<cyan>Ingresa el slug del plugin:</cyan> '));
        
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
        $query = $helper->ask($input, $output, new Question('<cyan>Ingresa tu búsqueda de tema:</cyan> '));
        
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
        $slug = $helper->ask($input, $output, new Question('<cyan>Ingresa el slug del tema:</cyan> '));
        
        if (empty($slug)) {
            $output->writeln('<error>❌ Slug vacío</error>');
            return Command::FAILURE;
        }
        
        $command = $this->getApplication()->find('theme:info');
        $arrayInput = new ArrayInput(['slug' => $slug]);
        return $command->run($arrayInput, $output);
    }
}
