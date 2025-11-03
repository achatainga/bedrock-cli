# ANÁLISIS DETALLADO DE COMANDOS ADICIONALES

## PluginsCommand - Gestión Completa de Plugins

**Archivo**: `src/Commands/PluginsCommand.php` (650 líneas)
**Propósito**: Menú interactivo para gestión completa de plugins

### Opciones CLI
| Opción | Tipo | Descripción |
|--------|------|-------------|
| `plugin` | Argumento | Nombre del plugin (opcional) |
| `--delete` | Flag | Eliminar carpeta del plugin (filesystem) |
| `--compress` | Flag | Comprimir plugin a ZIP |

### Menú Interactivo (7 opciones)
1. Gestionar - Plugin específico (submenú)
2. Listar desde WordPress - Consultar con WP-CLI
3. Instalar - Desde repositorio
4. Actualizar - Todos los plugins
5. Descomprimir - Instalar desde ZIPs
6. Orden de Activación - Gestionar secuencia
7. Construir Orden - Constructor interactivo

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando y opciones |
| `execute()` | InputInterface, OutputInterface | int | Loop de menú principal |

### Métodos Privados
| Método | Descripción |
|--------|-------------|
| `listFromWordPress()` | Lista plugins vía WP-CLI con detección de errores |
| `listPlugins()` | Lista plugins desde filesystem |
| `install()` | Instala plugin desde repositorio |
| `activate()` | Activa plugin |
| `deactivate()` | Desactiva plugin |
| `uninstall()` | Desinstala plugin vía WP-CLI |
| `update()` | Actualiza todos los plugins |
| `unzipPlugins()` | Descomprime ZIPs masivamente |
| `managePlugin()` | Submenú de gestión de plugin |
| `pluginActions()` | Acciones sobre plugin específico |
| `deletePluginFolder()` | Elimina carpeta con confirmación |
| `removeDirectory()` | Elimina directorio recursivamente |
| `compressPlugin()` | Comprime plugin a ZIP |
| `deletePluginFolderDirect()` | Elimina carpeta sin menú |
| `compressPluginDirect()` | Comprime sin menú |
| `runWithLoader()` | Barra de progreso animada |

### Dependencias
- Symfony\Component\Console\Command\Command
- Symfony\Component\Console\Question\ChoiceQuestion
- Roots\BedrockCli\Services\DockerService
- Roots\BedrockCli\Services\WpCliService
- Roots\BedrockCli\Services\UnzipService
- Roots\BedrockCli\Services\ZipService

### Flujo de Ejecución
```
1. Verificar opciones CLI (--delete, --compress)
   └─ Si presentes, ejecutar directamente
2. Mostrar menú interactivo (loop infinito)
3. Usuario selecciona opción
4. Ejecutar acción correspondiente
   ├─ Gestionar: Submenú con 6 acciones
   ├─ Listar: WP-CLI con detección de errores
   ├─ Instalar: Solicitar slug y ejecutar
   ├─ Actualizar: wp plugin update --all
   ├─ Descomprimir: Listar ZIPs y descomprimir
   ├─ Orden: Delegar a PluginsOrderMenuCommand
   └─ Construir: Delegar a PluginsOrderBuilderCommand
5. Volver al menú
```

### Detección Inteligente de Errores
- Error de conexión a BD → Sugerir `bedrock docker --up`
- WordPress no instalado → Sugerir `bedrock install`
- Docker no disponible → Sugerir `bedrock doctor`

### Ejemplo de Uso
```bash
# Menú interactivo
bedrock plugins

# Eliminar plugin directamente
bedrock plugins mi-plugin --delete

# Comprimir plugin
bedrock plugins mi-plugin --compress
```

---

## PluginsListCommand - Listar Plugins

**Archivo**: `src/Commands/PluginsListCommand.php` (45 líneas)
**Propósito**: Lista plugins instalados desde filesystem

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Lista plugins |

