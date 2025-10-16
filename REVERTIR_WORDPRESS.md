# Revertir WordPress a Versión de Composer

## Problema
Si ejecutaste `bedrock update` y WordPress se actualizó (ej. 6.8.1 → 6.8.3), pero `composer.json` especifica una versión anterior, necesitas revertir.

## Solución

### 1. Verificar versión actual
```bash
docker-compose exec web wp core version
```

### 2. Verificar versión en composer.json
```bash
grep "roots/wordpress" composer.json
# Debería mostrar: "roots/wordpress": "6.8.1"
```

### 3. Reinstalar WordPress desde Composer
```bash
# Eliminar WordPress actual
rm -rf web/wp

# Reinstalar desde Composer
composer install --no-dev

# O forzar reinstalación
composer update roots/wordpress --with-dependencies
```

### 4. Verificar versión reinstalada
```bash
docker-compose exec web wp core version
# Debería mostrar: 6.8.1
```

## Prevención

El comando `bedrock update` ha sido **DESHABILITADO** para evitar este problema.

**Razón:** Composer debe ser la única fuente de verdad para versiones de WordPress, plugins y temas.

**Para actualizar WordPress:**
```bash
# 1. Editar composer.json
"roots/wordpress": "6.8.3"

# 2. Actualizar con Composer
composer update roots/wordpress

# 3. Verificar
docker-compose exec web wp core version
```

## Notas Importantes

- ✅ **Correcto:** Gestionar versiones con `composer.json`
- ❌ **Incorrecto:** Actualizar desde WP Admin o `wp core update`
- ⚠️ **Advertencia:** Actualizaciones manuales causan inconsistencias

## Comandos Seguros

```bash
# Ver versión instalada
wp core version

# Ver actualizaciones disponibles (solo informativo)
wp core check-update

# NO ejecutar (deshabilitado):
# wp core update
# bedrock update
```
