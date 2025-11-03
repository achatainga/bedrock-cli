# BEDROCK-CLI - Resumen Ejecutivo

**Versión**: 1.0.0  
**Fecha**: 2025-10-31  
**Repositorio**: https://github.com/achatainga/bedrock-cli  
**Licencia**: MIT

---

## 🎯 QUÉ ES BEDROCK-CLI

CLI universal para gestionar proyectos WordPress basados en Roots Bedrock. Automatiza tareas comunes de desarrollo, despliegue y mantenimiento con una interfaz consistente.

**Problema que resuelve**: WordPress tradicional tiene estructura monolítica. Bedrock moderniza esto, pero requiere conocimiento de Composer, Docker, WP-CLI y configuraciones complejas.

**Solución**: Bedrock CLI abstrae toda esta complejidad en comandos simples.

---

## 🚀 COMANDOS PRINCIPALES

### Creación de Proyectos
```bash
bedrock new my-site --with-docker
```
Crea proyecto Bedrock completo con Docker, Acorn, Redis en minutos.

### Migración Automatizada
```bash
bedrock migrate --sql-file=dump.sql --old-url=https://old.com
```
Importa, limpia, reemplaza URLs y configura Acorn automáticamente.

### Gestión de Plugins
```bash
bedrock plugins:order-builder
```
Constructor interactivo con sintaxis de rangos (ej: `7-12,44`).

### Snapshots de BD
```bash
bedrock snapshot --create --name=backup
bedrock snapshot --restore --name=backup
```
Backup/restore rápido de base de datos.

---

## 📦 ARQUITECTURA

- **Lenguaje**: PHP 8.0+
- **Framework**: Symfony Console 6.0+
- **Patrón**: Command Pattern + Service Layer
- **Comandos**: 38 implementados
- **Servicios**: 6 (Docker, WpCli, Security, Zip, Unzip, Progress)
- **Stubs**: 14 plantillas

### Comandos por Categoría
- **Core** (3): new, migrate, setup
- **Menús** (2): menu, acorn
- **Docker/DB** (5): docker, database, db:clean, backup, snapshot
- **Plugins** (10): plugins, list, status, activate, deactivate, compress, order, order-menu, order-builder
- **Themes** (5): themes, list, status, activate, compress
- **Options** (5): options, list, manage, pull, push
- **Utilidades** (6): doctor, install, reinstall, seed, update, export-config, import-core

---

## ✨ CARACTERÍSTICAS DESTACADAS

### 1. Sistema de Menús Interactivos
- 11 opciones en menú Acorn (Fase 1 completada)
- UX mejorada con colores y símbolos
- Navegación sin miedo (opciones de solo lectura)

### 2. Loaders Animados
- 17 loaders implementados
- Feedback visual en tiempo real
- Spinner Braille: ⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏

### 3. Constructor de Orden de Plugins
- Sintaxis de rangos: `7-12,44`
- Comandos interactivos: list, remove, clear, save
- 70% menos interacciones que método antiguo

### 4. Seguridad
- Confirmaciones con códigos aleatorios de 6 dígitos
- Protección contra ejecución accidental
- Operaciones destructivas requieren confirmación

### 5. Detección Inteligente
- Puertos libres auto-detectados
- Prefijos de BD auto-detectados (DB_PREFIX)
- URLs auto-completadas (8080 → http://localhost:8080)

---

## 📊 CASOS DE USO

### Caso 1: Migrar Sitio Existente
```bash
# 1. Crear proyecto
bedrock new detodo24-local --with-docker
cd detodo24-local

# 2. Levantar Docker
docker-compose up -d

# 3. Migrar
bedrock migrate \
  --sql-file=production.sql \
  --old-prefix=hp2f_ \
  --old-url=https://detodo24.com

# Tiempo: 15-30 minutos
```

### Caso 2: Proyecto Nuevo
```bash
# 1. Crear proyecto completo
bedrock new mi-tienda --with-docker

# 2. Levantar entorno
cd mi-tienda
docker-compose up -d

# 3. Instalar WordPress
bedrock setup

# Tiempo: 10-15 minutos
```

### Caso 3: Gestionar Orden de Plugins
```bash
# Constructor interactivo
bedrock plugins:order-builder

# Sintaxis rápida
> 7-26,44
✓ Agregados 21 plugins

> save
```

---

## 🎯 ESTADO ACTUAL

### Completado ✅
- [x] Comando new con Docker/Acorn/Redis
- [x] Comando migrate automatizado
- [x] Sistema de menús interactivos (Fase 1)
- [x] 17 loaders animados
- [x] Constructor de orden de plugins
- [x] Gestión completa de plugins (10 comandos)
- [x] Gestión de temas y opciones
- [x] Snapshots de BD
- [x] Mecanismos de seguridad
- [x] Soporte DB_PREFIX

### En Progreso 🚧
- [ ] WizardCommand (Fase 2 de menús inteligentes)
- [ ] Mejoras a MigrateCommand (validaciones)
- [ ] StateDetectorService completo

### Planeado 📋
- [ ] BackupCommand completo
- [ ] Testing automatizado (PHPUnit)
- [ ] Health check automático
- [ ] Exportar/importar configuración completa
- [ ] Perfiles de configuración (dev, staging, prod)

---

## 📈 MÉTRICAS

- **Total de archivos**: 67 analizados
- **Líneas de código**: ~8,000
- **Comandos**: 38
- **Servicios**: 6
- **Stubs**: 14
- **Loaders**: 17
- **Documentación**: 29 archivos .md

---

## 🔗 ENLACES RÁPIDOS

- **README.md**: Instalación y comandos básicos
- **MIGRATION_GUIDE.md**: Guía completa de migración
- **DEVELOPMENT.md**: Flujo de desarrollo
- **ARCHITECTURE.md**: Arquitectura completa (67 archivos)
- **SECURITY.md**: Mecanismos de seguridad
- **UX_GUIDELINES.md**: Principios de diseño

---

## 🚀 PRÓXIMA MISIÓN

### Objetivo: Implementar Mejoras a MigrateCommand
1. Validar SQL antes de importar
2. Detectar claves duplicadas
3. Detectar tablas pre-existentes
4. Ofrecer drop automático
5. Modo dry-run

### Objetivo: Completar StateDetectorService
1. Detectar estado completo del proyecto
2. Validar dependencias
3. Sugerir próximos pasos
4. Integrar con WizardCommand

### Objetivo: Implementar WizardCommand (Fase 2)
1. Detección de estado
2. Flujo guiado paso a paso
3. Modo tutorial opcional
4. Sistema de tareas pendientes

---

**Última actualización**: 2025-10-31  
**Último commit**: 069285 - fix: undefined variable currentUrl in setup command
