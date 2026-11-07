<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Mail;

use Roots\BedrockCli\Services\MailpitService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCommand extends Command
{
    private MailpitService $mailService;

    public function __construct(?MailpitService $mailService = null)
    {
        parent::__construct();
        $this->mailService = $mailService ?? new MailpitService();
    }

    protected function configure(): void
    {
        $this->setName('mail:clear')
            ->setDescription('Vaciar todos los correos en Mailpit')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'URL base de la API de Mailpit', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customUrl = $input->getOption('url');
        if ($customUrl) {
            $this->mailService = new MailpitService($customUrl);
        }

        $output->writeln('<info>Vaciando bandeja de Mailpit en ' . $this->mailService->getBaseUrl() . '...</info>');

        if ($this->mailService->clearMessages()) {
            $output->writeln('<info>Bandeja de Mailpit vaciada con éxito.</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>No se pudo vaciar la bandeja de Mailpit. Verifica que el servicio esté activo.</error>');
        return Command::FAILURE;
    }
}
