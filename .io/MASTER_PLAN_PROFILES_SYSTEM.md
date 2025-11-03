# PLAN MAESTRO: Sistema de Profiles y Blueprints para Bedrock CLI

**Versión**: 1.0  
**Fecha**: 2025-10-31 14:29  
**Proyecto**: bedrock-cli  
**Estado**: 📋 Planificación

---

## 🎯 OBJETIVO GENERAL

Crear un sistema completo de gestión de proyectos Bedrock que permita:
1. Definir perfiles reutilizables de proyectos (profiles)
2. Buscar e instalar plugins/temas desde WordPress.org interactivamente
3. Gestionar plugins premium y custom via Composer
4. Inicializar proyectos con diferentes ambientes (production, staging, development)
5. Usar seeders y snapshots para datos base configurables

---

## 📐 ARQUITECTURA DEL SISTEMA

```
~/.bedrock-cli/                          # Configuración global del usuario
├── profiles/                            # Profiles reutilizables
│   ├── detodo24.json
│   ├── blog-simple.json
│   └── ecommerce-base.json
└── config.json                          # Configuración global

bedrock-cli/                             # Repositorio público
├── src/
│   ├── Commands/
│   │   ├── Profile/
│   │   │   ├── CreateCommand.php       # profile:create
│   │   │   ├── ListCommand.php         # profile:list
│   │   │   ├── ShowCommand.php         # profile:show
│   │   │   ├── EditCommand.php         # profile:edit
│   │   │   └── DeleteCommand.php       # profile:delete
│   │   ├── Plugin/
│   │   │   ├── SearchCommand.php       # plugin:search
│   │   │   ├── InfoCommand.php         # plugin:info
│   │   │   └── AddCommand.php          # plugin:add
│   │   ├── Theme/
│   │   │   ├── SearchCommand.php       # theme:search
│   │   │   └── InfoCommand.php         # theme:info
│   │   ├── InitCommand.php             # init --env=X
│   │   └── NewCommand.php              # new <name> --profile=X
│   └── Services/
│       ├── ProfileService.php          # Gestión de profiles
│       ├── WordPressApiService.php     # API WordPress.org
│       ├── ComposerService.php         # Generación composer.json
│       └── BlueprintService.php        # Gestión de blueprints
├── stubs/
│   ├── blueprints/
│   │   ├── production.json.stub
│   │   ├── staging.json.stub
│   │   └── development.json.stub
│   └── seeders/
│       ├── CoreSeeder.php.stub
│       ├── WooCommerceSeeder.php.stub
│       ├── ThemeSeeder.php.stub
│       ├── PluginsSeeder.php.stub
│       ├── ProductsSeeder.php.stub
│       └── VendorsSeeder.php.stub
└── profiles/                            # Profiles de ejemplo (no sensibles)
    └── default.json

proyecto-creado/                         # Proyecto generado
├── .bedrock/
│   └── profile.json                    # Profile usado (viaja con proyecto)
├── blueprints/
│   ├── production.json
│   ├── staging.json
│   └── development.json
├── database/
│   ├── seeders/
│   │   ├── CoreSeeder.php
│   │   ├── WooCommerceSeeder.php
│   │   └── ...
│   └── snapshots/
│       ├── core-clean.sql
│       └── staging-full.sql
└── composer.json                        # Generado desde profile
```

---

## 📋 FASES DE IMPLEMENTACIÓN

### **FASE 1: Infraestructura Base** ⏱️ Estimado: 2-3 horas

#### TASK-001: Crear estructura de directorios y servicios base
- [ ] Crear `~/.bedrock-cli/` en home del usuario
- [ ] Crear `~/.bedrock-cli/profiles/`
- [ ] Crear `~/.bedrock-cli/config.json`
- [ ] Crear `ProfileService.php` con métodos básicos:
  - `getProfilesPath()`: Retorna path de profiles
  - `profileExists($name)`: Verifica si existe
  - `loadProfile($name)`: Carga JSON
  - `saveProfile($name, $data)`: Guarda JSON
  - `listProfiles()`: Lista todos los profiles
  - `deleteProfile($name)`: Elimina profile

**Entregables**:
- `src/Services/ProfileService.php`
- Directorio `~/.bedrock-cli/` creado automáticamente

---

#### TASK-002: Crear profile por defecto
- [ ] Crear `profiles/default.json` en el repo
- [ ] Contenido: Bedrock vanilla + WooCommerce básico
- [ ] Copiar a `~/.bedrock-cli/profiles/` en primera ejecución

**Entregables**:
- `profiles/default.json`

---

### **FASE 2: Comandos de Gestión de Profiles** ⏱️ Estimado: 3-4 horas

