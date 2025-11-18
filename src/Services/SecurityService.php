<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class SecurityService
{
    /**
     * Genera un código de confirmación aleatorio de 6 dígitos
     */
    public static function generateConfirmationCode(): string
    {
        return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Solicita confirmación con código aleatorio
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $action Descripción de la acción peligrosa
     * @return bool True si el usuario confirma correctamente
     */
    public static function confirmDangerousAction(
        InputInterface $input,
        OutputInterface $output,
        $helper,
        string $action
    ): bool {
        $code = self::generateConfirmationCode();
        
        $output->writeln('');
        $output->writeln('<fg=red;options=bold>⚠️  ADVERTENCIA: ACCIÓN DESTRUCTIVA ⚠️</>');
        $output->writeln('<fg=yellow>' . $action . '</>');
        $output->writeln('');
        $output->writeln('<fg=cyan>Si estás seguro, escribe el código: </><fg=white;options=bold>' . $code . '</>');
        
        $question = new Question('<fg=yellow>Código: </>', '');
        $userInput = $helper->ask($input, $output, $question);
        
        if ($userInput === $code) {
            $output->writeln('<fg=green>✓ Código correcto. Procediendo...</>');
            $output->writeln('');
            return true;
        }
        
        $output->writeln('<fg=red>✗ Código incorrecto. Operación cancelada.</>');
        $output->writeln('');
        return false;
    }
}