### Flujo
```
1. Detectar carpeta web/app/plugins
2. Escanear directorios
3. Filtrar . y ..
4. Mostrar lista
```

---

## PluginsOrderCommand - Orden de Activación

**Archivo**: `src/Commands/PluginsOrderCommand.php` (350 líneas)
**Propósito**: Gestionar orden de activación de plugins con dependencias

### Opciones CLI
| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `action` | Argumento | save | Acción: list\|save\|activate |
| `--config` | String | - | Archivo de configuración personalizado |

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Ejecuta acción |

### Métodos Privados
| Método | Descripción |
|--------|-------------|
| `saveOrder()` | Guarda orden actual en JSON |
| `listPlugins()` | Lista plugins con orden y dependencias |
| `activateInOrder()` | Activa plugins en secuencia |
| `getConfigFile()` | Obtiene ruta del archivo de configuración |
| `detectDependencies()` | Detecta dependencias automáticamente |
| `runWithLoader()` | Barra de progreso |

### Flujo de Ejecución
```
1. Identificar acción (list, save, activate)
2. Obtener archivo de configuración
   └─ Default: config/plugins/activation-order.json
3. Ejecutar acción:
   
   SAVE:
   ├─ Consultar plugins activos vía WP-CLI
   ├─ Detectar dependencias automáticamente
   ├─ Guardar en JSON con orden y dependencias
   └─ Reportar cantidad guardada
   
   LIST:
   ├─ Leer archivo JSON
   ├─ Consultar estado actual vía WP-CLI
   ├─ Mostrar tabla con orden y dependencias
   └─ Leyenda: ✓ = Activo, ○ = Inactivo, [N] = Orden
   
   ACTIVATE:
   ├─ Leer orden desde JSON
   ├─ Activar plugins en secuencia
   ├─ Omitir ya activos
   ├─ Capturar errores
   └─ Mostrar resumen (activados, omitidos, fallidos)
```

### Detección Automática de Dependencias
```php
Patrones:
- woocommerce-* → requiere woocommerce
- dt24-* → requiere woocommerce
```

### Estructura del JSON
```json
{
  "activation_order": {
    "redis-cache": 0,
    "acorn": 1,
    "woocommerce": 2,
    "dt24-metodos-envio": 3
  },
  "dependencies": {
    "dt24-metodos-envio": ["woocommerce"]
  }
}
```

### Ejemplo de Uso
```bash
# Guardar orden actual
bedrock plugins:order save

# Listar orden guardado
bedrock plugins:order list

# Aplicar orden
bedrock plugins:order activate

# Usar configuración personalizada
bedrock plugins:order save --config=produccion.json
bedrock plugins:order activate --config=produccion.json
```

---

## ThemesCommand - Gestión de Temas

**Archivo**: `src/Commands/ThemesCommand.php` (400 líneas)
**Propósito**: Menú interactivo para gestión de temas

### Menú Interactivo (4 opciones)
1. Gestionar - Tema específico (submenú)
2. Listar desde WordPress - WP-CLI
3. Actualizar - Todos los temas
4. Descomprimir - ZIPs

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Loop de menú |

### Métodos Privados
| Método | Descripción |
|--------|-------------|
| `listFromWordPress()` | Lista temas vía WP-CLI |
| `listThemes()` | Lista desde filesystem |
| `activate()` | Activa tema |
| `delete()` | Elimina tema |
| `update()` | Actualiza todos |
| `unzipThemes()` | Descomprime ZIPs |
| `manageTheme()` | Submenú de gestión |
| `themeActions()` | Acciones sobre tema |
| `compressTheme()` | Comprime a ZIP |
| `themeStatus()` | Información del tema |
| `runWithLoader()` | Barra de progreso |

### Submenú de Tema (4 acciones)
1. Activar tema
2. Eliminar tema
3. Estado / información (WP-CLI)
4. Comprimir a ZIP

