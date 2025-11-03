# TASK-003: Comando profile:create (Wizard Básico)

**Estado**: ✅ Completada
**Fecha Fin**: 2025-10-31 14:50  
**Fase**: 2 - Comandos de Gestión de Profiles  
**Fecha Inicio**: 2025-10-31 14:48

---

## 🎯 Objetivo

Crear comando `profile:create` con wizard interactivo básico que permita crear profiles sin necesidad de editar JSON manualmente.

---

## ✅ Checklist

- [x] Crear `src/Commands/Profile/CreateCommand.php`
- [x] Wizard que pida: nombre, descripción
- [x] Wizard que pida: plugins públicos (lista manual)
- [x] Wizard que pida: URL repo premium (opcional)
- [x] Wizard que pida: path plugins custom (opcional)
- [x] Wizard que pida: nombre del tema
- [x] Wizard que pida: si tema es premium
- [x] Guardar en `~/.bedrock-cli/profiles/{name}.json`
- [x] Validar que no exista ya
- [x] Registrar comando en aplicación

---

## 📦 Entregables

1. `src/Commands/Profile/CreateCommand.php`
2. Comando funcional `bedrock profile:create <name>`
