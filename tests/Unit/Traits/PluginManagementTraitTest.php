<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Traits\PluginManagementTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginManagementTraitTest extends TestCase
{
    use PluginManagementTrait;

    public function testValidateNoDuplicatePluginDetectsPublicDuplicate(): void
    {
        $profile = [
            'plugins' => [
                'public' => [
                    ['slug' => 'elementor', 'version' => '3.32.5']
                ],
                'premium' => [],
                'custom' => []
            ]
        ];

        $result = $this->validateNoDuplicatePlugin($profile, 'elementor', 'premium');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('elementor', $result['message']);
        $this->assertContains('public', $result['sections']);
    }

    public function testValidateNoDuplicatePluginDetectsPremiumDuplicate(): void
    {
        $profile = [
            'plugins' => [
                'public' => [],
                'premium' => [
                    ['name' => 'elementor-pro', 'version' => '3.32.2']
                ],
                'custom' => []
            ]
        ];

        $result = $this->validateNoDuplicatePlugin($profile, 'elementor-pro', 'custom');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('elementor-pro', $result['message']);
        $this->assertContains('premium', $result['sections']);
    }

    public function testValidateNoDuplicatePluginDetectsCustomDuplicate(): void
    {
        $profile = [
            'plugins' => [
                'public' => [],
                'premium' => [],
                'custom' => ['dt24-cancel-insights']
            ]
        ];

        $result = $this->validateNoDuplicatePlugin($profile, 'dt24-cancel-insights', 'premium');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('dt24-cancel-insights', $result['message']);
        $this->assertContains('custom', $result['sections']);
    }

    public function testValidateNoDuplicatePluginAllowsSameSection(): void
    {
        $profile = [
            'plugins' => [
                'public' => [
                    ['slug' => 'elementor', 'version' => '3.32.5']
                ],
                'premium' => [],
                'custom' => []
            ]
        ];

        $result = $this->validateNoDuplicatePlugin($profile, 'elementor', 'public');

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['sections']);
    }

    public function testValidateNoDuplicatePluginAllowsNewPlugin(): void
    {
        $profile = [
            'plugins' => [
                'public' => [],
                'premium' => [],
                'custom' => []
            ]
        ];

        $result = $this->validateNoDuplicatePlugin($profile, 'new-plugin', 'public');

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['sections']);
    }

    protected function addNewPlugin(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        // Implementación dummy para satisfacer el trait
    }
    
    protected function getProfileService()
    {
        // Implementación dummy para satisfacer el trait
        return null;
    }
}
