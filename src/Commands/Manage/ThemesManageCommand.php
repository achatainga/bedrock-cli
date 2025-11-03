<?php

namespace Roots\BedrockCli\Commands\Manage;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\ThemeManager;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Roots\BedrockCli\Services\WordPressApiService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\Table;

class ThemesManageCommand extends Command
{
    private ContextDetector $contextDetector;
    private ManagementService $management;
    private ThemeManager $themeManager;
    private DependencyManager $dependencyManager;
    private WordPressApiService $wpApi;

    public function __construct()
    {
        parent::__construct();
        $this->contextDetector = new ContextDetector();
        $this->management = new ManagementService($this->contextDetector);
        $this->themeManager = new ThemeManager($this->management);
        $this->dependencyManager = new DependencyManager($this->management);
        $this->wpApi = new WordPressApiService();
    }

    protected function configure(): void
    {
        $this->setName('manage:themes')
             ->setDescription('Gestión de themes del proyecto');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->management->requireBedrockProject();
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        while (true) {
            $result = $this->showMenu($input, $output);
            if ($result === 'exit') {
                return Command::SUCCESS;
            }
        }
    }

    private function showMenu(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        $themes = $this->themeManager->list();

        $output->writeln('');
        $output->writeln('<fg=magenta;options=bold>╔════════════════════════════════════════╗</>');
        $output->writeln('<fg=magenta;options=bold>║</>   <fg=yellow;options=bold>🎨 GESTIÓN DE THEMES</><fg=magenta;options=bold>              ║</>');
        $output->writeln('<fg=magenta;options=bold>╚════════════════════════════════════════╝</>');
        $output->writeln('');

        if (!empty($themes)) {
            $output->writeln("<fg=cyan>Themes instalados (" . count($themes) . "):</>");
            $output->writeln('');
            
            $table = new Table($output);
            $table->setHeaders(['<fg=cyan>Slug</>', '<fg=cyan>Versión</>']);
            foreach ($themes as $theme) {
                $table->addRow([$theme['slug'], $theme['version']]);
            }
            $table->render();
            $output->writeln('');
        } else {
            $output->writeln('<comment>No hay themes instalados</comment>');
            $output->writeln('');
        }

        $output->writeln('<fg=cyan>¿Qué deseas hacer?</>');
        $output->writeln('');
        $output->writeln(' <fg=cyan>[1]</> 🔍 Buscar e instalar theme');
        $output->writeln(' <fg=cyan>[2]</> 🗑️  Desinstalar theme');
        $output->writeln(' <fg=cyan>[3]</> 📊 Ver detalles de theme');
        $output->writeln(' <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');

        $question = new Question('<fg=yellow>Opción [0-3]: </>', '0');
        $choice = $helper->ask($input, $output, $question);

        switch ($choice) {
            case '1':
                $this->searchAndInstall($input, $output);
                return 'continue';
            case '2':
                $this->uninstall($input, $output, $themes);
                return 'continue';
            case '3':
                $this->showDetails($input, $output, $themes);
                return 'continue';
            case '0':
                return 'exit';
            default:
                $output->writeln('<error>Opción inválida</error>');
                $this->waitForEnter($input, $output);
                return 'continue';
        }
    }

