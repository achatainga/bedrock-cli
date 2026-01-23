<?php

namespace Roots\BedrockCli\Traits;

use Roots\BedrockCli\Services\DockerComposeDetector;

trait DockerComposeTrait
{
    protected function getDockerComposeCommand(): string
    {
        return DockerComposeDetector::getCommand();
    }

    protected function dockerComposeExec(string $args, int &$exitCode = null): void
    {
        $command = $this->getDockerComposeCommand() . ' ' . $args;
        passthru($command, $exitCode);
    }
}