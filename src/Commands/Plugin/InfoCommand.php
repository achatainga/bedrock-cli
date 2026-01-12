<?php

namespace Roots\BedrockCli\Commands\Plugin;

use Roots\BedrockCli\Commands\BaseRepoInfoCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class InfoCommand extends BaseRepoInfoCommand
{
    protected function getItemType(): string
    {
        return 'plugin';
    }

    protected function handleProfileAddition(InputInterface $input, OutputInterface $output, string $profileName, string $slug, string $type): int
    {
        $helper = $this->getHelper('question');
        $confirmQuestion = new ConfirmationQuestion("\n<question>¿Agregar este plugin al profile '{$profileName}'? (y/n):</question> ", false);
        
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            return Command::SUCCESS;
        }

        if (!$this->profileService->profileExists($profileName)) {
            $output->writeln("<error>Profile '{$profileName}' no existe.</error>");
            return Command::FAILURE;
        }

        $versionQuestion = new Question("<question>Restricción de versión (default: *): </question>", '*');
        $version = $helper->ask($input, $output, $versionQuestion);

        $profile = $this->profileService->loadProfile($profileName);
        $profile['plugins']['public'][$slug] = $version;
        $this->profileService->saveProfile($profileName, $profile);

        $output->writeln("<info>✓ Plugin agregado al profile '{$profileName}'</info>");

        return Command::SUCCESS;
    }
}
