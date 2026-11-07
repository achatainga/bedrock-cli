<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Blueprints;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\Blueprints\VerticalRegistry;

class VerticalRegistryTest extends TestCase
{
    private VerticalRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new VerticalRegistry();
    }

    public function test_it_loads_exactly_50_verticals(): void
    {
        $this->assertSame(50, $this->registry->count());
    }

    public function test_it_contains_all_5_macro_sectors(): void
    {
        $sectors = $this->registry->getSectors();
        $this->assertCount(5, $sectors);
        $this->assertArrayHasKey('regulated', $sectors);
        $this->assertArrayHasKey('professional_services', $sectors);
        $this->assertArrayHasKey('ecommerce_logistics', $sectors);
        $this->assertArrayHasKey('media_creators', $sectors);
        $this->assertArrayHasKey('consumer_hospitality', $sectors);
    }

    public function test_it_finds_specific_verticals(): void
    {
        $this->assertTrue($this->registry->hasVertical('healthcare-provider'));
        $this->assertTrue($this->registry->hasVertical('saas-b2b'));
        $this->assertTrue($this->registry->hasVertical('ecommerce-brand'));
        $this->assertFalse($this->registry->hasVertical('non-existent-vertical'));
    }

    public function test_it_synthesizes_valid_bedrock_profile(): void
    {
        $profile = $this->registry->synthesizeProfile('saas-b2b', 'my-saas');

        $this->assertSame('vertical-saas-b2b', $profile['name']);
        $this->assertSame('saas-b2b', $profile['vertical_id']);
        $this->assertArrayHasKey('admin_stack', $profile);
        $this->assertArrayHasKey('virtual_assistant', $profile);
        $this->assertArrayHasKey('devops', $profile);
        $this->assertArrayHasKey('blueprints', $profile);
        $this->assertContains('interactive-docs-bot', $profile['virtual_assistant']['capabilities']);
    }

    public function test_it_filters_verticals_by_sector(): void
    {
        $regulated = $this->registry->getVerticalsBySector('regulated');
        $this->assertCount(10, $regulated);
        $this->assertArrayHasKey('healthcare-provider', $regulated);
        $this->assertArrayHasKey('financial-advisor', $regulated);
    }

    public function test_it_normalizes_plugin_slugs_to_agnostic_prefix(): void
    {
        $this->assertSame('core-hipaa-audit', $this->registry->normalizePlugin('dt24-hipaa-audit'));
        $this->assertSame('core-vault', $this->registry->normalizePlugin('core-vault'));
        $this->assertSame('woocommerce', $this->registry->normalizePlugin('woocommerce'));

        $this->registry->setPluginPrefix('plugin-');
        $this->assertSame('plugin-hipaa-audit', $this->registry->normalizePlugin('dt24-hipaa-audit'));
        $this->assertSame('plugin-vault', $this->registry->normalizePlugin('core-vault'));
    }

    public function test_it_synthesizes_profile_with_agnostic_plugins(): void
    {
        $profile = $this->registry->synthesizeProfile('healthcare-provider', 'my-clinic');
        $customPlugins = $profile['plugins']['custom'];

        foreach ($customPlugins as $plugin) {
            $this->assertStringStartsNotWith('dt24-', $plugin, "Plugin {$plugin} must not contain dt24 branding");
        }
    }
}
