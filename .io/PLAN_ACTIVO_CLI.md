# 🎯 PLAN ACTIVO: BEDROCK-CLI UNIVERSAL

**Fecha Inicio**: 2025-10-28  
**Repositorio**: GitHub - achatainga/bedrock-cli  
**Propósito**: CLI universal para proyectos Roots Bedrock  
**Estado**: ACTIVO - Comando migrate implementado

---

## ✅ FASE 0: INSTALADOR UNIVERSAL - COMPLETADO

### 0.1 Sistema de Stubs/Plantillas ✅
- [x] Crear carpeta `stubs/` en raíz
- [x] Stubs de Docker (docker-compose, Dockerfile, nginx)
- [x] Stubs de configuración (.env, .gitignore)
- [x] Stubs de scripts (sanitize-db, clean-database)
- [x] Stubs de Acorn (acorn-boot.php)
- [x] Stubs de config (application.php con guards)

### 0.2 Comando `bedrock new` ✅
- [x] Crear proyecto Bedrock desde composer
- [x] Copiar stubs con reemplazo de variables
- [x] Opciones: --with-acorn, --with-docker
- [x] Detección automática de puertos libres
- [x] Opciones: --http-port, --mysql-port, --redis-port

### 0.3 Comando `bedrock setup` ✅
- [x] Instalar WordPress (opcional con --skip-wp-install)
- [x] Configurar Acorn (storage + configs)
- [x] Opciones de personalización (url, title, admin)

---

## ✅ FASE 1: COMANDO MIGRATE - COMPLETADO

### 1.1 Comando `bedrock migrate` ✅
**Commit**: `79a72ee` - feat: comando migrate para automatizar importación de BD

**Funcionalidad**:
- [x] Importar SQL dump a base de datos
- [x] Limpiar BD (renombrar prefijos)
- [x] Search-replace URLs
- [x] Configurar Acorn
- [x] Activar tema por defecto (opcional)

**Opciones**:
- `--sql-file` - Ruta al dump SQL (requerido)
- `--old-prefix` - Prefix antiguo (default: wp_)
- `--new-prefix` - Prefix nuevo (default: wp_)
- `--old-url` - URL antigua (requerido)
- `--new-url` - URL nueva (detecta desde .env)
- `--skip-acorn` - Saltar configuración Acorn
- `--default-theme` - Activar twentytwentyfive

**Flujo Automatizado**:
1. ✅ Verificar Docker corriendo
2. ✅ Confirmar sobrescritura de BD
3. ✅ Importar SQL (timeout 10 min)
4. ✅ Limpiar BD (si hay cambio de prefix)
5. ✅ Search-replace URLs
6. ✅ Configurar Acorn (storage + configs)
7. ✅ Activar tema por defecto (opcional)
8. ✅ Mostrar resumen y próximos pasos

---

## 📝 COMANDOS EXISTENTES

### Gestión de Base de Datos
- [x] `snapshot --create --name="backup"` - Crear snapshot
- [x] `snapshot --restore --name="backup"` - Restaurar snapshot
- [x] `db:clean --old-prefix=hp2f_ --new-prefix=wp_` - Limpiar BD

### Gestión de Configuración
- [x] `export:config` - Exportar opciones básicas
- [x] `export:config --all` - Exportar todas las opciones
- [x] `import:core` - Importar golden-image.sql + configs JSON

### Gestión de Plugins
- [x] `plugins:order` - Aplicar orden de activación
- [x] `plugins:order:menu` - Menú de gestión
- [x] `plugins:order:builder` - Constructor interactivo

### Gestión de Opciones
- [x] `options:pull` - Exportar opciones a JSON
- [x] `options:push` - Importar opciones desde JSON

### Otros
- [x] `docker` - Gestión de Docker
- [x] `doctor` - Verificar dependencias del sistema

---

## 🚀 FLUJO COMPLETO DE MIGRACIÓN

### Crear Proyecto Nuevo y Migrar BD:
```bash
# 1. Crear proyecto
bedrock new detodo24-clean --with-docker
cd detodo24-clean

# 2. Iniciar Docker
docker-compose up -d

# 3. Migrar BD completa
php vendor/bin/bedrock migrate \
  --sql-file="C:\Users\achat\Downloads\20251020-detodo24db.sql" \
  --old-prefix=hp2f_ \
  --old-url=https://detodo24.com \
  --default-theme

# ¡Listo! Sitio funcionando en http://localhost:82
```

### Migrar sin cambiar prefix:
```bash
php vendor/bin/bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://example.com \
  --new-url=http://localhost:8080
```

### Migrar sin Acorn:
```bash
php vendor/bin/bedrock migrate \
  --sql-file=dump.sql \
  --old-url=https://example.com \
  --skip-acorn
```

---

## 📊 MÉTRICAS DE AUTOMATIZACIÓN

### Antes (Manual):
- **Pasos**: 7 comandos manuales
- **Tiempo**: ~15 minutos
- **Errores comunes**: Olvidar search-replace, no configurar Acorn

### Después (Automatizado):
- **Pasos**: 1 comando
- **Tiempo**: ~5 minutos (automático)
- **Errores**: Validaciones previas + confirmación

---

## 🔄 USO DUAL

### Uso Global (Crear proyectos nuevos):
```bash
composer global require achatainga/bedrock-cli
bedrock new my-site --with-acorn --with-docker
```

### Uso Local (Gestionar proyecto existente):
```bash
cd my-site
php vendor/bin/bedrock migrate --sql-file=dump.sql --old-url=https://old.com
```

---

## 📝 DECISIONES TÉCNICAS

### Detección Automática de URL
- Lee `WP_HOME` desde `.env` si no se proporciona `--new-url`
- Evita errores de configuración

### Confirmación de Sobrescritura
- Pregunta antes de importar SQL
- Previene pérdida accidental de datos

### Timeouts Generosos
- Importación SQL: 10 minutos
- Limpieza BD: 5 minutos
- Search-replace: 5 minutos

### Manejo de Errores
- Valida archivo SQL existe
- Verifica Docker corriendo
- Lee credenciales desde .env
- Reporta errores claros

---

**Última Actualización**: 2025-10-28 15:39  
**Último Commit**: 79a72ee - feat: comando migrate  
**Progreso**: Fase 1 COMPLETADA ✅

---

## 🎯 PRÓXIMOS PASOS (FASE 2)

1. Documentar comando migrate en README.md
2. Agregar ejemplos de uso en DEVELOPMENT.md
3. Testing del comando con diferentes escenarios
4. Optimizar mensajes de progreso (loaders animados)
5. Agregar opción `--dry-run` para simular sin ejecutar
