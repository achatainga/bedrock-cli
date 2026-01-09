<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Process\Process;
use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Enums\ExecutionMode;

class WpCliService
{
    private DockerService $docker;
    private ContextDetector $contextDetector;
    private string $projectPath;

    public function __construct(DockerService $docker, ContextDetector $contextDetector)
    {
        $this->docker = $docker;
        $this->contextDetector = $contextDetector;
        $this->projectPath = getcwd();
    }

    public function exec(array $args): Process
    {
        $mode = $this->contextDetector->detectExecutionMode();

        // Native OR Hybrid mode: use local wp-cli
        if ($mode !== ExecutionMode::DOCKER) {
            // Native/Hybrid mode: use global wp-cli or install it
            $wpPath = trim(shell_exec('which wp 2>/dev/null') ?: '');
            
            if (empty($wpPath)) {
                // Try composer global wp-cli
                $composerHome = getenv('COMPOSER_HOME') ?: (getenv('HOME') . '/.config/composer');
                $wpPath = $composerHome . '/vendor/bin/wp';
                
                if (!file_exists($wpPath)) {
                    throw new \RuntimeException('WP-CLI not found. Install it: composer global require wp-cli/wp-cli-bundle');
                }
            }
            
            return new Process(array_merge([$wpPath], $args), $this->projectPath);
        }

        // Docker Pure mode
        $escapedArgs = array_map('escapeshellarg', $args);
        
        $wpCommand = array_merge(
            ['bash', '-c'],
            ['export PATH=/var/www/html/vendor/bin:$PATH && export MYSQL_TEST_LOGIN_FILE=/var/www/html/.my.cnf && wp ' . implode(' ', $escapedArgs) . ' --allow-root 2>&1']
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

    public function themeDelete(string $theme): Process
    {
        return $this->exec(['theme', 'delete', $theme]);
    }

    public function themeUpdate(string $theme = ''): Process
    {
        return $theme ? $this->exec(['theme', 'update', $theme]) : $this->exec(['theme', 'update', '--all']);
    }

    public function pluginDeactivate(string $plugin): Process
    {
        return $this->exec(['plugin', 'deactivate', $plugin]);
    }

    public function pluginUninstall(string $plugin): Process
    {
        return $this->exec(['plugin', 'uninstall', $plugin, '--deactivate']);
    }

    public function pluginUpdate(string $plugin = ''): Process
    {
        return $plugin ? $this->exec(['plugin', 'update', $plugin]) : $this->exec(['plugin', 'update', '--all']);
    }

    public function pluginInstall(string $plugin): Process
    {
        return $this->exec(['plugin', 'install', $plugin]);
    }

    public function dbSearchReplace(string $search, string $replace): Process
    {
        return $this->exec(['search-replace', $search, $replace, '--all-tables']);
    }

    public function dbQuery(string $query): Process
    {
        return $this->exec(['db', 'query', $query]);
    }

    public function userList(string $role = ''): Process
    {
        return $role ? $this->exec(['user', 'list', '--role=' . $role]) : $this->exec(['user', 'list']);
    }

    public function userCreate(string $username, string $email, string $role = 'subscriber'): Process
    {
        return $this->exec(['user', 'create', $username, $email, '--role=' . $role]);
    }

    public function userUpdate(string $user, string $field, string $value): Process
    {
        return $this->exec(['user', 'update', $user, $field, $value]);
    }

    public function userResetPassword(string $user): Process
    {
        return $this->exec(['user', 'reset-password', $user]);
    }

    public function userDelete(string $user, string $reassign = ''): Process
    {
        $args = ['user', 'delete', $user, '--yes'];
        if ($reassign) {
            $args[] = '--reassign=' . $reassign;
        }
        return $this->exec($args);
    }

    public function coreUpdate(): Process
    {
        return $this->exec(['core', 'update']);
    }

    public function custom(string $command): Process
    {
        // Usar str_getcsv para separar por espacios respetando comillas
        $args = str_getcsv($command, ' ');
        return $this->exec(array_filter($args));
    }
}
