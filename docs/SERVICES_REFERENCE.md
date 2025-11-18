# Services Reference

## Core Services

### ProfileService
- **Purpose**: Profile management (CRUD operations)
- **Dependencies**: None
- **Used by**: 15 Profile commands

### ComposerService  
- **Purpose**: Composer operations
- **Dependencies**: None
- **Used by**: NewCommand, ProfileApplyCommand

### DockerService
- **Purpose**: Docker container management
- **Dependencies**: None
- **Used by**: 8 commands (WpCliService depends on this)

### WpCliService
- **Purpose**: WordPress CLI operations
- **Dependencies**: DockerService
- **Used by**: 12 commands

## Management Services

### ContextDetector
- **Purpose**: Detect project context (Bedrock, WordPress)
- **Dependencies**: None
- **Used by**: ManagementService

### ManagementService
- **Purpose**: Project validation and management
- **Dependencies**: ContextDetector
- **Used by**: All management commands

### PluginManager/ThemeManager/DependencyManager
- **Purpose**: Specific asset management
- **Dependencies**: ManagementService
- **Used by**: Respective manage commands

## Specialized Services

### AuthService
- **Purpose**: Authentication management
- **Dependencies**: None
- **Used by**: Auth commands

### PremiumCacheService
- **Purpose**: Premium plugin/theme caching
- **Dependencies**: None
- **Used by**: Cache commands, Profile commands

### AIContextBuilder
- **Purpose**: Build context for AI operations
- **Dependencies**: None
- **Used by**: AICommand

## DTOs

### Profile DTO
```php
readonly class Profile {
    public string $name;
    public array $plugins;
    public ?Theme $theme;
}
```

### Plugin/Theme DTOs
```php
readonly class Plugin {
    public string $slug;
    public string $version;
    public string $source;
}
```

## Service Dependencies Graph
```
ManagementService → ContextDetector
PluginManager → ManagementService → ContextDetector
WpCliService → DockerService
```