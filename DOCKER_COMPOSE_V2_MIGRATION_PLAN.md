# Plan: Migración a Docker Compose V2 con Detección Automática

## Contexto
Docker Compose V1 (`docker-compose`) está obsoleto desde 2023. Necesitamos soporte automático para ambas versiones.

| Característica | docker-compose (V1) | docker compose (V2) |
|---|---|---|
| **Nombre** | Docker Compose Standalone | Docker Compose Plugin |
| **Lenguaje** | Python | Go |
| **Instalación** | Binario independiente | Plugin integrado en Docker CLI |
| **Estado** | Obsoleto (EOL 2023) | Versión actual y recomendada |

## Estado Actual
- **Hash actual**: `8f3b6d0`
- **Commits pendientes**: `934b67d`, `16341f6` (mejoras de permisos y Dockerfile)
- **Ocurrencias**: 70+ referencias a `docker-compose` en archivos PHP

## Plan de Implementación

### 1. Actualizar a versión más reciente
```bash
cd /home/achat/code/bedrock-cli
git checkout 16341f6
git checkout -b feature/docker-compose-v2-detection
```

### 2. Crear servicio de detección
**Archivo**: `src/Services/DockerComposeDetector.php`
```php
class DockerComposeDetector
{
    private static ?string $command = null;
    
    public static function getCommand(): string
    {
        if (self::$command === null) {
            // Detectar docker compose (V2) primero
            if (self::commandExists('docker compose version')) {
                self::$command = 'docker compose';
            } elseif (self::commandExists('docker-compose --version')) {
                self::$command = 'docker-compose';
            } else {
                throw new RuntimeException('Docker Compose no encontrado');
            }
        }
        return self::$command;
    }
}
```

### 3. Crear trait para comandos Docker
**Archivo**: `src/Traits/DockerComposeTrait.php`
```php
trait DockerComposeTrait
{
    protected function dockerCompose(array $args = []): Process
    {
        $command = DockerComposeDetector::getCommand();
        return new Process(array_merge(explode(' ', $command), $args));
    }
}
```

### 4. Refactorizar archivos principales
- **DockerService.php**: Usar trait en lugar de `docker-compose` hardcodeado
- **DockerCommand.php**: Actualizar todos los comandos
- **70+ archivos**: Reemplazar llamadas directas

### 5. Archivos a modificar (prioritarios)
```
src/Services/DockerService.php
src/Commands/Docker/DockerCommand.php
src/Commands/Setup/SetupCommand.php
src/Commands/Setup/NewCommand.php
src/Services/StateService.php
src/Services/ProjectDiagnosticService.php
```

### 6. Actualizar mensajes de ayuda
- Mostrar `docker compose` como comando preferido
- Mantener compatibilidad con V1 automáticamente

### 7. Testing y deployment
```bash
# Probar detección
php bin/bedrock doctor

# Commit cambios
git add .
git commit -m "feat: Add Docker Compose V2 auto-detection with V1 fallback"
git push origin feature/docker-compose-v2-detection
```

## Resultado Esperado
✅ **Compatibilidad automática** con Docker Compose V1 y V2  
✅ **Sin breaking changes** en proyectos existentes  
✅ **Detección inteligente** de la versión disponible  
✅ **Mensajes actualizados** mostrando V2 como preferido  

## Notas
- Priorizar V2 en detección (más rápido, mejor soporte)
- Mantener fallback a V1 para compatibilidad
- Cache del comando detectado para performance