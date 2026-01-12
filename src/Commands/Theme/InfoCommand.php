<?php

namespace Roots\BedrockCli\Commands\Theme;

use Roots\BedrockCli\Commands\BaseRepoInfoCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InfoCommand extends BaseRepoInfoCommand
{
    protected function getItemType(): string
    {
        return 'theme';
    }

    protected function handleProfileAddition(InputInterface $input, OutputInterface $output, string $profileName, string $slug, string $type): int
    {
        $helper = $this->getHelper('question');
        $confirmQuestion = new ConfirmationQuestion("\n<question>¿Agregar este tema al profile '{$profileName}'? (y/n):</question> ", false);
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            return Command::SUCCESS;
        }

        if (!$this->profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' no existe.</error>");
            return Command::FAILURE;
        }

        $profile = $this->profileService->loadProfile($profileName);
        $profile['themes']['public'][$slug] = '*';
        $this->profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Tema agregado al profile '{$profileName}'</info>");

        return Command::SUCCESS;
    }
}
