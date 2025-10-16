<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Process\Process;

class WpCliService
{
    private DockerService $docker;

    public function __construct(DockerService $docker)
    {
        $this->docker = $docker;
    }

    public function exec(array $args): Process
    {
        $wpCommand = array_merge(
            ['bash', '-c'],
            ['export PATH=/var/www/html/vendor/bin:$PATH && export MYSQL_TEST_LOGIN_FILE=/var/www/html/.my.cnf && wp ' . implode(' ', $args) . ' --allow-root']
        );
        
        return $this->docker->exec('web', $wpCommand);
    }

    public function dbCreate(): Process
    {
        return $this->exec(['db', 'create']);
    }

    public function dbDrop(): Process
    {
        return $this->exec(['db', 'drop', '--yes']);
    }

    public function dbReset(): Process
    {
        return $this->exec(['db', 'reset', '--yes']);
    }

    public function dbImport(string $file): Process
    {
        return $this->exec(['db', 'import', $file]);
    }

    public function dbExport(string $file): Process
    {
        return $this->exec(['db', 'export', $file]);
    }

    public function coreInstall(array $options): Process
    {
        $args = ['core', 'install'];
        foreach ($options as $key => $value) {
            $args[] = "--{$key}={$value}";
        }
        $args[] = '--skip-email';
        return $this->exec($args);
    }

    public function pluginList(): Process
    {
        return $this->exec(['plugin', 'list']);
    }

    public function pluginActivate(string $plugin): Process
    {
        return $this->exec(['plugin', 'activate', $plugin]);
    }

    public function themeList(): Process
    {
        return $this->exec(['theme', 'list']);
    }

    public function themeActivate(string $theme): Process
    {
        return $this->exec(['theme', 'activate', $theme]);
    }
}
