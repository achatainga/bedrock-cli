# 🎯 DISEÑO: Sistema de Menús Inteligentes Bedrock CLI

**Fecha**: 2025-10-28
**Objetivo**: Crear un sistema de menús guiados que cualquiera pueda usar sin conocimiento previo

---

## 🧠 FILOSOFÍA DEL DISEÑO

### Principios:
1. **Guiado por Contexto**: El menú detecta el estado del proyecto y sugiere el siguiente paso
2. **Educativo**: Cada opción explica qué hace, por qué y cuándo usarla
3. **Validación Inteligente**: Detecta dependencias faltantes y ofrece instalarlas
4. **Orden Lógico**: Los pasos se presentan en el orden correcto de ejecución
5. **Reversible**: Toda acción tiene su opción de deshacer

---

## 📊 ESTRUCTURA DE MENÚS PROPUESTA

### MENÚ PRINCIPAL (Nivel 0)
```
╔═══════════════════════════════════════╗
║  BEDROCK CLI - Menú Principal         ║
╚═══════════════════════════════════════╝

Estado del Proyecto: ✓ Docker corriendo | ⚠ WordPress no instalado

 1. 🚀 Inicio Rápido (Wizard guiado)
 2. 📦 Setup (Configuración paso a paso)
 3. 🐳 Docker (Gestión de contenedores)
 4. 🗄️  Database (Gestión de base de datos)
 5. ⚙️  Options (Gestión de opciones WP)
 6. 🔌 Plugins (Gestión de plugins)
 7. 🎨 Themes (Gestión de temas)
 8. 🌿 Acorn (Gestión de Roots Acorn)
 9. 💾 Backup (Crear backup)
10. 🔄 Migrate (Migrar base de datos)
11. 🏥 Doctor (Verificar dependencias)
12. ℹ️  Info (Información del proyecto)
 0. Salir
```

---

## 🚀 MENÚ 1: INICIO RÁPIDO (WIZARD)

**Propósito**: Guiar al usuario desde cero hasta tener WordPress funcionando

```
╔═══════════════════════════════════════╗
║  Inicio Rápido - Wizard Guiado        ║
╚═══════════════════════════════════════╝

Este wizard te guiará paso a paso para configurar tu proyecto Bedrock.

Pasos a realizar:
 ✓ 1. Verificar Docker
 ⏳ 2. Iniciar contenedores
 ⏳ 3. Instalar WordPress
 ⏳ 4. Configurar Acorn (opcional)
 ⏳ 5. Activar plugins básicos

¿Deseas continuar? (y/n):
```

**Flujo Inteligente**:
- Detecta qué pasos ya están completados
- Salta pasos innecesarios
- Pregunta confirmación antes de cada paso
- Explica qué hace cada paso

---

## 📦 MENÚ 2: SETUP (PASO A PASO)

```
╔═══════════════════════════════════════╗
║  Setup - Configuración Paso a Paso    ║
╚═══════════════════════════════════════╝

Estado Actual:
 ✓ Docker: Corriendo
 ✗ WordPress: No instalado
 ✗ Acorn: No configurado

Selecciona un paso:
 1. Verificar requisitos del sistema
 2. Iniciar/detener Docker
 3. Instalar WordPress
 4. Configurar Acorn
 5. Importar base de datos
 6. Configurar plugins
 7. Ver resumen del proyecto
 0. Volver al menú principal
```

---

## 🌿 MENÚ 8: ACORN (REDISEÑADO COMPLETO)

```
╔═══════════════════════════════════════╗
║  Acorn - Gestión Completa             ║
╚═══════════════════════════════════════╝

ℹ️  ¿Qué es Acorn?
Roots Acorn trae componentes de Laravel a WordPress:
• Blade templates (mejor que PHP templates)
• Service Container (inyección de dependencias)
• Eloquent ORM (mejor que WP_Query)
• Asset management (Vite/Mix)

Estado Actual:
 Paquete Composer: ✗ No instalado
 Storage: ✗ No inicializado
 Configs: ✗ No publicados

┌─────────────────────────────────────┐
│ INSTALACIÓN                         │
├─────────────────────────────────────┤
│ 1. Instalar paquete (composer)      │
│ 2. Inicializar storage              │
│ 3. Publicar configs                 │
│ 4. Instalar completo (1+2+3)        │
├─────────────────────────────────────┤
│ MANTENIMIENTO                       │
├─────────────────────────────────────┤
│ 5. Limpiar cache                    │
│ 6. Regenerar configs                │
│ 7. Ver estado detallado             │
├─────────────────────────────────────┤
│ DESINSTALACIÓN                      │
├─────────────────────────────────────┤
│ 8. Eliminar configs y storage       │
│ 9. Desinstalar paquete (composer)   │
│ 10. Desinstalar completo (8+9)      │
├─────────────────────────────────────┤
│ AYUDA                               │
├─────────────────────────────────────┤
│ 11. ¿Cuándo usar Acorn?             │
│ 12. Ventajas y desventajas          │
│ 13. Ejemplos de uso                 │
└─────────────────────────────────────┘
 0. Volver
```

