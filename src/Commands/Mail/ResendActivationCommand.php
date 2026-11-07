<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Commands\Mail;

use Roots\BedrockCli\Services\MailpitService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ResendActivationCommand extends Command
{
    private MailpitService $mailService;

    public function __construct(?MailpitService $mailService = null)
    {
        parent::__construct();
        $this->mailService = $mailService ?? new MailpitService();
    }

    protected function configure(): void
    {
        $this->setName('mail:resend-activation')
            ->setDescription('Disparar el reenvío de activación vía REST API e inspeccionar el correo en Mailpit')
            ->addArgument('email', InputArgument::REQUIRED, 'Correo electrónico del usuario registrado')
            ->addOption('site', null, InputOption::VALUE_OPTIONAL, 'URL del sitio WordPress', 'https://staging.detodo24.com')
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'URL base de la API de Mailpit', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $siteUrl = (string) $input->getOption('site');

        $customUrl = $input->getOption('url');
        if ($customUrl) {
            $this->mailService = new MailpitService($customUrl);
        }

        $output->writeln('<info>Solicitando reenvío de código de activación para: ' . $email . ' en ' . $siteUrl . '</info>');

        $res = $this->mailService->triggerResendActivation($email, $siteUrl);

        if (empty($res['success'])) {
            $msg = $res['message'] ?? 'Error desconocido al solicitar reenvío';
            $output->writeln('<error>' . $msg . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Respuesta de la API:</info> ' . ($res['message'] ?? 'Solicitud procesada con éxito'));
        $output->writeln('<comment>Buscando el correo recién generado en Mailpit...</comment>');

        // Give Mailpit 1 second to index and query
        usleep(1000000);

        $messages = $this->mailService->getMessages(5, $email);

        if (empty($messages)) {
            $output->writeln('<comment>No se localizó un mensaje inmediato para ' . $email . ' en Mailpit. Consulta "bedrock mail:list".</comment>');
            return Command::SUCCESS;
        }

        $latest = $messages[0];
        $fullMsg = $this->mailService->getMessage($latest['ID']) ?? $latest;
        $activation = $this->mailService->extractActivationDetails($fullMsg);

        $output->writeln('================================================================================');
        $output->writeln('<info>Correo recibido:</info> ' . ($latest['Subject'] ?? ''));
        $output->writeln('<info>ID en Mailpit:</info>   ' . $latest['ID']);
        $output->writeln('================================================================================');

        if ($activation['code']) {
            $output->writeln('<fg=black;bg=yellow;options=bold> CODIGO DE ACTIVACION RECIBIDO: ' . $activation['code'] . ' </>');
        }

        if ($activation['url']) {
            $output->writeln('<fg=green;options=bold>ENLACE DE ACTIVACION: ' . $activation['url'] . '</>');
        }

        $output->writeln('================================================================================');
        $output->writeln('<info>¡Prueba de reenvío de correo completada con éxito!</info>');

        return Command::SUCCESS;
    }
}
