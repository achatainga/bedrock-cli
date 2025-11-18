# Bedrock CLI

Universal CLI tool for Roots Bedrock WordPress development.

## Installation
```bash
composer global require achatainga/bedrock-cli
```

## Architecture

### Dependency Injection
Bedrock CLI uses Symfony DI Container for loose coupling:
- **46 commands** use dependency injection
- **22+ services** registered in container
- **4 DTOs** for type safety (Profile, Plugin, Theme, Blueprint)

### Key Features
- Profile-based project management
- Docker integration
- Premium plugin/theme support
- WordPress API integration
- AI-powered assistance

## Usage

### Interactive Menu
```bash
bedrock
```

### Profile Management
```bash
bedrock profile:create myprofile
bedrock profile:apply myprofile
bedrock profile:list
```

### Project Management
```bash
bedrock new myproject
bedrock manage:plugins
bedrock manage:themes
```

### Docker Operations
```bash
bedrock docker --status
bedrock setup
```

## Development

### Architecture
- **Commands**: 78 total (46 with DI, 32 simple)
- **Services**: 22 registered in DI container
- **DTOs**: Type-safe data objects
- **Traits**: Reusable functionality

### Testing
Services are injectable for easy mocking:
```php
$mockService = $this->createMock(ProfileService::class);
$command = new ProfileCreateCommand($mockService);
```

### Documentation
- [DI Architecture](docs/ARCHITECTURE_DI.md)
- [Services Reference](docs/SERVICES_REFERENCE.md)

## Requirements
- PHP 8.0+
- Composer
- Docker (optional)

## License
MIT