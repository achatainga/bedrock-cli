<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

class CliRunnerService
{
    private ExecutableFinder $executableFinder;
    private ErrorLoggerService $errorLogger;
    
    public function __construct(ErrorLoggerService $errorLogger)
    {
        $this->executableFinder = new ExecutableFinder();
        $this->errorLogger = $errorLogger;
    }
    
    public function runCommand(string $executable, array $arguments = [], string $workingDirectory = null, bool $silent = false): ?string
    {
        $executablePath = $this->executableFinder->find($executable);
        
        if (!$executablePath) {
            if (!$silent) {
                $this->errorLogger->logPathError(__METHOD__, $executable, "Executable not found in PATH");
            }
            return null;
        }
        
        try {
            $process = new Process(array_merge([$executablePath], $arguments), $workingDirectory);
            $process->setTimeout(5);
            
            if ($silent) {
                $process->disableOutput();
            }
            
            $process->run();
            
            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
            
            if (!$silent) {
                $this->errorLogger->logPathError(__METHOD__, $executable, $process->getErrorOutput());
            }
            return null;
        } catch (\Exception $e) {
            if (!$silent) {
                $this->errorLogger->logPathError(__METHOD__, $executable, $e->getMessage());
            }
            return null;
        }
    }
    
    public function isExecutableAvailable(string $executable): bool
    {
        return $this->executableFinder->find($executable) !== null;
    }
    
    public function getExecutablePath(string $executable): ?string
    {
        return $this->executableFinder->find($executable);
    }
}