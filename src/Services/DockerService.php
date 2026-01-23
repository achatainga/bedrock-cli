<?php

namespace Roots\BedrockCli\Services;

use BedrockCli\Services\DockerComposeDetector;
use Symfony\Component\Process\Process;

class DockerService
{
    private function getDockerComposeCommand(): array
    {
        return explode(' ', DockerComposeDetector::getCommand());
    }
    public function isRunning(): bool
    {
        $process = new Process(['docker', 'info']);
        $process->run();
        return $process->isSuccessful();
    }

    public function areContainersUp(): bool
    {
        // Check if docker-compose.yml exists first
        if (!file_exists(getcwd() . '/docker-compose.yml')) {
            return false;
        }
        
        $cmd = array_merge($this->getDockerComposeCommand(), ['ps', '-q']);
        $process = new Process($cmd);
        $process->run();
        return !empty(trim($process->getOutput()));
    }

    public function up(bool $build = false): Process
    {
        $cmd = array_merge($this->getDockerComposeCommand(), ['up', '-d']);
        if ($build) {
            $cmd[] = '--build';
        }
        $process = new Process($cmd);
        $process->setTimeout(300);
        return $process;
    }

    public function down(): Process
    {
        return new Process(array_merge($this->getDockerComposeCommand(), ['down']));
    }

    public function restart(): Process
    {
        return new Process(array_merge($this->getDockerComposeCommand(), ['restart']));
    }

    public function status(): Process
    {
        return new Process(array_merge($this->getDockerComposeCommand(), ['ps']));
    }

    public function exec(string $service, array $command): Process
    {
        return new Process(array_merge($this->getDockerComposeCommand(), ['exec', $service], $command));
    }

    public function rebuild(): Process
    {
        // Build servicio web sin caché
        $process = new Process(array_merge($this->getDockerComposeCommand(), ['build', '--no-cache', 'web']));
        $process->setTimeout(600);
        return $process;
    }
    
    public function rebuildAndUp(): Process
    {
        $process = new Process(array_merge($this->getDockerComposeCommand(), ['up', '-d']));
        $process->setTimeout(600);
        return $process;
    }

    public function logs(bool $follow = false): Process
    {
        $cmd = array_merge($this->getDockerComposeCommand(), ['logs']);
        if ($follow) {
            $cmd[] = '-f';
        } else {
            $cmd[] = '--tail=100'; // Últimas 100 líneas
        }
        return new Process($cmd);
    }
}
