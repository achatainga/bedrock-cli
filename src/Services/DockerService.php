<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Process\Process;
use Roots\BedrockCli\Traits\DockerComposeTrait;

class DockerService
{
    use DockerComposeTrait;

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
        
        return $this->areDockerContainersRunning();
    }

    public function up(bool $build = false): Process
    {
        $args = 'up -d';
        if ($build) {
            $args .= ' --build';
        }
        $process = $this->dockerComposeProcess($args);
        $process->setTimeout(300);
        return $process;
    }

    public function down(): Process
    {
        return $this->dockerComposeProcess('down');
    }

    public function restart(): Process
    {
        return $this->dockerComposeProcess('restart');
    }

    public function status(): Process
    {
        return $this->dockerComposeProcess('ps');
    }

    public function exec(string $service, array $command): Process
    {
        $args = 'exec ' . $service . ' ' . implode(' ', $command);
        return $this->dockerComposeProcess($args);
    }

    public function rebuild(): Process
    {
        // Build servicio web sin caché
        $process = $this->dockerComposeProcess('build --no-cache web');
        $process->setTimeout(600);
        return $process;
    }
    
    public function rebuildAndUp(): Process
    {
        $process = $this->dockerComposeProcess('up -d');
        $process->setTimeout(600);
        return $process;
    }

    public function logs(bool $follow = false): Process
    {
        $args = 'logs';
        if ($follow) {
            $args .= ' -f';
        } else {
            $args .= ' --tail=100'; // Últimas 100 líneas
        }
        return $this->dockerComposeProcess($args);
    }

    /**
     * Obtiene información de Docker Compose para diagnósticos
     */
    public function getComposeInfo(): array
    {
        return $this->getDockerComposeInfo();
    }
}