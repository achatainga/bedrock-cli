# Mecanismos de Seguridad

## Confirmación con Código Aleatorio

Para prevenir ejecución accidental de operaciones destructivas, bedrock-cli implementa un sistema de confirmación con código aleatorio de 6 dígitos.

### Operaciones Protegidas

Las siguientes operaciones requieren confirmación con código:

1. **Database Drop** - Eliminar base de datos
2. **Database Reset** - Resetear base de datos (eliminar todos los datos)
3. **Reinstall** - Reinstalación completa de la aplicación

### Funcionamiento

Cuando ejecutas una operación destructiva, el sistema:

1. Genera un código aleatorio de 6 dígitos (ej: `697789`)
2. Muestra advertencia en rojo con descripción de la acción
3. Solicita que escribas el código exacto
4. Solo procede si el código es correcto

### Ejemplo

```
⚠️  ADVERTENCIA: ACCIÓN DESTRUCTIVA ⚠️
Esta acción ELIMINARÁ PERMANENTEMENTE la base de datos.

Si estás seguro, escribe el código: 697789
Código: 697789
✓ Código correcto. Procediendo...
```

### Características

- **Código aleatorio:** Cada vez que ejecutas la operación, el código cambia
- **No memorizable:** Imposible ejecutar por accidente o por hábito
- **Visible:** El código se muestra claramente para que puedas copiarlo
- **Cancelable:** Si escribes cualquier otra cosa, la operación se cancela

## Comando Reinstall

El comando más destructivo es `bedrock reinstall`, que:

1. ⚠️ Resetea la base de datos (elimina todos los datos)
2. ⚠️ Elimina WordPress (`rm -rf web/wp`)
3. ⚠️ Elimina vendor (`rm -rf vendor`)
4. ✅ Reinstala dependencias (`composer install`)
5. ✅ Rebuild Docker (sin caché)
6. ✅ Reinstala WordPress (con datos guardados)

### Uso

```bash
bedrock reinstall
```

### Datos Guardados

Antes de resetear, el comando intenta guardar:
- URL del sitio (desde `.env`)
- Título del sitio (desde DB)
- Email del admin (desde DB)

Después de reinstalar, WordPress se configura automáticamente con:
- Usuario: `admin`
- Contraseña: `admin`
- Misma URL y título

### Cuándo Usar

- Cuando el proyecto está corrupto y necesitas empezar de cero
- Para testing de instalación limpia
- Cuando quieres resetear todo el entorno de desarrollo

### Cuándo NO Usar

- ❌ En producción (NUNCA)
- ❌ Si tienes datos importantes sin backup
- ❌ Si no estás 100% seguro de lo que haces

## Mejores Prácticas

1. **Siempre haz backup antes** de operaciones destructivas
2. **Lee la advertencia completa** antes de escribir el código
3. **Verifica el entorno** (development, staging, production)
4. **Ten un plan de recuperación** por si algo sale mal
5. **Documenta** por qué necesitas ejecutar la operación

## Deshabilitación (NO RECOMENDADO)

Si por alguna razón necesitas deshabilitar las confirmaciones de seguridad, puedes modificar `SecurityService::confirmDangerousAction()` para que siempre retorne `true`.

**ADVERTENCIA:** Esto elimina toda protección contra ejecución accidental. Solo hazlo en entornos de testing automatizado.
