# TASK-010: Modificar NewCommand para usar Profiles

**Estado**: ✅ Completada
**Fecha Fin**: 2025-10-31 14:59  
**Fase**: 4 - Composer Service  
**Fecha Inicio**: 2025-10-31 14:57

---

## 🎯 Objetivo

Integrar el sistema de profiles en el comando `bedrock new` para que genere proyectos usando profiles.

---

## ✅ Checklist

- [x] Agregar opción `--profile=<name>` a NewCommand
- [x] Si no se especifica, usar profile `default`
- [x] Cargar profile desde ProfileService
- [x] Después de crear proyecto Bedrock, aplicar ComposerService
- [x] Copiar profile a `.bedrock/profile.json` del proyecto
- [x] Ejecutar `composer install` con las nuevas dependencias
- [x] Manejo de errores si profile no existe

---

## 📦 Entregables

1. NewCommand modificado con soporte de profiles
2. Integración completa ProfileService + ComposerService