#### TASK-003: Comando profile:create (wizard básico)
- [ ] Crear `ProfileCreateCommand.php`
- [ ] Wizard interactivo que pida:
  - Nombre del profile
  - Descripción
  - Plugins públicos (lista manual por ahora)
  - URL de repo premium (opcional)
  - Path de plugins custom (opcional)
  - Nombre del tema
  - Si el tema es premium
- [ ] Guardar en `~/.bedrock-cli/profiles/{name}.json`
- [ ] Validar que no exista ya

**Entregables**:
- `src/Commands/Profile/CreateCommand.php`

---

#### TASK-004: Comandos profile:list, profile:show, profile:delete
- [ ] `profile:list`: Mostrar tabla con profiles disponibles
- [ ] `profile:show <name>`: Mostrar contenido JSON formateado
- [ ] `profile:delete <name>`: Eliminar con confirmación

**Entregables**:
- `src/Commands/Profile/ListCommand.php`
- `src/Commands/Profile/ShowCommand.php`
- `src/Commands/Profile/DeleteCommand.php`

---

### **FASE 3: Integración WordPress.org API** ⏱️ Estimado: 4-5 horas

#### TASK-005: Servicio WordPressApiService
- [ ] Crear `WordPressApiService.php`
- [ ] Método `searchPlugins($query, $page, $perPage)`
- [ ] Método `getPluginInfo($slug)`
- [ ] Método `searchThemes($query, $page, $perPage)`
- [ ] Método `getThemeInfo($slug)`
- [ ] Cachear resultados en `~/.bedrock-cli/cache/`

**Entregables**:
- `src/Services/WordPressApiService.php`

---

#### TASK-006: Comando plugin:search
- [ ] Crear `PluginSearchCommand.php`
- [ ] Mostrar resultados en tabla numerada
- [ ] Mostrar: nombre, instalaciones, rating, última versión
- [ ] Permitir selección múltiple (ej: 1,3,5)
- [ ] Preguntar versión constraint para cada plugin
- [ ] Agregar a profile actual o crear nuevo

**Entregables**:
- `src/Commands/Plugin/SearchCommand.php`

---

#### TASK-007: Comando plugin:info
- [ ] Crear `PluginInfoCommand.php`
- [ ] Mostrar detalles completos del plugin
- [ ] Versiones disponibles
- [ ] Links (homepage, support, changelog)
- [ ] Opción de agregar al profile

**Entregables**:
- `src/Commands/Plugin/InfoCommand.php`

---

#### TASK-008: Comandos theme:search y theme:info
- [ ] Igual que plugin:search pero para temas
- [ ] Igual que plugin:info pero para temas

**Entregables**:
- `src/Commands/Theme/SearchCommand.php`
- `src/Commands/Theme/InfoCommand.php`

---

### **FASE 4: Generación de Composer.json desde Profile** ⏱️ Estimado: 2-3 horas

#### TASK-009: Servicio ComposerService
- [ ] Crear `ComposerService.php`
- [ ] Método `generateFromProfile($profile, $projectPath)`
- [ ] Generar sección `repositories` desde profile
- [ ] Generar sección `require` desde profile
- [ ] Agregar wpackagist.org automáticamente
- [ ] Manejar plugins públicos, premium y custom

**Entregables**:
- `src/Services/ComposerService.php`

---

#### TASK-010: Modificar NewCommand para usar profiles
- [ ] Agregar opción `--profile=<name>`
- [ ] Si no se especifica, usar `default`
- [ ] Cargar profile desde `~/.bedrock-cli/profiles/`
- [ ] Generar `composer.json` usando ComposerService
- [ ] Copiar profile a `.bedrock/profile.json` del proyecto
- [ ] Ejecutar `composer install`

**Entregables**:
- `src/Commands/NewCommand.php` (modificado)

---

### **FASE 5: Sistema de Blueprints y Ambientes** ⏱️ Estimado: 3-4 horas

#### TASK-011: Crear stubs de blueprints
- [ ] Crear `stubs/blueprints/production.json.stub`
- [ ] Crear `stubs/blueprints/staging.json.stub`
- [ ] Crear `stubs/blueprints/development.json.stub`
- [ ] Variables: `{{PROJECT_NAME}}`, `{{DB_NAME}}`, etc.

**Entregables**:
- `stubs/blueprints/*.json.stub`

---

#### TASK-012: Servicio BlueprintService
- [ ] Crear `BlueprintService.php`
- [ ] Método `generateBlueprints($profile, $projectPath)`
- [ ] Copiar stubs a `{project}/blueprints/`
- [ ] Reemplazar variables
- [ ] Generar configuración de seeders según profile

**Entregables**:
- `src/Services/BlueprintService.php`

---

#### TASK-013: Integrar blueprints en NewCommand
- [ ] Generar blueprints automáticamente al crear proyecto
- [ ] Usar configuración del profile para personalizar