### Dependencias
- Roots\BedrockCli\Services\WpCliService
- Roots\BedrockCli\Services\UnzipService
- Roots\BedrockCli\Services\ZipService

### Ejemplo de Uso
```bash
bedrock themes
```

---

## OptionsCommand - Gestión de Opciones WP

**Archivo**: `src/Commands/OptionsCommand.php` (70 líneas)
**Propósito**: Menú para exportar/importar opciones de WordPress

### Menú Interactivo (4 opciones)
1. Exportar - Extraer opciones a JSON
2. Importar - Inyectar opciones desde JSON
3. Listar - Ver archivos JSON disponibles
4. Gestionar - Opción específica

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Loop de menú |

### Delegación de Comandos
- Opción 1 → `options:pull`
- Opción 2 → `options:push`
- Opción 3 → `options:list`
- Opción 4 → `options:manage`

### Caso de Uso
Sincronizar configuraciones entre entornos (desarrollo, staging, producción)

### Ejemplo de Uso
```bash
bedrock options
```

---

## DoctorCommand - Verificación de Dependencias

**Archivo**: `src/Commands/DoctorCommand.php` (400 líneas)
**Propósito**: Verificar e instalar dependencias del sistema

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Ejecuta verificación |

### Métodos Privados
| Método | Descripción |
|--------|-------------|
| `detectOS()` | Detecta sistema operativo |
| `windowsSetup()` | Setup para Windows |
| `linuxSetup()` | Setup para Linux |
| `macSetup()` | Setup para Mac |
| `checkChocolatey()` | Verifica Chocolatey |
| `installChocolatey()` | Instala Chocolatey |
| `checkWSL()` | Verifica WSL2 |
| `installWSL()` | Instala WSL2 |
| `checkUbuntu()` | Verifica Ubuntu en WSL |
| `installUbuntu()` | Instala Ubuntu |
| `checkDockerDesktop()` | Verifica Docker Desktop |
| `installDockerDesktop()` | Instala Docker Desktop |
| `checkDockerRunning()` | Verifica Docker corriendo |
| `waitWithLoader()` | Espera con animación |

### Flujo de Ejecución (Windows)
```
1. Detectar OS
2. Verificar/Instalar Chocolatey
   └─ Si no existe, preguntar si instalar
3. Verificar/Instalar WSL2
   └─ Si no existe, preguntar si instalar
   └─ Si instala, requiere reinicio
4. Verificar/Instalar Ubuntu en WSL
5. Verificar/Instalar Docker Desktop
   └─ Instala vía Chocolatey
6. Verificar Docker corriendo
   └─ Si no corre, intenta iniciar Docker Desktop
   └─ Espera hasta 60 segundos
```

### Flujo Linux
```
1. Verificar Docker instalado
2. Si no existe, mostrar comandos:
   - sudo apt-get update
   - sudo apt-get install docker.io docker-compose
   - sudo usermod -aG docker $USER
```

### Flujo Mac
```
1. Verificar Docker Desktop en /Applications/
2. Si no existe, mostrar URL de descarga
```

### Ejemplo de Uso
```bash
bedrock doctor
```

### Salida Esperada
```
╔═══════════════════════════════════════╗
║  BEDROCK DOCTOR - System Check        ║
╚═══════════════════════════════════════╝

Sistema detectado: Windows

═══ Paso 1: Chocolatey ═══
✓ Chocolatey instalado

═══ Paso 2: WSL2 ═══
✓ WSL instalado

═══ Paso 3: Ubuntu en WSL ═══
✓ Ubuntu instalado en WSL

═══ Paso 4: Docker Desktop ═══
✓ Docker Desktop instalado

═══ Paso 5: Docker en ejecución ═══
✓ Docker está corriendo

✓ Verificación completada
```

---

## ReinstallCommand - Reinstalación Completa

