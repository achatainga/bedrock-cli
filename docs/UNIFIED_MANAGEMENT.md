# Sistema Unificado de Gestión v2.0

Gestión completa de proyectos Bedrock con menús interactivos y comandos directos.

---

## 🎯 Visión General

El Sistema Unificado de Gestión proporciona **dos formas** de gestionar tu proyecto:

1. **Menús Interactivos**: Para usuarios que prefieren navegación guiada
2. **Comandos Directos**: Para scripts, CI/CD y usuarios avanzados

---

## 🎛️ Menús Interactivos

### Menú Principal

```bash
bedrock manage
```

Abre el menú principal con opciones para gestionar:
- 🔌 Plugins
- 🎨 Themes
- 📦 Dependencias Composer

### Gestión de Plugins

```bash
bedrock manage:plugins
```

**Funciones**:
- Listar plugins instalados
- Buscar e instalar plugins desde WordPress.org
- Desinstalar plugins
- Ver detalles completos (rating, instalaciones, descripción)

**Flujo de Instalación**:
1. Seleccionar "Buscar e instalar plugin"
2. Ingresar término de búsqueda
3. Seleccionar de resultados
4. Confirmar instalación
5. Ver progreso en tiempo real

### Gestión de Themes

```bash
bedrock manage:themes
```

**Funciones**:
- Listar themes instalados
- Buscar e instalar themes desde WordPress.org
- Desinstalar themes
- Ver detalles completos

### Gestión de Dependencias

```bash
bedrock manage:dependencies
```

**Funciones**:
- Listar dependencias (producción y desarrollo)
- Agregar nuevas dependencias
- Remover dependencias
- Actualizar todas las dependencias
- Instalar dependencias

---

## ⚡ Comandos Directos

### Agregar Recursos

```bash
# Plugins
bedrock add:plugin woocommerce
bedrock add:plugin woocommerce --version=^8.0
bedrock add:plugin woocommerce --activate

# Themes
bedrock add:theme storefront
bedrock add:theme storefront --version=^4.0

# Dependencias
bedrock add:dependency roots/acorn
bedrock add:dependency phpunit/phpunit --dev
```

### Remover Recursos

```bash
# Plugins
bedrock remove:plugin akismet

# Themes
bedrock remove:theme twentytwentyfour

# Dependencias
bedrock remove:dependency old-vendor/package
```

---

## 📝 Gestión de Profiles

### Comandos Granulares

```bash
# Agregar plugin a profile
bedrock profile:add-plugin detodo24 woocommerce --version=^8.0

# Remover plugin de profile
bedrock profile:remove-plugin detodo24 akismet

# Establecer theme de profile
bedrock profile:set-theme detodo24 storefront --version=^4.0

# Agregar repositorio custom
bedrock profile:add-repo detodo24 --type=vcs --url=git@gitlab.com:vendor/plugin.git
```

---

## 🔄 Casos de Uso

### Caso 1: Usuario Nuevo (Menús)

```bash
cd mi-proyecto
bedrock manage
# → Seleccionar "Plugins"
# → Seleccionar "Buscar e instalar"
# → Buscar "woocommerce"
# → Seleccionar de lista
# → Confirmar
```

### Caso 2: Usuario Avanzado (Comandos)

```bash
bedrock add:plugin woocommerce
bedrock add:plugin redis-cache
bedrock add:theme storefront
```

### Caso 3: Script de Setup

```bash
#!/bin/bash
bedrock add:plugin woocommerce --version=^8.0
bedrock add:plugin woocommerce-gateway-stripe
bedrock add:plugin redis-cache
bedrock add:theme storefront --activate
```

### Caso 4: CI/CD Pipeline

```yaml
# .github/workflows/deploy.yml
- name: Install dependencies
  run: |
    bedrock add:plugin woocommerce --version=^8.0
    bedrock add:plugin redis-cache
    bedrock add:theme storefront
```

### Caso 5: Crear Profile por Comandos

```bash
bedrock profile:create ecommerce
bedrock profile:add-plugin ecommerce woocommerce
bedrock profile:add-plugin ecommerce woocommerce-gateway-stripe
bedrock profile:set-theme ecommerce storefront
bedrock profile:add-repo ecommerce --type=vcs --url=git@gitlab.com:vendor/premium.git
```

---

## 🏗️ Arquitectura

### Servicios (FASE 1)

- **ContextDetector**: Detecta proyecto Bedrock y contexto
- **ManagementService**: Lógica común de gestión
- **PluginManager**: CRUD de plugins
- **ThemeManager**: CRUD de themes
- **DependencyManager**: Operaciones Composer

### Comandos Interactivos (FASE 2)

- **ManageCommand**: Menú principal
- **PluginsManageCommand**: Submenú plugins
- **ThemesManageCommand**: Submenú themes
- **DependenciesManageCommand**: Submenú dependencias

### Comandos Granulares (FASE 3)

- **add:plugin/theme/dependency**: Agregar recursos
- **remove:plugin/theme/dependency**: Remover recursos

### Profile Edit (FASE 4)

- **profile:add-plugin**: Agregar plugin a profile
- **profile:remove-plugin**: Remover plugin de profile
- **profile:set-theme**: Establecer theme
- **profile:add-repo**: Agregar repositorio

---

## 💡 Ventajas

### Menús Interactivos
- ✅ Guiado paso a paso
- ✅ Búsqueda interactiva
- ✅ Ver detalles antes de instalar
- ✅ Ideal para usuarios nuevos

### Comandos Directos
- ✅ Una sola línea
- ✅ Scriptable
- ✅ Rápido
- ✅ Ideal para CI/CD

---

## 📊 Comparación

| Característica | Menús | Comandos |
|----------------|-------|----------|
| Interactivo | ✅ | ❌ |
| Scriptable | ❌ | ✅ |
| Búsqueda | ✅ | ❌ |
| Velocidad | Media | Rápida |
| Curva aprendizaje | Baja | Media |
| CI/CD | ❌ | ✅ |

---

## 🔧 Opciones Avanzadas

### Versiones Específicas

```bash
bedrock add:plugin woocommerce --version=^8.0
bedrock add:theme storefront --version=^4.0
```

### Dependencias de Desarrollo

```bash
bedrock add:dependency phpunit/phpunit --dev
```

### Repositorios Custom

```bash
bedrock profile:add-repo myprofile --type=vcs --url=git@gitlab.com:vendor/plugin.git
bedrock profile:add-repo myprofile --type=path --url=../local-plugin
```

---

## 🚀 Mejores Prácticas

1. **Usa menús** para explorar y aprender
2. **Usa comandos** para automatizar
3. **Versiona profiles** en Git
4. **Documenta scripts** de setup
5. **Testea en staging** antes de producción

---

## 📚 Referencias

- [Profiles Guide](PROFILES.md)
- [Blueprints Guide](BLUEPRINTS.md)
- [Seeders Guide](SEEDERS.md)
