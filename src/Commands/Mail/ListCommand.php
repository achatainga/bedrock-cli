<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Mail;

use Roots\BedrockCli\Services\MailpitService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{
    private MailpitService $mailService;

    public function __construct(?MailpitService $mailService = null)
    {
        parent::__construct();
        $this->mailService = $mailService ?? new MailpitService();
    }

    protected function configure(): void
    {
        $this->setName('mail:list')
            ->setAliases(['mail'])
            ->setDescription('Listar correos capturados en Mailpit (Staging/Dev)')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Número máximo de mensajes a listar', '15')
            ->addOption('search', 's', InputOption::VALUE_OPTIONAL, 'Filtrar mensajes por texto o remitente', '')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'URL base de la API de Mailpit', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customUrl = $input->getOption('url');
        if ($customUrl) {
            $this->mailService = new MailpitService($customUrl);
        }

        $limit = (int) $input->getOption('limit');
        $query = (string) $input->getOption('search');

        $output->writeln('<info>Conectando a Mailpit en ' . $this->mailService->getBaseUrl() . '...</info>');

        $messages = $this->mailService->getMessages($limit, $query);

        if (empty($messages)) {
            $output->writeln('<comment>No se encontraron correos en la bandeja de Mailpit.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Fecha / Hora', 'De', 'Para', 'Asunto', 'Snippet']);

        foreach ($messages as $msg) {
            $from = ($msg['From']['Name'] ?? '') . ' <' . ($msg['From']['Address'] ?? '') . '>';
            $toAddresses = array_map(fn($t) => $t['Address'] ?? '', $msg['To'] ?? []);
            $to = implode(', ', $toAddresses);

            $date = isset($msg['Created']) ? date('Y-m-d H:i:s', strtotime($msg['Created'])) : '-';
            $snippet = mb_strimwidth($msg['Snippet'] ?? '', 0, 45, '...');

            $table->addRow([
                $msg['ID'] ?? '',
                $date,
                mb_strimwidth($from, 0, 25, '...'),
                mb_strimwidth($to, 0, 25, '...'),
                mb_strimwidth($msg['Subject'] ?? '(Sin Asunto)', 0, 35, '...'),
                $snippet,
            ]);
        }

        $table->render();
        $output->writeln('<info>Total listados: ' . count($messages) . '</info>');
        $output->writeln('<comment>Para ver el detalle de un correo ejecuta: bedrock mail:view <ID></comment>');

        return Command::SUCCESS;
    }
}
