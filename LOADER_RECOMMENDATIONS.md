# Recomendaciones para Agregar Loader Animado

## ✅ Ya Implementado

### OptionsCommand
- ✅ `OptionsPullCommand::pullOptionsBatch()` - "Exportando opciones desde WordPress"
- ✅ `OptionsPushCommand::pushOptionsBatch()` - "Importando opciones a WordPress" / "Simulando importación"

### PluginsOrderCommand
- ✅ `PluginsOrderCommand::listPlugins()` - "Consultando WordPress"
- ✅ `PluginsOrderCommand::activateInOrder()` - "Activando plugins"

---

## 🎯 Recomendaciones de Alta Prioridad

### DatabaseCommand
**Operaciones lentas que se beneficiarían del loader:**

1. **searchReplace()** - Línea ~280
   - Mensaje: "Buscando y reemplazando en base de datos"
   - Razón: Puede tardar varios minutos en bases de datos grandes

2. **prefixReplace()** - Línea ~320
   - Mensaje: "Cambiando prefijo de tablas"
   - Razón: Operación crítica que modifica estructura

3. **import()** - Línea ~200
   - Mensaje: "Importando base de datos"
   - Razón: Archivos SQL grandes tardan tiempo

4. **export()** - Línea ~220
   - Mensaje: "Exportando base de datos"
   - Razón: Bases de datos grandes tardan en exportar

5. **reset()** - Línea ~240
   - Mensaje: "Reseteando base de datos"
   - Razón: Operación destructiva que tarda

### DockerCommand
**Operaciones que tardan en completarse:**

1. **up()** - Línea ~160
   - Mensaje: "Levantando contenedores Docker"
   - Razón: Puede tardar 10-30 segundos

2. **rebuild()** - Línea ~220
   - Mensaje: "Reconstruyendo contenedores" / "Compilando imagen Docker"
   - Razón: Build puede tardar varios minutos

3. **down()** - Línea ~180
   - Mensaje: "Deteniendo contenedores Docker"
   - Razón: Puede tardar 5-15 segundos

### SeedCommand
**Operaciones de seeding:**

1. **runSeeder()** - Línea ~60
   - Mensaje: "Ejecutando seeder: {$class}"
   - Razón: Seeders pueden insertar muchos datos

2. **runDatabaseSeeder()** - Línea ~90
   - Mensaje: "Ejecutando todos los seeders"
   - Razón: Múltiples seeders pueden tardar

---

## 🔧 Comandos de Media Prioridad

### PluginsCompressCommand
- **compress()** - Al comprimir plugins grandes
- Mensaje: "Comprimiendo plugin: {$name}"

### ThemesCompressCommand
- **compress()** - Al comprimir temas
- Mensaje: "Comprimiendo tema: {$name}"

### BackupCommand
- **createBackup()** - Al crear backups completos
- Mensaje: "Creando backup del proyecto"

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

## 📊 Priorización

**Alta Prioridad (implementar primero):**
1. DatabaseCommand - searchReplace, prefixReplace, import, export
2. DockerCommand - up, rebuild, down
3. SeedCommand - runSeeder, runDatabaseSeeder

**Media Prioridad:**
4. PluginsCompressCommand
5. ThemesCompressCommand
6. BackupCommand

**Baja Prioridad:**
- Operaciones rápidas (<2 segundos)
- Comandos poco usados
