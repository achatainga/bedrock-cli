# Bedrock CLI - Testing Suite

## 📋 Descripción

Suite de tests automatizados para bedrock-cli usando PHPUnit 11.5.0.

## 🚀 Ejecución

### Ejecutar todos los tests
```bash
php vendor/phpunit/phpunit/phpunit
```

### Ejecutar solo tests unitarios
```bash
php vendor/phpunit/phpunit/phpunit --testsuite Unit
```

### Ejecutar solo tests de integración
```bash
php vendor/phpunit/phpunit/phpunit --testsuite Integration
```

### Ejecutar un archivo específico
```bash
php vendor/phpunit/phpunit/phpunit tests/Unit/Traits/ProjectSelectorTraitTest.php
```

## 📊 Estructura

```
tests/
├── Unit/                    # Tests unitarios (sin dependencias externas)
│   └── Traits/
│       └── ProjectSelectorTraitTest.php
├── Integration/             # Tests de integración (con dependencias)
│   └── ProjectSelectorIntegrationTest.php
├── bootstrap.php            # Autoloader de Composer
└── README.md               # Este archivo
```

## ✅ Cobertura Actual

### ProjectSelectorTrait (~90%)
- **8 tests unitarios**: isBedrockProject() y ensureBedrockProject()
- **2 tests de integración**: Selección múltiple y cancelación con CommandTester

**Total**: 10 tests, 13 assertions

## 🔧 Configuración

### phpunit.xml
- **stopOnFailure**: true (detiene en primer error)
- **colors**: true (output colorizado)
- **cacheDirectory**: .phpunit.cache/ (en .gitignore)

### Requisitos
- PHP 8.4+
- PHPUnit 11.5.0
- Composer autoloader

## 📝 Escribir Tests

### Test Unitario Básico
```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
}
```

### Test con Mocks
```php
use Symfony\Component\Console\Input\InputInterface;

$input = $this->createMock(InputInterface::class);
$input->method('getOption')->willReturn('value');
```

### Test de Integración
```php
use Symfony\Component\Console\Tester\CommandTester;

$commandTester = new CommandTester($command);
$commandTester->setInputs(['user', 'input']);
$commandTester->execute([]);
$output = $commandTester->getDisplay();
```

## 🎯 Próximos Tests

### Prioridad Alta
- [ ] ComposerService
- [ ] ProfileService
- [ ] StateService

### Prioridad Media
- [ ] InteractiveSearchTrait
- [ ] PremiumAssetsTrait
- [ ] Setup Commands

## 📚 Recursos

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Symfony Console Testing](https://symfony.com/doc/current/console.html#testing-commands)
