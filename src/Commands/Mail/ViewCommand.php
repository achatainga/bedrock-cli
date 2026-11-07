<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Mail;

use Roots\BedrockCli\Services\MailpitService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ViewCommand extends Command
{
    private MailpitService $mailService;

    public function __construct(?MailpitService $mailService = null)
    {
        parent::__construct();
        $this->mailService = $mailService ?? new MailpitService();
    }

    protected function configure(): void
    {
        $this->setName('mail:view')
            ->setAliases(['mail:show'])
            ->setDescription('Ver contenido detallado de un correo en Mailpit')
            ->addArgument('id', InputArgument::OPTIONAL, 'ID del mensaje en Mailpit (por defecto el último recibido)', null)
            ->addOption('html', null, InputOption::VALUE_NONE, 'Mostrar cuerpo HTML sin procesar')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'URL base de la API de Mailpit', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customUrl = $input->getOption('url');
        if ($customUrl) {
            $this->mailService = new MailpitService($customUrl);
        }

        $id = $input->getArgument('id');

        if (!$id) {
            $latest = $this->mailService->getMessages(1);
            if (empty($latest)) {
                $output->writeln('<error>No hay correos registrados en Mailpit.</error>');
                return Command::FAILURE;
            }
            $id = $latest[0]['ID'];
            $output->writeln('<comment>No se especificó ID. Mostrando el último mensaje recibido (' . $id . ')...</comment>');
        }

        $msg = $this->mailService->getMessage((string) $id);

        if (!$msg) {
            $output->writeln('<error>No se encontró el mensaje con ID: ' . $id . '</error>');
            return Command::FAILURE;
        }

        $from = ($msg['From']['Name'] ?? '') . ' <' . ($msg['From']['Address'] ?? '') . '>';
        $toAddresses = array_map(fn($t) => $t['Address'] ?? '', $msg['To'] ?? []);
        $to = implode(', ', $toAddresses);

        $output->writeln('================================================================================');
        $output->writeln('<info>ID:</info>      ' . ($msg['ID'] ?? $id));
        $output->writeln('<info>Fecha:</info>   ' . ($msg['Date'] ?? '-'));
        $output->writeln('<info>De:</info>      ' . $from);
        $output->writeln('<info>Para:</info>    ' . $to);
        $output->writeln('<info>Asunto:</info>  ' . ($msg['Subject'] ?? '(Sin Asunto)'));
        $output->writeln('================================================================================');

        // Detect activation credentials
        $activation = $this->mailService->extractActivationDetails($msg);
        if ($activation['code'] || $activation['url']) {
            $output->writeln('<question> *** DATOS DE ACTIVACION DETECTADOS *** </question>');
            if ($activation['code']) {
                $output->writeln('<fg=black;bg=yellow;options=bold> CODIGO DE ACTIVACION: ' . $activation['code'] . ' </>');
            }
            if ($activation['url']) {
                $output->writeln('<fg=green;options=bold>ENLACE DE ACTIVACION: ' . $activation['url'] . '</>');
            }
            $output->writeln('--------------------------------------------------------------------------------');
        }

        if ($input->getOption('html')) {
            $output->writeln('<comment>--- CUERPO HTML ---</comment>');
            $output->writeln($msg['HTML'] ?? '(Sin contenido HTML)');
        } else {
            $output->writeln('<comment>--- CUERPO TEXTO ---</comment>');
            $text = trim($msg['Text'] ?? '');
            if (empty($text) && !empty($msg['HTML'])) {
                $text = strip_tags($msg['HTML']);
            }
            $output->writeln($text ?: '(Sin contenido de texto)');
        }

        $output->writeln('================================================================================');
        return Command::SUCCESS;
    }
}
