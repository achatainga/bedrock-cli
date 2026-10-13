<?php

declare(strict_types=1);

namespace Tests\Unit\Services\VirtualAssistant;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\VirtualAssistant\ChatWidgetGenerator;

class ChatWidgetGeneratorTest extends TestCase
{
    public function test_it_generates_valid_widget_markup(): void
    {
        $generator = new ChatWidgetGenerator();
        $html = $generator->generateWidgetScript('/wp-json/bedrock/v1/assistant', 'Enterprise Assistant');

        $this->assertStringContainsString('id="bedrock-chat-root"', $html);
        $this->assertStringContainsString('Enterprise Assistant', $html);
        $this->assertStringContainsString('/wp-json/bedrock/v1/assistant', $html);
        $this->assertStringContainsString('<script>', $html);
    }
}
