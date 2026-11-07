<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Process\Process;

class DockerService
{
    public function isRunning(): bool
    {
        $process = new Process(['docker', 'info']);
        $process->run();
        return $process->isSuccessful();
    }

    public function areContainersUp(): bool
    {
        $process = new Process(['docker-compose', 'ps', '-q']);
        $process->run();
        return !empty(trim($process->getOutput()));
    }

    public function up(bool $build = false): Process
    {
        $cmd = ['docker-compose', 'up', '-d'];
        if ($build) {
            $cmd[] = '--build';
        }
        $process = new Process($cmd);
        $process->setTimeout(300);
        return $process;
    }

    public function down(): Process
    {
        return new Process(['docker-compose', 'down']);
    }

    public function restart(): Process
    {
        return new Process(['docker-compose', 'restart']);
    }

    public function status(): Process
    {
        return new Process(['docker-compose', 'ps']);
    }

    public function exec(string $service, array $command): Process
    {
        return new Process(array_merge(['docker-compose', 'exec', $service], $command));
    }

    public function rebuild(): Process
    {
        // Build servicio web sin caché
        $process = new Process(['docker-compose', 'build', '--no-cache', 'web']);
        $process->setTimeout(600);
        return $process;
    }
    
    public function rebuildAndUp(): Process
    {
        $process = new Process(['docker-compose', 'up', '-d']);
        $process->setTimeout(600);
        return $process;
    }

    public function logs(bool $follow = false): Process
    {
        $cmd = ['docker-compose', 'logs'];
        if ($follow) {
            $cmd[] = '-f';
        } else {
            $cmd[] = '--tail=100'; // Últimas 100 líneas
        }
        return new Process($cmd);
    }
}
