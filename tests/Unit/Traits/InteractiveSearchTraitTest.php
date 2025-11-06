<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Traits\InteractiveSearchTrait;
use Symfony\Component\Console\Output\BufferedOutput;

class InteractiveSearchTraitTest extends TestCase
{
    use InteractiveSearchTrait;

    public function test_it_displays_plugins_table(): void
    {
        $output = new BufferedOutput();
        
        $plugins = [
            ['name' => 'Plugin 1', 'slug' => 'plugin-1', 'active_installs' => 1000000],
            ['name' => 'Plugin 2', 'slug' => 'plugin-2', 'active_installs' => 500000]
        ];

        $this->displayPluginsTable($plugins, $output);

        $display = $output->fetch();
        $this->assertStringContainsString('Plugin 1', $display);
        $this->assertStringContainsString('plugin-1', $display);
        $this->assertStringContainsString('1,000,000', $display);
    }

    public function test_it_displays_themes_table(): void
    {
        $output = new BufferedOutput();
        
        $themes = [
            ['name' => 'Theme 1', 'slug' => 'theme-1', 'rating' => 95],
            ['name' => 'Theme 2', 'slug' => 'theme-2', 'rating' => 88]
        ];

        $this->displayThemesTable($themes, $output);

        $display = $output->fetch();
        $this->assertStringContainsString('Theme 1', $display);
        $this->assertStringContainsString('theme-1', $display);
        $this->assertStringContainsString('95%', $display);
    }

    public function test_it_handles_empty_plugins_array(): void
    {
        $output = new BufferedOutput();
        
        $this->displayPluginsTable([], $output);

        $display = $output->fetch();
        $this->assertStringContainsString('Nombre', $display);
        $this->assertStringContainsString('Slug', $display);
    }

    public function test_it_handles_plugins_without_active_installs(): void
    {
        $output = new BufferedOutput();
        
        $plugins = [
            ['name' => 'Plugin 1', 'slug' => 'plugin-1']
        ];

        $this->displayPluginsTable($plugins, $output);

        $display = $output->fetch();
        $this->assertStringContainsString('Plugin 1', $display);
        $this->assertStringContainsString('0', $display);
    }
}
