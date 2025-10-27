# Recomendaciones para Agregar Loader Animado

## ✅ Ya Implementado

### OptionsCommand
- ✅ `OptionsPullCommand::pullOptionsBatch()` - "Exportando opciones desde WordPress"
- ✅ `OptionsPushCommand::pushOptionsBatch()` - "Importando opciones a WordPress" / "Simulando importación"

### PluginsOrderCommand
- ✅ `PluginsOrderCommand::listPlugins()` - "Consultando WordPress"
- ✅ `PluginsOrderCommand::activateInOrder()` - "Activando plugins"

### DatabaseCommand
- ✅ `create()` - "Creando base de datos"
- ✅ `drop()` - "Eliminando base de datos"
- ✅ `import()` - "Importando base de datos desde {$file}"
- ✅ `export()` - "Exportando base de datos a {$file}"
- ✅ `reset()` - "Reseteando base de datos"
- ✅ `searchReplace()` - "Buscando '{$search}' y reemplazando por '{$replace}'"
- ✅ `prefixReplace()` - "Cambiando prefijo de '{$oldPrefix}' a '{$newPrefix}'"
- ✅ `searchReplaceDirect()` - "Buscando '{$search}' y reemplazando por '{$replace}'"
- ✅ `prefixReplaceDirect()` - "Cambiando prefijo de '{$oldPrefix}' a '{$newPrefix}'"

### DockerCommand
- ✅ `down()` - "Deteniendo contenedores Docker"
- ✅ `restart()` - "Reiniciando contenedores Docker"
- ❌ `up()` - No implementado (usa passthru, no Process)
- ❌ `rebuild()` - No implementado (usa passthru, no Process)

### SeedCommand
- ✅ `runSeeder()` - "🌱 Ejecutando seeder: {$class}"
- ✅ Fresh mode - "Reseteando base de datos"

---

## ❌ No Implementable

### DockerCommand - up() y rebuild()
**Razón**: Usan `passthru()` en lugar de `Process`, lo que impide el loader animado.
- `up()` usa: `passthru('docker-compose up -d')`
- `rebuild()` usa: `passthru('docker-compose build --no-cache')`

**Alternativa**: Mantener output en tiempo real con passthru (más útil para builds largos)

### PluginsCompressCommand y ThemesCompressCommand
**Razón**: Usan `ZipService::compress()` que no retorna `Process`, sino boolean.
**Alternativa**: Refactorizar ZipService para usar Process (baja prioridad)

---

## 🚧 Pendientes de Implementación

### BackupCommand
**Estado**: Comando no implementado aún
- Cuando se implemente, agregar loader para operaciones de backup

---

## 📝 Patrón de Implementación

### Método runWithLoader (copiar a cada comando)

```php
protected function runWithLoader(Process $process, OutputInterface $output, string $message): void
{
    $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    $frameIndex = 0;
    
    $process->start();
    
    while ($process->isRunning()) {
        $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
        $frameIndex = ($frameIndex + 1) % count($frames);
        usleep(80000); // 80ms
    }
    
    $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
}
```

### Uso

**Antes:**
```php
$process = $wpcli->dbExport($file);
$process->run();
```

**Después:**
```php
$process = $wpcli->dbExport($file);
$this->runWithLoader($process, $output, 'Exportando base de datos');
```

---

## 🎨 Mensajes Sugeridos en Español

### Database
- "Exportando base de datos"
- "Importando base de datos"
- "Reseteando base de datos"
- "Buscando y reemplazando en base de datos"
- "Cambiando prefijo de tablas"
- "Ejecutando query SQL"

### Docker
- "Levantando contenedores Docker"
- "Deteniendo contenedores Docker"
- "Reiniciando contenedores Docker"
- "Reconstruyendo contenedores"
- "Compilando imagen Docker"

### Seeders
- "Ejecutando seeder: {$class}"
- "Ejecutando todos los seeders"
- "Reseteando base de datos"

### Plugins/Themes
- "Comprimiendo plugin: {$name}"
- "Comprimiendo tema: {$name}"
- "Descomprimiendo archivos"

---

## ⚠️ Consideraciones

1. **Solo usar en operaciones >2 segundos**: No agregar loader a operaciones instantáneas
2. **Mensajes descriptivos**: El usuario debe saber qué está pasando
3. **Consistencia**: Usar el mismo patrón en todos los comandos
4. **Process->start()**: Requerido para animación asíncrona
5. **Limpieza de línea**: El `\r` sobrescribe la línea anterior

---

## 📊 Estado de Implementación

**✅ Completado (100%):**
- DatabaseCommand: 9/9 operaciones con loader
- DockerCommand: 2/2 operaciones implementables (down, restart)
- SeedCommand: 2/2 operaciones con loader
- OptionsCommand: 2/2 operaciones con loader
- PluginsOrderCommand: 2/2 operaciones con loader

**❌ No Implementable:**
- DockerCommand: up, rebuild (usan passthru)
- PluginsCompressCommand (usa ZipService sin Process)
- ThemesCompressCommand (usa ZipService sin Process)

**🚧 Pendiente:**
- BackupCommand (comando no implementado)

**Total: 17 loaders implementados en 5 comandos**
