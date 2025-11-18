# Changelog

## [2.0.0] - 2025-11-03

### Added - Sistema Unificado de Gestión

#### Servicios de Gestión (FASE 1)
- `ContextDetector`: Detecta proyecto Bedrock y contexto actual
- `ManagementService`: Lógica común de gestión con patrón transaccional
- `PluginManager`: CRUD de plugins en composer.json
- `ThemeManager`: CRUD de themes en composer.json
- `DependencyManager`: Operaciones Composer completas

#### Menús Interactivos (FASE 2)
- `bedrock manage`: Menú principal de gestión unificada
- `bedrock manage:plugins`: Gestión interactiva de plugins
  - Listar plugins instalados
  - Buscar e instalar desde WordPress.org
  - Desinstalar plugins
  - Ver detalles completos
- `bedrock manage:themes`: Gestión interactiva de themes
  - Listar themes instalados
  - Buscar e instalar desde WordPress.org
  - Desinstalar themes
  - Ver detalles completos
- `bedrock manage:dependencies`: Gestión interactiva de dependencias
  - Listar dependencias (prod + dev)
  - Agregar dependencias
  - Remover dependencias
  - Actualizar dependencias
  - Instalar dependencias

#### Comandos Granulares (FASE 3)
- `bedrock add:plugin <slug>`: Instalar plugin
- `bedrock add:theme <slug>`: Instalar theme
- `bedrock add:dependency <package>`: Agregar dependencia Composer
- `bedrock remove:plugin <slug>`: Desinstalar plugin
- `bedrock remove:theme <slug>`: Desinstalar theme
- `bedrock remove:dependency <package>`: Remover dependencia

#### Profile Edit Mejorado (FASE 4)
- `bedrock profile:add-plugin <profile> <slug>`: Agregar plugin a profile
- `bedrock profile:remove-plugin <profile> <slug>`: Remover plugin de profile
- `bedrock profile:set-theme <profile> <slug>`: Establecer theme de profile
- `bedrock profile:add-repo <profile>`: Agregar repositorio custom a profile

### Features
- Output en tiempo real de Composer
- Búsqueda interactiva en WordPress.org API
- Validaciones completas (proyecto Bedrock, formato, existencia)
- Exit codes correctos para scripts
- Soporte para versiones específicas
- Soporte para dependencias de desarrollo
- Integración completa con servicios existentes

### Documentation
- Actualizado README.md con comandos v2.0
- Creado docs/UNIFIED_MANAGEMENT.md
- Documentación completa de casos de uso
- Ejemplos para scripts y CI/CD

---

## [1.0.0] - 2025-11-03

### Added - Sistema de Profiles, Blueprints y Seeders

#### Sistema de Profiles (8 comandos)
- `bedrock profile:create <name>`: Wizard interactivo con búsqueda de plugins
- `bedrock profile:list`: Listar profiles disponibles
- `bedrock profile:show <name>`: Ver detalles en JSON
- `bedrock profile:edit <name>`: Editar en editor del sistema
- `bedrock profile:delete <name>`: Eliminar con confirmación
- `bedrock profile:export <name>`: Exportar desde proyecto actual
- `bedrock profile:apply <name>`: Aplicar a proyecto existente
- `bedrock profile:menu`: Menú interactivo con detección de contexto

#### Sistema de Blueprints
- Blueprints por ambiente (production, staging, development)
- Generación automática desde profiles
- Seeders específicos por ambiente
- Variables de entorno configurables

#### Sistema de Seeders (6 seeders híbridos)
- `DatabaseSeeder`: Orquestador principal
- `CoreSeeder`: Configuración básica de WordPress
- `WooCommerceSeeder`: Configuración de WooCommerce
- `ThemeSeeder`: Activación y configuración de tema
- `PluginsSeeder`: Activación de plugins
- `ProductsSeeder`: Productos fake con Faker

#### Búsqueda WordPress.org (4 comandos)
- `bedrock plugin:search <query>`: Buscar plugins interactivamente
- `bedrock plugin:info <slug>`: Información detallada de plugin
- `bedrock theme:search <query>`: Buscar themes
- `bedrock theme:info <slug>`: Información detallada de theme

#### Reorganización de Comandos
- 38 comandos reorganizados en 11 carpetas por dominio
- Namespaces actualizados
- Application.php con aliases

### Fixed
- Namespace doble backslash en WordPressApiService
- Imports sin `Roots\` en NewCommand
- Rutas de stubs para instalación global (Windows/Linux)
- Namespace doble backslash en BlueprintService

### Documentation
- README.md actualizado
- docs/PROFILES.md - Guía completa de profiles
- docs/BLUEPRINTS.md - Configuración por ambiente
- docs/SEEDERS.md - Sistema híbrido de seeders

---

## [0.1.0] - Initial Release

### Added
- Comandos básicos de setup
- Integración con Bedrock
- Soporte para Docker
- Database management
- Options management