**Entregables**:
- `src/Commands/NewCommand.php` (modificado)

---

### **FASE 6: Sistema de Seeders** ⏱️ Estimado: 5-6 horas

#### TASK-014: Crear stubs de seeders base
- [ ] `CoreSeeder.php.stub`: Páginas, menús, opciones WP
- [ ] `WooCommerceSeeder.php.stub`: Config WC, pasarelas, envíos
- [ ] `ThemeSeeder.php.stub`: Opciones del tema
- [ ] `PluginsSeeder.php.stub`: Opciones de plugins
- [ ] `ProductsSeeder.php.stub`: Productos fake con Faker
- [ ] `VendorsSeeder.php.stub`: Vendedores fake

**Entregables**:
- `stubs/seeders/*.php.stub`

---

#### TASK-015: Clase base Seeder
- [ ] Crear `BaseSeeder.php` con helpers:
  - `createPage($title, $slug, $template)`
  - `createMenu($name, $items)`
  - `updateOption($key, $value)`
  - `downloadFakeImage($url)`
  - `createUser($username, $role, $email)`

**Entregables**:
- `stubs/seeders/BaseSeeder.php.stub`

---

#### TASK-016: Copiar seeders al crear proyecto
- [ ] Copiar todos los stubs a `{project}/database/seeders/`
- [ ] Reemplazar variables según profile

**Entregables**:
- `src/Commands/NewCommand.php` (modificado)

---

### **FASE 7: Comando Init (Inicialización de Ambientes)** ⏱️ Estimado: 4-5 horas

#### TASK-017: Crear InitCommand
- [ ] Crear `InitCommand.php`
- [ ] Opción `--env=production|staging|development`
- [ ] Leer blueprint correspondiente
- [ ] Importar snapshot SQL si existe
- [ ] Ejecutar seeders definidos en blueprint
- [ ] Activar plugins
- [ ] Configurar licencias desde .env
- [ ] Ejecutar `wp search-replace` para URLs
- [ ] Regenerar permalinks

**Entregables**:
- `src/Commands/InitCommand.php`

---

#### TASK-018: Integración con WP-CLI
- [ ] Detectar si WP-CLI está disponible
- [ ] Ejecutar comandos WP-CLI desde PHP
- [ ] Manejar errores de WP-CLI

**Entregables**:
- Helper en `InitCommand.php`

---

### **FASE 8: Mejoras al Wizard de Profiles** ⏱️ Estimado: 3-4 horas

#### TASK-019: Integrar plugin:search en profile:create
- [ ] Durante wizard, preguntar "¿Buscar plugins?"
- [ ] Si sí, ejecutar `plugin:search` interactivo
- [ ] Agregar plugins seleccionados al profile
- [ ] Permitir búsquedas múltiples

**Entregables**:
- `src/Commands/Profile/CreateCommand.php` (modificado)

---

#### TASK-020: Detección automática de plugins custom
- [ ] Si el usuario da un path, escanear carpeta
- [ ] Detectar plugins válidos (con header de plugin)
- [ ] Mostrar lista y preguntar cuáles vincular
- [ ] Agregar al profile con symlink

**Entregables**:
- Método en `ProfileService.php`

---

### **FASE 9: Comandos Adicionales** ⏱️ Estimado: 2-3 horas

#### TASK-021: Comando profile:edit
- [ ] Abrir profile en editor del sistema
- [ ] Validar JSON después de editar
- [ ] Mostrar errores si JSON inválido

**Entregables**:
- `src/Commands/Profile/EditCommand.php`

---

#### TASK-022: Comando profile:export
- [ ] Desde un proyecto existente
- [ ] Leer `.bedrock/profile.json`
- [ ] Exportar a `~/.bedrock-cli/profiles/`
- [ ] Útil para compartir configuraciones

**Entregables**:
- `src/Commands/Profile/ExportCommand.php`

---

#### TASK-023: Comando profile:apply
- [ ] Aplicar un profile a un proyecto existente
- [ ] Regenerar `composer.json`
- [ ] Actualizar `.bedrock/profile.json`
- [ ] Ejecutar `composer update`

**Entregables**:
- `src/Commands/Profile/ApplyCommand.php`

---

### **FASE 10: Documentación y Testing** ⏱️ Estimado: 3-4 horas

#### TASK-024: Documentación completa
- [ ] README.md con ejemplos de uso
- [ ] Documentar estructura de profiles
- [ ] Documentar estructura de blueprints
- [ ] Guía de creación de seeders custom
- [ ] Ejemplos de profiles para diferentes casos

**Entregables**:
- `README.md` actualizado
- `docs/PROFILES.md`
- `docs/BLUEPRINTS.md`
- `docs/SEEDERS.md`

---

