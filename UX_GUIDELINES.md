# Guía de UX para bedrock-cli

## Principios Fundamentales

### 1. Exploración Sin Miedo
**Regla**: El usuario debe poder navegar por TODAS las opciones sin ejecutar cambios irreversibles.

**Implementación**:
- ✅ Siempre ofrecer opción "Ver" o "Listar" (solo lectura) antes de acciones destructivas
- ✅ Incluir "(solo lectura)" o "(sin cambios)" en descripciones
- ✅ Opción "Volver" siempre disponible con mensaje "(sin cambios)"
- ✅ Confirmaciones explícitas para acciones destructivas

**Ejemplo**:
```
[1] Ver Estado - Listar plugins y su orden (solo lectura)
[2] Guardar Orden - Capturar secuencia actual
[3] Aplicar Orden - Activar plugins según configuración
[0] Volver (sin cambios)
```

### 2. Feedback Visual Claro

**Colores Estándar**:
- `<fg=cyan>` - Información, títulos, headers
- `<fg=green>` - Acciones seguras, éxito
- `<fg=yellow>` - Advertencias, acciones que modifican
- `<fg=red>` - Peligro, acciones destructivas, errores
- `<fg=magenta>` - Opciones especiales, configuraciones
- `<comment>` - Ayuda, explicaciones, contexto

**Iconos**:
- ✓ - Éxito, completado
- ○ - Inactivo, deshabilitado
- ⊘ - Sin cambios, omitido
- ✗ - Error, fallido
- → - Acción pendiente (dry-run)
- ↳ - Dependencia, relación
- 📦 - Paquete, plugin
- 📄 - Archivo, configuración
- 🚀 - Ejecución, activación

### 3. Indicadores de Progreso

**Para operaciones >2 segundos**:
```php
$output->write('<comment>Obteniendo estado de plugins</comment> ');
$this->showSpinner($output);
// ... operación ...
$output->write("\r<comment>Obteniendo estado de plugins</comment> <info>✓</info>\n");
```

**Spinner frames**: ⠋ ⠙ ⠹ ⠸ ⠼ ⠴ ⠦ ⠧ ⠇ ⠏

### 4. Mensajes Descriptivos

**Antes** ❌:
```
[5] Order - Guardar orden de activación
```

**Después** ✅:
```
[5] Orden de Activación - Gestionar secuencia de carga

Controla el orden en que WordPress activa los plugins.
Importante para resolver dependencias entre plugins.
```

### 5. Estructura de Menús

**Patrón estándar**:
```
╔═══════════════════════════════════════╗
║        Título del Menú             ║
╚═══════════════════════════════════════╝

[Descripción breve del propósito]
[Contexto adicional si es necesario]

[1] Opción Segura - Descripción (solo lectura)
[2] Opción Modificadora - Descripción clara
[3] Opción Destructiva - Descripción con advertencia
[0] Volver (sin cambios)
```

### 6. Confirmaciones

**Para acciones destructivas**:
```php
$output->writeln('<fg=red;options=bold>⚠️  ADVERTENCIA: Acción irreversible</>');
$output->writeln('<fg=yellow>Esto eliminará permanentemente...</>');
$output->writeln('');

$question = new Question('<fg=red>Escribe "CONFIRMAR" para continuar:</> ');
$confirmation = $helper->ask($input, $output, $question);

if ($confirmation !== 'CONFIRMAR') {
    $output->writeln('<comment>Operación cancelada</comment>');
    return;
}
```

### 7. Gestión de Archivos/Configuraciones

**Patrón para selección de archivos**:
1. Listar archivos con metadata (tamaño, fecha)
2. Al seleccionar archivo:
   - Ver Contenido (solo lectura)
   - Aplicar/Usar (con confirmación)
   - Hacer Predeterminado (opcional)
   - Volver (sin cambios)

### 8. Mensajes de Estado

**Durante operación**:
```
Activando plugins ⠋
```

**Después de operación**:
```
✓ Activación completa
  ✓ plugin-1
  ✓ plugin-2
  ⊘ plugin-3 (ya activo)
  ✗ plugin-4: Error al activar
```

### 9. Ayuda Contextual

**Siempre incluir**:
- Leyendas de símbolos
- Comandos alternativos
- Rutas de archivos relevantes
- Próximos pasos sugeridos

**Ejemplo**:
```
Leyenda: ✓ = Activo | ○ = Inactivo | [Número] = Orden de carga

Para guardar este orden: plugins:order save
📄 Archivo de configuración: config/plugins/activation-order.json
```

### 10. Navegación Consistente

**Teclas estándar**:
- `0` - Siempre "Volver" o "Salir"
- `1` - Generalmente opción de "Ver" o "Listar" (segura)
- Números ascendentes - De menos a más destructivo

**Orden de opciones**:
1. Opciones de solo lectura (cyan)
2. Opciones de modificación (green/yellow)
3. Opciones destructivas (red)
0. Volver/Salir

## Checklist de Implementación

Al crear un nuevo comando/menú, verificar:

- [ ] ¿Hay opción de solo lectura para explorar?
- [ ] ¿Los mensajes son descriptivos y claros?
- [ ] ¿Las acciones destructivas tienen confirmación?
- [ ] ¿Hay indicador de progreso para operaciones lentas?
- [ ] ¿Los colores siguen el estándar?
- [ ] ¿Hay opción "Volver (sin cambios)"?
- [ ] ¿Los iconos son consistentes?
- [ ] ¿Hay ayuda contextual?
- [ ] ¿Los errores son informativos?
- [ ] ¿El usuario puede cancelar en cualquier momento?

## Ejemplos de Referencia

### Menú Bien Diseñado
Ver: `PluginsOrderMenuCommand.php`, `OptionsCommand.php`

### Indicadores de Progreso
Ver: `PluginsOrderCommand.php` (showSpinner)

### Confirmaciones
Ver: `PluginsCommand.php` (deletePluginFolder)

### Gestión de Archivos
Ver: `OptionsManageCommand.php`, `PluginsOrderMenuCommand.php`
