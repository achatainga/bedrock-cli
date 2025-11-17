<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\OutputInterface;

trait SpinnerTrait
{
    protected function runWithLoader(Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        $process->start();
        
        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }
        
        if ($process->isSuccessful()) {
            $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
        } else {
            $output->write("\r<comment>{$message}</comment> <error>✗</error>\n");
        }
    }
}