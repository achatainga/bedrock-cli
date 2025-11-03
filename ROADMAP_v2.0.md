# 🗺️ ROADMAP v2.0 - Sistema Unificado de Gestión

**Fecha**: 2025-11-03  
**Estado**: Planificación  
**Estimado**: 14-18 horas de desarrollo

---

## 🎯 VISIÓN

Crear un sistema unificado de gestión que permita administrar proyectos Bedrock de forma intuitiva mediante:

1. **Menús interactivos** (usuarios normales)
2. **Comandos directos** (usuarios avanzados)
3. **Comandos granulares** (scripts/CI/CD)

---

## 📋 FASES DE IMPLEMENTACIÓN

### FASE 1: Refactorización (3-4 horas)
**Objetivo**: Crear servicios reutilizables

**Tareas**:
- [ ] Crear `ManagementService` (lógica común)
- [ ] Crear `ContextDetector` (detectar profile vs proyecto)
- [ ] Crear `PluginManager` (CRUD de plugins)
- [ ] Crear `ThemeManager` (CRUD de themes)
- [ ] Crear `DependencyManager` (operaciones Composer)
- [ ] Tests unitarios básicos

**Entregables**:
```
src/Services/Management/
├── ManagementService.php
├── ContextDetector.php
├── PluginManager.php
├── ThemeManager.php
└── DependencyManager.php
```

---

### FASE 2: Comando Unificado (4-5 horas)
**Objetivo**: Implementar `bedrock manage`

**Tareas**:
- [ ] Crear `ManageCommand` (menú principal)
- [ ] Crear `PluginsManageCommand` (submenú plugins)
- [ ] Crear `ThemesManageCommand` (submenú themes)
- [ ] Crear `DependenciesManageCommand` (submenú dependencias)
- [ ] Integrar con servicios de Fase 1
- [ ] Testing funcional completo

**Menú Principal**:
```
╔════════════════════════════════════════╗
║   🎛️  BEDROCK PROJECT MANAGER          ║
║   Proyecto: detodo24                   ║
╚════════════════════════════════════════╝

¿Qué deseas gestionar?

 [1] 🔌 Plugins
 [2] 🎨 Themes
 [3] 📦 Dependencias Composer
 [4] 🗄️  Base de datos
 [5] ⚙️  Opciones WordPress
 [6] 🏗️  Blueprints
 [7] 📝 Profile
 [8] 🐳 Docker
 [0] ❌ Salir
```

**Submenú Plugins**:
```
[1] 🔌 Gestión de Plugins

Plugins instalados (3):
  ✅ woocommerce v8.5.0 (activo)
  ⭕ contact-form-7 v5.8.0 (inactivo)

¿Qué deseas hacer?

 [1] 🔍 Buscar e instalar nuevo plugin
 [2] ✅ Activar plugin
 [3] ⭕ Desactivar plugin
 [4] 🗑️  Desinstalar plugin
 [5] 📊 Ver estado detallado
 [0] ⬅️  Volver
```

---

### FASE 3: Comandos Granulares (2-3 horas)
**Objetivo**: Comandos para scripts/CI/CD

**Tareas**:
- [ ] `bedrock add:plugin <slug> [--activate]`
- [ ] `bedrock add:theme <slug> [--activate]`
- [ ] `bedrock add:dependency <vendor/package>`
- [ ] `bedrock remove:plugin <slug>`
- [ ] `bedrock remove:theme <slug>`
- [ ] `bedrock remove:dependency <vendor/package>`
- [ ] Documentación para CI/CD

**Ejemplos de Uso**:
```bash
# Instalar y activar plugin
bedrock add:plugin woocommerce --activate

# Instalar theme
bedrock add:theme storefront --activate

# Agregar dependencia
bedrock add:dependency roots/acorn

# Remover plugin
bedrock remove:plugin contact-form-7
```

---

### FASE 4: Profile Edit Mejorado (3-4 horas)
**Objetivo**: Menú interactivo para editar profiles

**Tareas**:
- [ ] Implementar menú interactivo para `profile:edit`
- [ ] Agregar flag `--raw` para editor directo
- [ ] Crear `profile:add-plugin <profile> <slug>`
- [ ] Crear `profile:add-repo <profile> --type=vcs --url=...`
- [ ] Crear `profile:remove-plugin <profile> <slug>`
- [ ] Crear `profile:set-theme <profile> <slug>`
- [ ] Validación JSON mejorada

**Menú de Edición**:
```
╔════════════════════════════════════════╗
║   📝 EDITAR PROFILE: detodo24          ║
╚════════════════════════════════════════╝

¿Qué deseas editar?

 [1] 📝 Información básica
 [2] 🔌 Plugins públicos
 [3] 💎 Repositorios premium
 [4] 🛠️  Plugins custom
 [5] 🎨 Tema
 [6] 🏗️  Blueprints
 [7] 📄 Ver JSON completo
 [8] 💾 Guardar y salir
 [0] ❌ Cancelar sin guardar
```

---

### FASE 5: Documentación y Testing (2 horas)
**Objetivo**: Documentar y testear todo

**Tareas**:
- [ ] Actualizar README.md
- [ ] Crear `docs/UNIFIED_MANAGEMENT.md`
- [ ] Testing funcional completo
- [ ] Screencasts/GIFs de demostración
- [ ] Actualizar CHANGELOG.md

---

## 📊 CRONOGRAMA

```
Semana 1:
  ⏳ FASE 1: Refactorización (3-4h)
  ⏳ FASE 2: Comando Unificado (4-5h)

Semana 2:
  ⏳ FASE 3: Comandos Granulares (2-3h)
  ⏳ FASE 4: Profile Edit Mejorado (3-4h)

Semana 3:
  ⏳ FASE 5: Documentación y Testing (2h)
  ⏳ Release v2.0
```

**Total estimado**: 14-18 horas de desarrollo

---

## 💡 CASOS DE USO

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

## 📝 DECISIONES TÉCNICAS

### 1. Detección de Contexto

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

### 2. Gestión Transaccional

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

### 3. Arquitectura de Servicios

```
src/Services/Management/
├── ManagementService.php       # Lógica común
├── ContextDetector.php         # Detecta contexto
├── PluginManager.php           # CRUD plugins
├── ThemeManager.php            # CRUD themes
├── DependencyManager.php       # Composer ops
├── BlueprintManager.php        # Gestión blueprints
└── OptionManager.php           # Gestión opciones WP
```

---

## 🔗 COMPATIBILIDAD

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

- **Inspiración**: Laravel Artisan, Symfony Console, WP-CLI
- **Patrón**: Command Pattern + Strategy Pattern
- **UX**: Progressive Disclosure (revelar complejidad gradualmente)
- **Documento de Diseño**: `.io/DESIGN_UNIFIED_MANAGEMENT.md` (local)

---

## ✅ CRITERIOS DE ÉXITO

- [ ] `bedrock manage` funciona en proyectos Bedrock
- [ ] Submenús de plugins, themes y dependencias operativos
- [ ] Comandos granulares `add:*` y `remove:*` funcionan
- [ ] Profile edit con menú interactivo
- [ ] Documentación completa
- [ ] Testing funcional 80%+
- [ ] Sin breaking changes

---

## 🚀 INICIO DE DESARROLLO

**Próxima sesión**:
1. Crear rama `feature/unified-management-system`
2. Implementar FASE 1 (Refactorización)
3. Tests unitarios de servicios

---

**Última Actualización**: 2025-11-03  
**Estado**: Listo para desarrollo  
**Versión Target**: v2.0.0