---

## 💡 5 MEJORAS QUE NO PENSASTE (ACORN)

### 1. **Validación Inteligente de Dependencias**
```php
// Antes de publicar configs, verificar:
if (!isAcornInstalled()) {
    echo "⚠️  Acorn no está instalado en composer.json\n";
    echo "¿Deseas instalarlo ahora? (y/n): ";
    if (confirm()) {
        exec('composer require roots/acorn');
    }
}
```

### 2. **Estado Visual del Proyecto**
```
Estado de Acorn:
┌──────────────────────────────────────┐
│ Paquete:  ✓ roots/acorn v5.0.5      │
│ Storage:  ✓ Inicializado (2.3 MB)   │
│ Configs:  ✓ 9 archivos publicados   │
│ Cache:    ⚠️  45 MB (limpiar?)       │
│ MU Plugin: ✓ acorn-boot.php activo  │
└──────────────────────────────────────┘
```

### 3. **Detección de Conflictos**
```php
// Detectar si hay configs personalizados
if (hasCustomConfigs()) {
    echo "⚠️  Detectados configs personalizados:\n";
    echo "  • config/app.php (modificado hace 2 días)\n";
    echo "  • config/view.php (modificado hace 1 hora)\n";
    echo "\n¿Deseas hacer backup antes de regenerar? (y/n): ";
}
```

### 4. **Modo de Aprendizaje**
```
Modo Tutorial: ACTIVADO

Estás por inicializar storage de Acorn.

📚 ¿Qué hace esto?
   Crea las carpetas necesarias para que Acorn funcione:
   • storage/framework/cache/ - Cache de aplicación
   • storage/framework/views/ - Templates compilados
   • storage/logs/ - Logs de la aplicación

💡 ¿Cuándo necesitas esto?
   Siempre que uses Acorn. Es el primer paso después de instalarlo.

⚠️  ¿Qué puede salir mal?
   Nada grave. Si falla, solo reinténtalo.

¿Continuar? (y/n):
```

### 5. **Rollback Automático**
```php
// Antes de desinstalar, crear snapshot
echo "Creando snapshot de seguridad...\n";
createSnapshot('before-acorn-uninstall');

// Si algo falla, ofrecer restaurar
if ($error) {
    echo "⚠️  Error durante desinstalación\n";
    echo "¿Deseas restaurar el snapshot? (y/n): ";
}
```

---

## 🚀 10 IDEAS INNOVADORAS (UNA MILLA EXTRA)

### 1. **Sistema de Tareas Pendientes**
```
╔═══════════════════════════════════════╗
║  Tareas Pendientes (3)                ║
╚═══════════════════════════════════════╝

 ⚠️  Alta Prioridad:
    • WordPress no está instalado
    • Acorn instalado pero no configurado

 ℹ️  Recomendaciones:
    • Cache de Acorn ocupa 45 MB (limpiar?)

¿Ver detalles? (y/n):
```

### 2. **Historial de Acciones**
```
Últimas 5 acciones:
 1. [16:30] Acorn: Configs publicados ✓
 2. [16:28] Acorn: Storage inicializado ✓
 3. [16:25] Docker: Contenedores iniciados ✓
 4. [16:20] Setup: WordPress instalado ✓
 5. [16:15] Doctor: Verificación completada ✓

Ver historial completo: bedrock history
```

### 3. **Perfiles de Configuración**
```
Selecciona un perfil:
 1. Desarrollo (debug ON, cache OFF)
 2. Staging (debug OFF, cache ON)
 3. Producción (optimizado, cache ON)
 4. Testing (debug ON, cache OFF, DB temporal)
 5. Personalizado

Perfil actual: Desarrollo
```

### 4. **Comparador de Estados**
```
Comparar con:
 1. Estado inicial (commit a42a709)
 2. Último snapshot (before-acorn-uninstall)
 3. Otro proyecto Bedrock
 4. Configuración recomendada

Diferencias encontradas:
 • config/app.php: 15 líneas diferentes
 • storage/: 2.3 MB vs 45 MB
```

### 5. **Asistente de Troubleshooting**
```
🔍 Detectando problemas...

Problemas encontrados:
 ⚠️  Cache de Acorn muy grande (45 MB)
    Solución: Limpiar cache
    Comando: bedrock acorn → opción 5

 ⚠️  WordPress no responde en localhost:82
    Posibles causas:
    1. Docker no está corriendo
    2. Puerto 82 ocupado
    3. Nginx no configurado
    
    ¿Ejecutar diagnóstico automático? (y/n):
```