**Archivo**: `src/Commands/ReinstallCommand.php` (250 líneas)
**Propósito**: Reinstalar aplicación completa (DESTRUCTIVO)

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Ejecuta reinstalación |

### Métodos Privados
| Método | Descripción |
|--------|-------------|
| `saveWpInstallData()` | Guarda datos de instalación actual |
| `removeDirectory()` | Elimina directorio (Windows/Unix) |
| `runComposerInstall()` | Ejecuta composer install |
| `reinstallWordPress()` | Reinstala WordPress con datos guardados |
| `runWithLoader()` | Barra de progreso |

### Flujo de Ejecución
```
1. Mostrar advertencia con lista de acciones
2. Solicitar confirmación con código aleatorio
   └─ SecurityService::confirmDangerousAction()
3. Guardar datos de instalación actual
   ├─ URL (desde .env)
   ├─ Título del sitio (desde BD)
   └─ Email admin (desde BD)
4. Resetear base de datos
   └─ wp db reset
5. Eliminar WordPress
   └─ rm -rf web/wp
6. Eliminar vendor
   └─ rm -rf vendor
7. Reinstalar dependencias
   └─ composer install --no-dev --optimize-autoloader
8. Rebuild Docker sin caché
   └─ docker-compose build --no-cache
9. Reinstalar WordPress
   └─ wp core install (con datos guardados)
   └─ Usuario: admin / Contraseña: admin
10. Mostrar credenciales de acceso
```

### Confirmación de Seguridad
```
═══════════════════════════════════════════════════════════
  REINSTALACIÓN COMPLETA DE LA APLICACIÓN
═══════════════════════════════════════════════════════════

Esta operación realizará:
  1. Resetear base de datos    - (eliminar todos los datos)
  2. Eliminar WordPress        - (rm -rf web/wp)
  3. Eliminar vendor           - (rm -rf vendor)
  4. Reinstalar dependencias   - (composer install)
  5. Rebuild Docker            - (sin caché)
  6. Instalar WordPress        - (con datos guardados)

⚠️  ADVERTENCIA: ACCIÓN DESTRUCTIVA ⚠️
Esta acción ELIMINARÁ TODOS LOS DATOS y reinstalará la aplicación.

Si estás seguro, escribe el código: 847392
Código: _
```

### Ejemplo de Uso
```bash
bedrock reinstall
```

### Salida Esperada
```
Paso 1/6: Reseteando base de datos... ✓
Paso 2/6: Eliminando WordPress... ✓
Paso 3/6: Eliminando vendor... ✓
Paso 4/6: Instalando dependencias... ✓
Paso 5/6: Reconstruyendo contenedores Docker... ✓
Paso 6/6: Instalando WordPress... ✓

═══════════════════════════════════════════════════════════
  ✓ REINSTALACIÓN COMPLETADA
═══════════════════════════════════════════════════════════

Credenciales de acceso:
  Usuario: admin
  Contraseña: admin
  URL: http://localhost:8080/wp/wp-admin
```

---

## SnapshotCommand - Snapshots de BD

**Archivo**: `src/Commands/SnapshotCommand.php` (180 líneas)
**Propósito**: Crear y restaurar snapshots rápidos de base de datos

### Opciones CLI
| Opción | Tipo | Descripción |
|--------|------|-------------|
| `--create` | Flag | Crear snapshot |
| `--restore` | Flag | Restaurar snapshot |
| `--name` | String | Nombre del snapshot |

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Ejecuta acción |

### Métodos Privados
| Método | Descripción |
|--------|-------------|
| `createSnapshot()` | Exporta BD a archivo SQL |
| `restoreSnapshot()` | Importa BD desde archivo SQL |
| `loadEnv()` | Lee credenciales de .env |
| `runWithLoader()` | Barra de progreso |