    private function searchAndInstall(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        
        $output->writeln('');
        $question = new Question('<fg=yellow>Buscar theme:</> ');
        $query = $helper->ask($input, $output, $question);

        if (!$query) {
            return;
        }

        $output->writeln('<info>Buscando...</info>');
        $response = $this->wpApi->searchThemes($query);
        $themes = $response['themes'] ?? [];

        if (empty($themes)) {
            $output->writeln('<comment>No se encontraron themes</comment>');
            $this->waitForEnter($input, $output);
            return;
        }

        $output->writeln('');
        $output->writeln('<comment>Resultados:</comment>');
        $output->writeln('');
        
        $colors = ['cyan', 'green', 'yellow', 'blue', 'magenta', 'red', 'white', 'gray', 'bright-cyan', 'bright-green'];
        
        $table = new Table($output);
        $table->setHeaders(['#', 'Nombre', 'Slug', 'Rating']);
        $table->setColumnMaxWidth(1, 40); // Limitar ancho de nombre
        
        foreach (array_slice($themes, 0, 10) as $index => $theme) {
            $color = $colors[$index % count($colors)];
            $num = $index + 1;
            $name = $theme['name'] ?? 'N/A';
            $slug = $theme['slug'] ?? 'N/A';
            $rating = ($theme['rating'] ?? 0) . '/100';
            
            $table->addRow([
                "<fg={$color}>{$num}</>",
                wordwrap($name, 40, "\n", true),
                "<fg={$color}>{$slug}</>",
                $rating
            ]);
        }
        
        $table->render();
        
        $output->writeln('');
        $question = new Question('<fg=yellow>Seleccionar número (o Enter para cancelar):</> ');
        $selection = $helper->ask($input, $output, $question);
        
        if (empty($selection)) {
            return;
        }
        
        $index = (int)$selection - 1;
        if (!isset($themes[$index])) {
            $output->writeln('<error>Selección inválida</error>');
            $this->waitForEnter($input, $output);
            return;
        }
        
        $slug = $themes[$index]['slug'];
        
        $output->writeln('');
        $output->writeln("<info>Instalando theme: {$slug}</info>");
        
        $this->themeManager->add($slug);
        
        $exitCode = $this->dependencyManager->require(
            "wpackagist-theme/{$slug}",
            null,
            false,
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode === 0) {
            $output->writeln('');
            $output->writeln('<info>✓ Theme instalado correctamente</info>');
        } else {
            $output->writeln('');
            $output->writeln('<error>✗ Error al instalar theme</error>');
        }

        $this->waitForEnter($input, $output);
    }

    private function uninstall(InputInterface $input, OutputInterface $output, array $themes): void
    {
        if (empty($themes)) {
            $output->writeln('<comment>No hay themes para desinstalar</comment>');
            $this->waitForEnter($input, $output);
            return;
        }

        $helper = $this->getHelper('question');
        $choices = [];
        
        foreach ($themes as $theme) {
            $choices[$theme['slug']] = "{$theme['slug']} ({$theme['version']})";
        }
        $choices['cancel'] = 'Cancelar';

        $question = new ChoiceQuestion('Selecciona theme a desinstalar:', $choices, 'cancel');
        $selected = $helper->ask($input, $output, $question);

        if ($selected === 'Cancelar' || $selected === 'cancel') {
            return;
        }

        $slug = array_search($selected, $choices);
        if ($slug === 'cancel' || !$slug) {
            return;
        }
        
        $output->writeln('');
        $output->writeln("<info>Desinstalando theme: {$slug}</info>");
        
        $this->themeManager->remove($slug);
        
        $exitCode = $this->dependencyManager->update(
            ["wpackagist-theme/{$slug}"],
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode === 0) {
            $output->writeln('');
            $output->writeln('<info>✓ Theme desinstalado correctamente</info>');
        } else {
            $output->writeln('');
            $output->writeln('<error>✗ Error al desinstalar theme</error>');
        }

        $this->waitForEnter($input, $output);
    }

    private function showDetails(InputInterface $input, OutputInterface $output, array $themes): void
    {
        if (empty($themes)) {
            $output->writeln('<comment>No hay themes instalados</comment>');
            $this->waitForEnter($input, $output);
            return;
        }

        $helper = $this->getHelper('question');
        $choices = [];
        
        foreach ($themes as $theme) {
            $choices[$theme['slug']] = $theme['slug'];
        }
        $choices['cancel'] = 'Cancelar';

        $question = new ChoiceQuestion('Selecciona theme:', $choices, 'cancel');
        $selected = $helper->ask($input, $output, $question);

        if ($selected === 'Cancelar') {
            return;
        }

        $slug = array_search($selected, $choices);
        $info = $this->wpApi->getThemeInfo($slug);

        if (!$info) {
            $output->writeln('<error>No se pudo obtener información del theme</error>');
            $this->waitForEnter($input, $output);
            return;
        }

        $output->writeln('');
        $output->writeln("<fg=yellow;options=bold>{$info['name']}</>");
        $output->writeln('');
        $output->writeln("<info>Slug:</info> {$info['slug']}");
        $output->writeln("<info>Versión:</info> {$info['version']}");
        $output->writeln("<info>Autor:</info> {$info['author']}");
        $output->writeln("<info>Rating:</info> {$info['rating']}/100");
        $output->writeln('');
        $output->writeln("<info>Descripción:</info>");
        $output->writeln(wordwrap(strip_tags($info['description']), 70));

        $this->waitForEnter($input, $output);
    }

    private function waitForEnter(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('');
        $output->write('<comment>Presiona Enter para continuar...</comment>');
        if ($input->isInteractive()) {
            fgets(STDIN);
        }
    }
}