### 6. **Modo Experto vs Principiante**
```
Modo actual: Principiante

En modo Principiante:
 • Explicaciones detalladas
 • Confirmaciones antes de cada acción
 • Sugerencias de siguiente paso

En modo Experto:
 • Menús compactos
 • Ejecución directa
 • Atajos de teclado

¿Cambiar a modo Experto? (y/n):
```

### 7. **Generador de Documentación**
```
Generar documentación del proyecto:
 1. README.md (setup instructions)
 2. ARCHITECTURE.md (estructura)
 3. DEPLOYMENT.md (deploy guide)
 4. TROUBLESHOOTING.md (problemas comunes)
 5. Todo lo anterior

Formato:
 • Markdown
 • HTML
 • PDF
```

### 8. **Integración con Git**
```
Estado de Git:
 Branch: develop
 Commits sin push: 3
 Archivos sin commit: 5

Acciones rápidas:
 1. Commit rápido (mensaje auto)
 2. Commit con mensaje
 3. Push a origin
 4. Ver diff
 5. Crear branch
```

### 9. **Health Check Automático**
```
╔═══════════════════════════════════════╗
║  Health Check - Estado del Proyecto   ║
╚═══════════════════════════════════════╝

Puntuación: 85/100 ⭐⭐⭐⭐

✓ Docker: Corriendo (100%)
✓ WordPress: Instalado y funcionando (100%)
✓ Acorn: Configurado correctamente (100%)
⚠️ Cache: Muy grande, limpiar (-10%)
⚠️ Logs: 150 MB, rotar (-5%)

Recomendaciones:
 1. Limpiar cache de Acorn
 2. Rotar logs antiguos
 3. Actualizar dependencias

¿Aplicar recomendaciones automáticamente? (y/n):
```

### 10. **Exportar/Importar Configuración**
```
Exportar configuración:
 1. Configuración completa (.bedrock-config.json)
 2. Solo Acorn
 3. Solo Docker
 4. Solo WordPress

Importar configuración:
 1. Desde archivo
 2. Desde otro proyecto
 3. Desde template predefinido
 4. Desde URL (GitHub Gist)

Esto permite:
 • Replicar setup en otro proyecto
 • Compartir configuración con equipo
 • Backup de configuración
```

---

## 🎯 IMPLEMENTACIÓN PROPUESTA

### Fase 1: Menú Acorn Mejorado (Inmediato)
- Validación de paquete Composer
- Opciones de instalar/desinstalar paquete
- Estado visual detallado
- Sección de ayuda integrada

### Fase 2: Wizard de Inicio Rápido
- Detección de estado
- Flujo guiado paso a paso
- Modo tutorial opcional

### Fase 3: Sistema de Tareas Pendientes
- Detección automática de problemas
- Sugerencias contextuales
- Priorización de tareas

### Fase 4: Características Avanzadas
- Perfiles de configuración
- Health check automático
- Historial de acciones
- Exportar/importar configs

---

## 📋 ESTRUCTURA DE ARCHIVOS PROPUESTA

```
src/Commands/
├── MainMenuCommand.php (rediseñado)
├── WizardCommand.php (nuevo)
├── SetupMenuCommand.php (nuevo - desglose de setup)
├── AcornCommand.php (mejorado)
├── InfoCommand.php (nuevo)
├── HealthCheckCommand.php (nuevo)
└── Services/
    ├── StateDetector.php (detecta estado del proyecto)
    ├── DependencyValidator.php (valida dependencias)
    ├── TutorialService.php (modo aprendizaje)
    └── SnapshotService.php (backups automáticos)
```

---

## 🎨 MEJORAS DE UX

### Colores y Símbolos:
- ✓ Verde: Completado
- ⚠️ Amarillo: Advertencia
- ✗ Rojo: Error/Faltante
- ℹ️ Azul: Información
- 🚀 Acción rápida
- 📚 Educativo

### Feedback Visual:
```
Instalando Acorn...
[████████████████████░░] 85% (45s restantes)

✓ Paquete descargado
✓ Dependencias resueltas
⏳ Generando autoload...
```

### Confirmaciones Inteligentes:
```
Esta acción eliminará:
 • 9 archivos de configuración
 • Carpeta storage/ (2.3 MB)
 • Cache de Acorn (45 MB)

Total a eliminar: 47.3 MB

¿Estás seguro? Escribe 'CONFIRMAR' para continuar:
```

---

**Próximo Paso**: Implementar Fase 1 (Menú Acorn Mejorado)
