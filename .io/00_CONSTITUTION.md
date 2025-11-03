# CONSTITUTION - bedrock-cli

## Principios Fundamentales

1. **Standalone First:** Este paquete es independiente, NO parte de ningún proyecto específico
2. **Composer Native:** Distribución exclusiva vía Composer
3. **Interactive UX:** Menús interactivos como interfaz principal
4. **CLI Fallback:** Todos los comandos accesibles vía flags para scripting
5. **Bedrock Agnostic:** Compatible con cualquier instalación Roots Bedrock

## Reglas de Desarrollo

### Comandos
- Todo comando debe tener modo interactivo Y modo CLI
- Confirmaciones críticas obligatorias (drop DB, delete theme, etc.)
- Soporte bilingüe español/inglés en confirmaciones
- Output con colores para mejor UX

### Menús
- Opción 0 siempre es navegación (Volver/Salir)
- Opción 0 siempre en última posición
- Loop infinito en submenús hasta que usuario elija volver
- Cursor hide para ocultar echo de input

### Servicios
- Separación clara: DockerService, WpCliService
- Métodos atómicos y reutilizables
- Manejo de errores con excepciones
- Logging de operaciones críticas

### Seguridad
- Nunca hardcodear credenciales
- Validar inputs de usuario
- Sanitizar comandos shell
- Confirmar operaciones destructivas

## Convenciones de Código

- PSR-12 coding standard
- Type hints estrictos
- Documentación PHPDoc completa
- Nombres descriptivos en español para UX, inglés para código

## Versionado

- Semantic Versioning 2.0.0
- CHANGELOG actualizado en cada release
- Tags Git para releases
- Rama develop para desarrollo, main para stable