### Flujo Create
```
1. Validar --name presente
2. Generar nombre: {name}_{timestamp}.sql
3. Leer credenciales de .env
4. Ejecutar mysqldump vía docker-compose
   └─ docker-compose exec -T mysql mysqldump
5. Guardar en database/snapshots/
6. Reportar éxito
```

### Flujo Restore
```
1. Listar snapshots disponibles
2. Si --name presente, buscar coincidencia
3. Si no, mostrar menú de selección
4. Leer archivo SQL
5. Ejecutar mysql vía docker-compose
   └─ docker-compose exec -T mysql mysql < snapshot.sql
6. Reportar éxito
```

### Estructura de Archivos
```
database/snapshots/
├── base-install_20250130-1430.sql
├── before-migration_20250130-1500.sql
└── working-state_20250130-1600.sql
```

### Ejemplo de Uso
```bash
# Crear snapshot
bedrock snapshot --create --name=base-install

# Restaurar snapshot específico
bedrock snapshot --restore --name=base-install

# Restaurar con menú interactivo
bedrock snapshot --restore
```

### Salida Esperada
```
# Create
Creando snapshot: base-install_20250130-1430.sql
Exportando base de datos ✓
✓ Snapshot creado: base-install_20250130-1430.sql

# Restore
Restaurando snapshot: base-install_20250130-1430.sql
Importando base de datos ✓
✓ Snapshot restaurado: base-install_20250130-1430.sql
```

---

## DbCleanCommand - Limpieza de BD

**Archivo**: `src/Commands/DbCleanCommand.php` (60 líneas)
**Propósito**: Limpiar base de datos (multisite, prefijos, opciones)

### Opciones CLI
| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `--from-multisite` | Flag | - | Eliminar tablas multisite |
| `--old-prefix` | String | hp2f_ | Prefijo antiguo |
| `--new-prefix` | String | wp_ | Prefijo nuevo |

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Ejecuta limpieza |

### Flujo de Ejecución
```
1. Verificar que existe scripts/clean-database.php
2. Leer opciones (old-prefix, new-prefix)
3. Ejecutar script PHP
   └─ php scripts/clean-database.php hp2f_ wp_
4. Script realiza:
   ├─ Renombrar tablas: old_posts → new_posts
   ├─ Eliminar tablas multisite
   ├─ Limpiar opciones obsoletas
   ├─ Actualizar usermeta (reemplazar prefix)
   └─ Optimizar tablas
5. Reportar éxito
```

### Ejemplo de Uso
```bash
# Cambiar prefijo
bedrock db:clean --old-prefix=hp2f_ --new-prefix=wp_

# Limpiar multisite
bedrock db:clean --from-multisite
```

### Salida Esperada
```
Ejecutando limpieza de base de datos...

Renombrando tablas: hp2f_ → wp_
✓ 15 tablas renombradas

Eliminando tablas multisite...
✓ 8 tablas eliminadas

Limpiando opciones obsoletas...
✓ 12 opciones eliminadas

Actualizando usermeta...
✓ 45 registros actualizados

Optimizando tablas...
✓ 15 tablas optimizadas

✓ Base de datos limpiada exitosamente
```

---

## BackupCommand - Backup Completo

**Archivo**: `src/Commands/BackupCommand.php` (20 líneas)
**Estado**: ⏳ Pendiente de implementación

### Métodos Públicos
| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `configure()` | - | void | Define comando |
| `execute()` | InputInterface, OutputInterface | int | Muestra mensaje pendiente |

### Funcionalidad Planeada
- Backup de base de datos (mysqldump)
- Backup de uploads (web/app/uploads)
- Compresión en archivo .tar.gz
- Timestamping automático
- Restauración desde backup

### Alternativa Temporal
Usar `SnapshotCommand` para backups de BD

### Ejemplo de Uso (Futuro)
```bash
# Crear backup completo
bedrock backup --create

# Restaurar backup
bedrock backup --restore --file=backup_20250130.tar.gz
```

---

**FIN DEL ANÁLISIS**