#### TASK-025: Tests unitarios básicos
- [ ] Test de ProfileService
- [ ] Test de ComposerService
- [ ] Test de WordPressApiService
- [ ] Test de generación de composer.json

**Entregables**:
- `tests/Services/ProfileServiceTest.php`
- `tests/Services/ComposerServiceTest.php`
- `tests/Services/WordPressApiServiceTest.php`

---

## 📊 RESUMEN DE ENTREGABLES

### Comandos Nuevos (13)
1. `profile:create` - Crear profile con wizard
2. `profile:list` - Listar profiles
3. `profile:show` - Ver profile
4. `profile:edit` - Editar profile
5. `profile:delete` - Eliminar profile
6. `profile:export` - Exportar desde proyecto
7. `profile:apply` - Aplicar a proyecto existente
8. `plugin:search` - Buscar plugins
9. `plugin:info` - Info de plugin
10. `theme:search` - Buscar temas
11. `theme:info` - Info de tema
12. `init` - Inicializar ambiente
13. `plugin:add` - Agregar plugin interactivo

### Comandos Modificados (1)
1. `new` - Soporte para `--profile`

### Servicios Nuevos (4)
1. `ProfileService` - Gestión de profiles
2. `WordPressApiService` - API WordPress.org
3. `ComposerService` - Generación composer.json
4. `BlueprintService` - Gestión de blueprints

### Stubs Nuevos (9)
1. `blueprints/production.json.stub`
2. `blueprints/staging.json.stub`
3. `blueprints/development.json.stub`
4. `seeders/BaseSeeder.php.stub`
5. `seeders/CoreSeeder.php.stub`
6. `seeders/WooCommerceSeeder.php.stub`
7. `seeders/ThemeSeeder.php.stub`
8. `seeders/PluginsSeeder.php.stub`
9. `seeders/ProductsSeeder.php.stub`

---

## 🎯 ORDEN DE EJECUCIÓN RECOMENDADO

```
FASE 1 → FASE 2 → FASE 4 → FASE 3 → FASE 8 → FASE 5 → FASE 6 → FASE 7 → FASE 9 → FASE 10
```

**Justificación**:
- Fase 1-2: Base del sistema de profiles
- Fase 4: Generación de composer.json (crítico)
- Fase 3: API WordPress (puede ser paralelo)
- Fase 8: Mejoras al wizard (usa Fase 3)
- Fase 5-6-7: Sistema de ambientes completo
- Fase 9-10: Pulido y documentación

---

## 📝 PROTOCOLO DE COMPACTACIÓN

Al finalizar cada FASE, crear checkpoint:

```markdown
# CHECKPOINT_YYYYMMDD_HHMM.md

## Fase Completada: X
## Tareas Completadas: TASK-XXX, TASK-YYY
## Archivos Creados:
- src/Commands/...
- src/Services/...

## Archivos Modificados:
- src/Commands/NewCommand.php

## Estado Actual:
- ✅ Fase 1: Completada
- ✅ Fase 2: Completada
- ⏳ Fase 3: En progreso (TASK-005 completada)

## Próximo Paso:
TASK-006: Crear PluginSearchCommand

## Notas:
- ProfileService funcionando correctamente
- Tests pasando
```

---

## 🔄 PROTOCOLO DE RECUPERACIÓN

Al iniciar sesión después de compactación:

1. Leer `CURRENT_WORK.md`
2. Leer último `CHECKPOINT_*.md`
3. Leer este `MASTER_PLAN_PROFILES_SYSTEM.md`
4. Identificar última tarea completada
5. Continuar con siguiente tarea

---

## ⏱️ ESTIMACIÓN TOTAL

- **Tiempo total estimado**: 35-45 horas
- **Fases críticas**: 3, 4, 7 (API, Composer, Init)
- **Fases opcionales**: 9, 10 (pueden posponerse)

---

## 🎯 CRITERIOS DE ÉXITO

### Mínimo Viable (MVP)
- ✅ Crear profiles manualmente
- ✅ Usar profiles en `bedrock new`
- ✅ Generar composer.json desde profile
- ✅ Blueprints básicos

### Completo
- ✅ Todo lo anterior
- ✅ Búsqueda interactiva de plugins/temas
- ✅ Comando `init` funcional
- ✅ Seeders funcionando
- ✅ Documentación completa

---

## 📌 NOTAS IMPORTANTES

1. **Compatibilidad Pública**: Nada sensible en el repo
2. **Profiles Externos**: Siempre en `~/.bedrock-cli/`
3. **Portabilidad**: Profile viaja con proyecto en `.bedrock/`
4. **Flexibilidad**: Sistema debe funcionar para cualquier proyecto
5. **Interactividad**: Wizards guiados, no JSON manual

---

**FIN DEL PLAN MAESTRO**
