# TASK-001: Crear Estructura de Directorios y ProfileService

**Estado**: ✅ Completada  
**Fase**: 1 - Infraestructura Base  
**Fecha Inicio**: 2025-10-31 14:29  
**Fecha Fin**: 2025-10-31 14:32

---

## 🎯 Objetivo

Crear la infraestructura base para el sistema de profiles:
1. Directorio `~/.bedrock-cli/` en home del usuario
2. Subdirectorio `profiles/`
3. Archivo `config.json`
4. Servicio `ProfileService.php` con métodos básicos

---

## ✅ Checklist

- [x] Crear método para detectar home del usuario (Windows/Linux/Mac)
- [x] Crear directorio `~/.bedrock-cli/`
- [x] Crear subdirectorio `~/.bedrock-cli/profiles/`
- [x] Crear `~/.bedrock-cli/config.json` con estructura base
- [x] Crear `src/Services/ProfileService.php`
- [x] Implementar `getProfilesPath()`
- [x] Implementar `profileExists($name)`
- [x] Implementar `loadProfile($name)`
- [x] Implementar `saveProfile($name, $data)`
- [x] Implementar `listProfiles()`
- [x] Implementar `deleteProfile($name)`
- [x] Implementar `getConfig()` y `updateConfig()` (bonus)
- [x] Crear `profiles/default.json` en el repo

---

## 📦 Entregables

1. ✅ `src/Services/ProfileService.php` - 140 líneas
2. ✅ `profiles/default.json` - Profile por defecto
3. ✅ Directorio `~/.bedrock-cli/profiles/` (se crea automáticamente)
4. ✅ Archivo `~/.bedrock-cli/config.json` (se crea automáticamente)

---

## 🔧 Funcionalidades Implementadas

### ProfileService
- `getHomeDirectory()`: Detecta home en Windows/Linux/Mac
- `ensureDirectoryStructure()`: Crea estructura automáticamente
- `getProfilesPath()`: Retorna path de profiles
- `profileExists($name)`: Verifica existencia
- `loadProfile($name)`: Carga y valida JSON
- `saveProfile($name, $data)`: Guarda con formato
- `listProfiles()`: Lista con descripciones
- `deleteProfile($name)`: Elimina profile
- `getConfig()`: Lee configuración global
- `updateConfig($data)`: Actualiza configuración

---

## ✅ TASK COMPLETADA
