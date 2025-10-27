<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Console\Output\OutputInterface;

class ProgressService
{
    public static function showSpinner(OutputInterface $output, string $message, callable $task)
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        $output->write("\n<comment>{$message}</comment> ");
        
        $startTime = microtime(true);
        $result = null;
        $completed = false;
        
        // Ejecutar tarea en "background" simulado
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            // No se puede hacer fork, ejecutar directamente
            $result = $task();
            $output->writeln(' <info>✓</info>');
            return $result;
        } elseif ($pid) {
            // Proceso padre - mostrar spinner
            while (!$completed) {
                $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
                $frameIndex = ($frameIndex + 1) % count($frames);
                usleep(100000); // 100ms
                
                $status = null;
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if ($res == -1 || $res > 0) {
                    $completed = true;
                }
            }
            
            $elapsed = round(microtime(true) - $startTime, 1);
            $output->write("\r<comment>{$message}</comment> <info>✓</info> <fg=gray>({$elapsed}s)</>\n");
            
            return $result;
        } else {
            // Proceso hijo - ejecutar tarea
            $result = $task();
            exit(0);
        }
    }
    
    public static function showProgress(OutputInterface $output, string $message, int $maxSeconds = 60): void
    {
        $output->writeln('');
        $output->writeln("<comment>{$message}</comment>");
        $output->write('<fg=cyan>[');
        
        $barLength = 40;
        $interval = $maxSeconds / $barLength;
        
        for ($i = 0; $i < $barLength; $i++) {
            $output->write('█');
            usleep((int)($interval * 1000000));
        }
        
        $output->writeln(']</> <info>✓</info>');
    }
    
    public static function showWaiting(OutputInterface $output, string $message): void
    {
        $dots = ['   ', '.  ', '.. ', '...'];
        $index = 0;
        
        for ($i = 0; $i < 20; $i++) {
            $output->write("\r<comment>{$message}{$dots[$index]}</comment>");
            $index = ($index + 1) % count($dots);
            usleep(250000); // 250ms
        }
        
        $output->writeln("\r<comment>{$message}</comment> <info>✓</info>");
    }
}
