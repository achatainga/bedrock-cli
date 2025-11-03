# TASK-20251031-1239: Mejorar comandos setup/install/info

**Propietario**: Orquestador
**Asignado a**: Constructor
**Estado**: [✅] Completado
**Fecha**: 2025-10-31 12:39

## 1. Objetivo
Mejorar bedrock-cli eliminando redundancias y extendiendo funcionalidad de setup e info.

## 2. Alcance
### Dentro del Alcance:
- [✅] Eliminar comando `install` (redundante con setup)
- [✅] Mejorar `setup` para gestión completa:
  - [✅] Instalar WordPress
  - [✅] Activar tema (detectar disponibles, preguntar)
  - [✅] Gestionar plugins (listar, activar, orden)
- [✅] Extender `info` para mostrar:
  - [✅] Estado de temas (activo, disponibles)
  - [✅] Estado de plugins (activos, inactivos, orden)
  - [✅] Sugerencias de configuración

### Fuera del Alcance:
- Instalación de plugins desde repositorio ❌
- Configuración avanzada de plugins ❌

## 3. Propuesta de Implementación

### A) Gestión de Plugins - Opción C (Recomendada)
Archivo de configuración `config/setup.json`:
```json
{
  "theme": "twentytwentyfive",
  "plugins": {
    "acorn": { "active": true, "order": 1 },
    "redis-cache": { "active": true, "order": 2 }
  }
}
```

Fallback a modo interactivo si no existe.

### B) Flujo de setup mejorado
1. Detectar estado actual
2. Instalar WordPress (si no existe)
3. Listar temas disponibles → preguntar cuál activar
4. Listar plugins disponibles → preguntar cuáles activar
5. Configurar orden de activación
6. Aplicar configuración
7. Mostrar resumen

### C) Comando info extendido
```
Estado de WordPress:
  ✓ WordPress instalado
  ✓ Acorn configurado

Tema Activo:
  • twentytwentyfive (v1.3)
  
Temas Disponibles:
  - twentytwentyfour
  - twentytwentythree

Plugins Activos (2):
  1. acorn (v5.0.5)
  2. redis-cache (v2.7.0)

Plugins Inactivos (0):
  (ninguno)

Próximos Pasos Sugeridos:
  1. Activar tema personalizado
  2. Configurar Redis
```

## 4. Criterios de Aceptación
- [✅] Comando `install` eliminado del menú
- [✅] `setup` gestiona tema y plugins
- [✅] `info` muestra estado completo de temas/plugins
- [✅] Commit y push realizados (9211913)
- [✅] Actualizado en detodo24-alpha
- [ ] Documentación actualizada (README, DEVELOPMENT)

## 5. Meta-Información
- **Coste de Tokens Estimado**: Medio
- **Prioridad**: Alta
- **Tiempo Estimado**: 2-3 horas
