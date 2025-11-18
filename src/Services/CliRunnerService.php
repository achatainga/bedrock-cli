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
    
    public function runCommand(string $executable, array $arguments = [], string $workingDirectory = null): ?string
    {
        $executablePath = $this->executableFinder->find($executable);
        
        if (!$executablePath) {
            $this->errorLogger->logPathError(__METHOD__, $executable, "Executable not found in PATH");
            return null;
        }
        
        try {
            $process = new Process(array_merge([$executablePath], $arguments), $workingDirectory);
            $process->run();
            
            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
            
            $this->errorLogger->logPathError(__METHOD__, $executable, $process->getErrorOutput());
            return null;
        } catch (\Exception $e) {
            $this->errorLogger->logPathError(__METHOD__, $executable, $e->getMessage());
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