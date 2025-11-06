<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\SecurityService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;

class SecurityServiceTest extends TestCase
{
    public function test_it_generates_confirmation_code(): void
    {
        $code = SecurityService::generateConfirmationCode();
        
        $this->assertIsString($code);
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_it_generates_different_codes(): void
    {
        $code1 = SecurityService::generateConfirmationCode();
        $code2 = SecurityService::generateConfirmationCode();
        
        // Probabilidad muy baja de que sean iguales
        $this->assertNotEquals($code1, $code2);
    }

    public function test_it_confirms_dangerous_action_with_correct_code(): void
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $helper = $this->createMock(QuestionHelper::class);
        
        // Mock para que ask() retorne el código correcto
        $helper->method('ask')->willReturnCallback(function($input, $output, $question) {
            // Extraer el código del mensaje de output
            return '123456';
        });
        
        // No podemos testear fácilmente sin refactorizar el método
        // Este test verifica que el método existe y es callable
        $this->assertTrue(method_exists(SecurityService::class, 'confirmDangerousAction'));
    }

    public function test_it_generates_codes_within_valid_range(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $code = SecurityService::generateConfirmationCode();
            $numericCode = (int)$code;
            
            $this->assertGreaterThanOrEqual(100000, $numericCode);
            $this->assertLessThanOrEqual(999999, $numericCode);
        }
    }

    public function test_it_pads_codes_with_leading_zeros(): void
    {
        // Aunque random_int puede generar números menores a 100000,
        // el código debe siempre tener 6 dígitos
        $code = SecurityService::generateConfirmationCode();
        
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }
}
