# Dependency Injection Architecture

## Overview
Bedrock CLI implements Symfony DI Container for loose coupling and improved testability.

## Container Configuration
```php
// src/Application.php
private ContainerBuilder $container;

private function configureServices(): void {
    // Core services
    $this->container->register('Roots\BedrockCli\Services\ProfileService');
    $this->container->register('Roots\BedrockCli\Services\ComposerService');
    
    // Management services with dependencies
    $this->container->register('Roots\BedrockCli\Services\Management\ManagementService')
        ->addArgument(new Reference('Roots\BedrockCli\Services\Management\ContextDetector'));
}
```

## Command Pattern
```php
// Before (tight coupling)
class ProfileCreateCommand extends Command {
    public function __construct() {
        $this->profileService = new ProfileService(); // ❌
    }
}

// After (dependency injection)
class ProfileCreateCommand extends Command {
    public function __construct(ProfileService $profileService) {
        $this->profileService = $profileService; // ✅
        parent::__construct();
    }
}
```

## Service Registration
Commands receive services via constructor injection:
```php
new ProfileCreateCommand(
    $this->container->get('Roots\BedrockCli\Services\ProfileService'),
    $this->container->get('Roots\BedrockCli\Services\PremiumCacheService')
)
```

## DTOs Implementation
```php
readonly class Profile {
    public function __construct(
        public string $name,
        public array $plugins,
        public ?Theme $theme = null
    ) {}
    
    public static function fromArray(array $data): self {
        return new self($data['name'], $data['plugins'], $data['theme']);
    }
}
```

## Benefits
- **Testability**: Services can be mocked
- **Maintainability**: Clear dependencies
- **SOLID Principles**: Dependency inversion implemented
- **Scalability**: Easy to add new services

## Refactored Commands (46/78)
- All Profile commands (15)
- Critical System commands (8) 
- Management commands (3)
- Auth commands (3)
- Plugin/Theme commands (8)
- Add/Remove commands (6)
- AI & Cache commands (3)

## Testing Example
```php
public function testProfileCreate() {
    $mockService = $this->createMock(ProfileService::class);
    $command = new ProfileCreateCommand($mockService);
    // Test command logic with mocked service
}
```