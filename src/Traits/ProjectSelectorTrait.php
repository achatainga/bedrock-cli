<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait ProjectSelectorTrait
{
    protected function ensureBedrockProject(InputInterface $input, OutputInterface $output): ?string
    {
        if ($this->isBedrockProject(getcwd())) {
            return getcwd();
        }

        $output->writeln('<comment>Buscando proyectos Bedrock en subdirectorios...</comment>');
        $output->writeln('');
        
        $subdirs = glob(getcwd() . '/*', GLOB_ONLYDIR);
        $projects = [];

        foreach ($subdirs as $dir) {
            if ($this->isBedrockProject($dir)) {
                $projects[] = $dir;
            }
        }

        if (empty($projects)) {
            $output->writeln('<error>No se encontró ningún proyecto Bedrock</error>');
            return null;
        }

        if (count($projects) === 1) {
            $projectPath = $projects[0];
            $output->writeln("<info>Proyecto detectado: " . basename($projectPath) . "</info>");
            $output->writeln('');
            chdir($projectPath);
            return $projectPath;
        }

        $output->writeln('<fg=cyan>Proyectos Bedrock encontrados:</>');;
        foreach ($projects as $idx => $project) {
            $name = basename($project);
            $output->writeln("  <fg=cyan>[" . ($idx + 1) . "]</> {$name}");
        }
        $output->writeln('  <fg=cyan>[0]</> Cancelar');
        $output->writeln('');

        $helper = $this->getHelper('question');
        $question = new Question('<fg=yellow>Seleccionar proyecto [1]:</> ', '1');
        $choice = $helper->ask($input, $output, $question);

        if ($choice === '0') {
            return null;
        }

        $index = (int)$choice - 1;
        $projectPath = $projects[$index] ?? null;

        if ($projectPath) {
            $output->writeln("<info>Proyecto seleccionado: " . basename($projectPath) . "</info>");
            $output->writeln('');
            chdir($projectPath);
        }

        return $projectPath;
    }

    protected function isBedrockProject(string $path): bool
    {
        $composerFile = $path . '/composer.json';

        if (!file_exists($composerFile)) {
            return false;
        }

        $composer = json_decode(file_get_contents($composerFile), true);

        return isset($composer['require']['roots/bedrock']) ||
               isset($composer['require']['roots/wordpress']) ||
               (isset($composer['extra']['installer-paths']) &&
                isset($composer['extra']['installer-paths']['web/app/mu-plugins/{$name}/']));
    }
}
