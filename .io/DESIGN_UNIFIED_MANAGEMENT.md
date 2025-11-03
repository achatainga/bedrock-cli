# DISEÑO: SISTEMA UNIFICADO DE GESTIÓN

**Fecha**: 2025-11-03 10:09  
**Versión**: 1.0  
**Estado**: Propuesta Aprobada  
**Implementación**: Post v1.0

---

## 📋 ÍNDICE

1. [Visión General](#visión-general)
2. [Problema Actual](#problema-actual)
3. [Solución Propuesta](#solución-propuesta)
4. [Arquitectura](#arquitectura)
5. [Especificaciones de Comandos](#especificaciones-de-comandos)
6. [Plan de Implementación](#plan-de-implementación)
7. [Ejemplos de Uso](#ejemplos-de-uso)

---

## 🎯 VISIÓN GENERAL

Crear un **sistema unificado de gestión** que permita administrar proyectos Bedrock de forma intuitiva mediante:

1. **Menús interactivos** (usuarios normales)
2. **Comandos directos** (usuarios avanzados)
3. **Comandos granulares** (scripts/CI/CD)

**Principio**: Misma lógica funciona para **profiles** (plantillas) y **proyectos** (instancias activas).

---

## 🔴 PROBLEMA ACTUAL

### Fragmentación de Comandos

```bash
# Usuario debe recordar 50+ comandos diferentes
bedrock plugins:list
bedrock plugins:activate woocommerce
bedrock plugins:deactivate contact-form-7
bedrock themes:list
bedrock themes:activate storefront
bedrock options:pull
bedrock options:push
bedrock db:clean
bedrock db:snapshot
# ... etc
```

### Problemas:
- ❌ Difícil de recordar
- ❌ No hay flujo guiado
- ❌ Curva de aprendizaje alta
- ❌ Lógica duplicada entre comandos
- ❌ No hay gestión unificada

---

## ✅ SOLUCIÓN PROPUESTA

### Sistema de 3 Capas

```
┌─────────────────────────────────────────┐
│  CAPA 1: Menú Interactivo               │
│  bedrock manage                          │
│  → Guía al usuario paso a paso          │
└─────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────┐
│  CAPA 2: Comandos Directos               │
│  bedrock plugins:manage                  │
│  → Acceso directo a secciones           │
└─────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────┐
│  CAPA 3: Comandos Granulares             │
│  bedrock add:plugin woocommerce          │
│  → Automatización y scripts              │
└─────────────────────────────────────────┘
```

---

## 🏗️ ARQUITECTURA

### Servicios Reutilizables

```
src/Services/
├── Management/
│   ├── ManagementService.php       # Lógica común de gestión
│   ├── ContextDetector.php         # Detecta profile vs proyecto
│   ├── PluginManager.php           # CRUD de plugins
│   ├── ThemeManager.php            # CRUD de themes
│   ├── DependencyManager.php       # Operaciones Composer
│   ├── BlueprintManager.php        # Gestión de blueprints
│   └── OptionManager.php           # Gestión de opciones WP
```

### Comandos Organizados

```
src/Commands/
├── Manage/
│   ├── ManageCommand.php           # Menú principal
│   ├── PluginsManageCommand.php    # Submenú plugins
│   ├── ThemesManageCommand.php     # Submenú themes
│   ├── DependenciesManageCommand.php
│   ├── BlueprintsManageCommand.php
│   ├── OptionsManageCommand.php
│   └── DatabaseManageCommand.php
├── Add/
│   ├── AddPluginCommand.php        # bedrock add:plugin
│   ├── AddThemeCommand.php         # bedrock add:theme
│   └── AddDependencyCommand.php    # bedrock add:dependency
└── Remove/
    ├── RemovePluginCommand.php     # bedrock remove:plugin
    ├── RemoveThemeCommand.php      # bedrock remove:theme
    └── RemoveDependencyCommand.php # bedrock remove:dependency
```

---

## 📝 ESPECIFICACIONES DE COMANDOS

### 1. Comando Principal: `bedrock manage`

**Descripción**: Menú interactivo principal de gestión

**Flujo**:
```
bedrock manage

╔════════════════════════════════════════╗
║   🎛️  BEDROCK PROJECT MANAGER          ║
║   Proyecto: detodo24                   ║
╚════════════════════════════════════════╝

¿Qué deseas gestionar?

 [1] 🔌 Plugins (instalar/activar/desactivar/quitar)
 [2] 🎨 Themes (instalar/activar/quitar)
 [3] 📦 Dependencias Composer (agregar/quitar)
 [4] 🗄️  Base de datos (limpiar/snapshot/migrar)
 [5] ⚙️  Opciones WordPress (pull/push/manage)
 [6] 🏗️  Blueprints (editar por ambiente)
 [7] 📝 Profile (exportar/aplicar)
 [8] 🐳 Docker (up/down/restart)
 [9] ℹ️  Info del proyecto
 [0] ❌ Salir

Opción:
```

**Contexto**:
- Si está en un proyecto: gestiona el proyecto actual
- Si no está en proyecto: muestra error y sugiere `bedrock new`

---

### 2. Submenú: `bedrock manage` → Plugins

**Flujo**:
```
[1] 🔌 Gestión de Plugins

Plugins instalados (3):
  ✅ woocommerce v8.5.0 (activo)
  ✅ redis-cache v2.7.0 (activo)
  ⭕ contact-form-7 v5.8.0 (inactivo)

¿Qué deseas hacer?

 [1] 🔍 Buscar e instalar nuevo plugin
 [2] ✅ Activar plugin
 [3] ⭕ Desactivar plugin
 [4] 🗑️  Desinstalar plugin
 [5] 📊 Ver estado detallado
 [6] 🔄 Actualizar plugins
 [7] 📋 Listar todos
 [0] ⬅️  Volver al menú principal

Opción:
```

**Opciones Detalladas**:

#### [1] Buscar e instalar
```
Buscar plugin: woocommerce

Resultados (10,000 encontrados):
 [1] WooCommerce - 7M installs, 90% rating
 [2] Google for WooCommerce - 900K installs
 ...

Selecciona plugin (1-10, 0=cancelar): 1

¿Instalar WooCommerce v10.3.4? (Y/n): Y

Instalando...
✓ Agregado a composer.json
✓ Ejecutando composer require
✓ Plugin instalado

¿Activar ahora? (Y/n): Y
✓ Plugin activado

Presiona Enter para continuar...
```

#### [2] Activar plugin
```
Plugins inactivos:
 [1] contact-form-7
 [2] akismet
 [0] Cancelar

Selecciona plugin: 1
✓ contact-form-7 activado
```

---

### 3. Comandos Granulares

#### `bedrock add:plugin`

**Sintaxis**:
```bash
bedrock add:plugin <slug> [--activate] [--version=X.Y.Z]
```

**Ejemplos**:
```bash
# Instalar sin activar
bedrock add:plugin woocommerce

# Instalar y activar
bedrock add:plugin woocommerce --activate

# Instalar versión específica
bedrock add:plugin woocommerce --version=8.5.0 --activate
```

**Proceso**:
1. Buscar plugin en WordPress.org
2. Agregar a composer.json: `wpackagist-plugin/woocommerce`
3. Ejecutar `composer require`
4. Si `--activate`: ejecutar WP-CLI activate

---

#### `bedrock add:theme`

**Sintaxis**:
```bash
bedrock add:theme <slug> [--activate] [--version=X.Y.Z]
```

**Ejemplos**:
```bash
bedrock add:theme storefront --activate
```

---

#### `bedrock add:dependency`

**Sintaxis**:
```bash
bedrock add:dependency <vendor/package> [--version=X.Y.Z] [--dev]
```

**Ejemplos**:
```bash
bedrock add:dependency roots/acorn
bedrock add:dependency laravel/pint --dev
```

---

#### `bedrock remove:plugin`

**Sintaxis**:
```bash
bedrock remove:plugin <slug> [--force]
```

**Ejemplos**:
```bash
# Desactivar y desinstalar
bedrock remove:plugin contact-form-7

# Forzar eliminación sin confirmación
bedrock remove:plugin akismet --force
```

---

### 4. Profile Edit: `bedrock profile:edit`

**Mejoras Propuestas**:

#### Opción A: Menú Interactivo (Default)
```bash
bedrock profile:edit detodo24

╔════════════════════════════════════════╗
║   📝 EDITAR PROFILE: detodo24          ║
╚════════════════════════════════════════╝

¿Qué deseas editar?

 [1] 📝 Información básica (nombre, descripción)
 [2] 🔌 Plugins públicos (agregar/quitar)
 [3] 💎 Repositorios premium (agregar/quitar/editar)
 [4] 🛠️  Plugins custom (agregar/quitar)
 [5] 🎨 Tema (cambiar/configurar)
 [6] 🏗️  Blueprints (production/staging/development)
 [7] 📄 Ver JSON completo
 [8] 💾 Guardar y salir
 [0] ❌ Cancelar sin guardar

Opción:
```

#### Opción B: Editor Directo
```bash
bedrock profile:edit detodo24 --raw
# Abre en VS Code/Notepad++
# Valida JSON al guardar
```

#### Opción C: Comandos Granulares
```bash
bedrock profile:add-plugin detodo24 woocommerce
bedrock profile:add-repo detodo24 --type=vcs --url=git@...
bedrock profile:remove-plugin detodo24 contact-form-7
bedrock profile:set-theme detodo24 storefront
```

---

## 🚀 PLAN DE IMPLEMENTACIÓN

### FASE 1: Refactorización (TASK-026)
**Tiempo estimado**: 3-4 horas  
**Prioridad**: Alta

**Objetivos**:
1. Crear servicios reutilizables en `src/Services/Management/`
2. Extraer lógica común de comandos existentes
3. Implementar `ContextDetector` (profile vs proyecto)
4. Crear `PluginManager`, `ThemeManager`, `DependencyManager`

**Entregables**:
- `ManagementService.php`
- `ContextDetector.php`
- `PluginManager.php`
- `ThemeManager.php`
- `DependencyManager.php`
- Tests unitarios básicos

---

### FASE 2: Comando Unificado (TASK-027)
**Tiempo estimado**: 4-5 horas  
**Prioridad**: Alta

**Objetivos**:
1. Implementar `bedrock manage` (menú principal)
2. Implementar submenús:
   - `PluginsManageCommand`
   - `ThemesManageCommand`
   - `DependenciesManageCommand`
3. Integrar con servicios de Fase 1
4. Testing funcional completo

**Entregables**:
- `ManageCommand.php`
- `PluginsManageCommand.php`
- `ThemesManageCommand.php`
- `DependenciesManageCommand.php`
- Documentación de uso

---

### FASE 3: Comandos Granulares (TASK-028)
**Tiempo estimado**: 2-3 horas  
**Prioridad**: Media

**Objetivos**:
1. Implementar comandos `add:*`:
   - `add:plugin`
   - `add:theme`
   - `add:dependency`
2. Implementar comandos `remove:*`:
   - `remove:plugin`
   - `remove:theme`
   - `remove:dependency`
3. Documentación para CI/CD

**Entregables**:
- 6 comandos granulares
- Documentación de automatización
- Ejemplos de scripts

---

### FASE 4: Profile Edit Mejorado (TASK-029)
**Tiempo estimado**: 3-4 horas  
**Prioridad**: Media

**Objetivos**:
1. Implementar menú interactivo para `profile:edit`
2. Agregar flag `--raw` para editor directo
3. Implementar comandos granulares de profile:
   - `profile:add-plugin`
   - `profile:add-repo`
   - `profile:remove-plugin`
   - `profile:set-theme`
4. Validación JSON mejorada

**Entregables**:
- `ProfileEditCommand` mejorado
- Submenús de edición
- Comandos granulares de profile
- Validación robusta

---

### FASE 5: Documentación y Testing (TASK-030)
**Tiempo estimado**: 2 horas  
**Prioridad**: Alta

**Objetivos**:
1. Actualizar README.md con nuevos comandos
2. Crear guía de uso: `docs/UNIFIED_MANAGEMENT.md`
3. Testing funcional completo
4. Screencasts/GIFs de demostración

**Entregables**:
- Documentación completa
- Testing checklist
- Ejemplos visuales

---

## 📊 CRONOGRAMA

```
Semana 1:
  ✅ FASE 1: Refactorización (3-4h)
  ✅ FASE 2: Comando Unificado (4-5h)

Semana 2:
  ⏳ FASE 3: Comandos Granulares (2-3h)
  ⏳ FASE 4: Profile Edit Mejorado (3-4h)

Semana 3:
  ⏳ FASE 5: Documentación y Testing (2h)
  ⏳ Release v2.0
```

**Total estimado**: 14-18 horas de desarrollo

---

## 💡 EJEMPLOS DE USO

### Caso 1: Usuario Nuevo (Menú Interactivo)

```bash
cd mi-proyecto
bedrock manage

# Selecciona [1] Plugins
# Selecciona [1] Buscar e instalar
# Busca "woocommerce"
# Selecciona de la lista
# Confirma instalación
# Confirma activación
# ✓ Listo
```

### Caso 2: Usuario Avanzado (Comandos Directos)

```bash
bedrock add:plugin woocommerce --activate
bedrock add:theme storefront --activate
bedrock add:dependency roots/acorn
```

### Caso 3: CI/CD (Scripts)

```bash
#!/bin/bash
# deploy.sh

bedrock add:plugin woocommerce --activate
bedrock add:plugin redis-cache --activate
bedrock add:theme storefront --activate
bedrock options:pull production
bedrock db:snapshot production
```

### Caso 4: Editar Profile

```bash
# Opción A: Menú interactivo
bedrock profile:edit detodo24
# Navega por menús, edita secciones

# Opción B: Editor directo
bedrock profile:edit detodo24 --raw
# Edita JSON manualmente

# Opción C: Comandos granulares
bedrock profile:add-plugin detodo24 woocommerce
bedrock profile:add-repo detodo24 --type=vcs --url=git@gitlab.com:...
```

---

## 🎯 BENEFICIOS

### Para Usuarios Normales
- ✅ No necesitan recordar comandos
- ✅ Menús guían paso a paso
- ✅ Menos errores
- ✅ Curva de aprendizaje baja

### Para Usuarios Avanzados
- ✅ Comandos directos rápidos
- ✅ Scriptable
- ✅ Automatización fácil
- ✅ Flexibilidad total

### Para el Proyecto
- ✅ Código DRY (servicios reutilizables)
- ✅ Fácil de mantener
- ✅ Fácil de extender
- ✅ Testing más simple
- ✅ Mejor UX consistente

---

## 📝 NOTAS TÉCNICAS

### Detección de Contexto

```php
class ContextDetector
{
    public function detect(): string
    {
        // Si existe .bedrock/profile.json → Proyecto
        if (file_exists(getcwd() . '/.bedrock/profile.json')) {
            return 'project';
        }
        
        // Si existe composer.json con roots/bedrock → Proyecto
        if ($this->isBedrockProject()) {
            return 'project';
        }
        
        // Si no → Error
        return 'none';
    }
}
```

### Gestión Transaccional

```php
class ManagementService
{
    private array $changes = [];
    
    public function addChange(string $type, array $data): void
    {
        $this->changes[] = ['type' => $type, 'data' => $data];
    }
    
    public function commit(): bool
    {
        // Aplicar todos los cambios
        foreach ($this->changes as $change) {
            $this->applyChange($change);
        }
        
        // Guardar
        return $this->save();
    }
    
    public function rollback(): void
    {
        $this->changes = [];
    }
}
```

---

## 🔄 COMPATIBILIDAD

### Comandos Existentes
- ✅ Todos los comandos actuales seguirán funcionando
- ✅ No hay breaking changes
- ✅ Sistema nuevo es adicional, no reemplazo

### Migración
- No requiere migración
- Usuarios pueden seguir usando comandos antiguos
- Nuevos usuarios usarán sistema unificado

---

## 📚 REFERENCIAS

- Inspiración: Laravel Artisan, Symfony Console, WP-CLI
- Patrón: Command Pattern + Strategy Pattern
- UX: Progressive Disclosure (revelar complejidad gradualmente)

---

**Última Actualización**: 2025-11-03 10:09  
**Próxima Revisión**: Después de completar testing actual  
**Estado**: Listo para implementación
