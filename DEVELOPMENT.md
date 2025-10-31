# Guía de Desarrollo - Bedrock CLI

## 🚀 Flujo de Desarrollo

### Estructura del Proyecto

```
c:/code/
├── bedrock-cli/              # Este repositorio
├── my-project/               # Proyecto que usa bedrock-cli
└── custom-utilities/         # Scripts de automatización
    └── bedrock-cli-push.sh
```

### Flujo Automatizado

```bash
# Desde cualquier lugar
bash c:/code/custom-utilities/bedrock-cli-push.sh "feat: Mi cambio"

# O desde my-project
cd c:/code/my-project
composer bedrock-push
```

**El script hace:**
1. ✅ Commit en `bedrock-cli`
2. ✅ Push a GitHub (rama `develop`)
3. ✅ `composer update` en `my-project`

### Flujo Manual

```bash
# 1. Editar código en bedrock-cli
cd c:/code/bedrock-cli
# ... hacer cambios ...

# 2. Commit y push
git add .
git commit -m "feat: Mi cambio"
git push origin develop

# 3. Actualizar en my-project
cd c:/code/my-project
composer update roots/bedrock-cli

# 4. Probar
vendor/bin/bedrock list
```

## 🧪 Testing

### Pruebas sin Bedrock

```bash
cd c:/code/bedrock-cli
php bin/bedrock list
php bin/bedrock docker --help
```

### Pruebas con Bedrock

```bash
cd c:/code/my-project
vendor/bin/bedrock docker --up
vendor/bin/bedrock db --create
vendor/bin/bedrock install
```

## 📝 Comandos Disponibles

### Implementados ✅
- `bedrock docker` - Menú interactivo + opciones (--up, --down, --restart, --status)
- `bedrock db` - Menú interactivo + opciones (--create, --import, --export)
- `bedrock install` - Instalación interactiva de WordPress

### Pendientes ⏳
- `bedrock plugins` - Gestión de plugins
- `bedrock themes` - Gestión de temas
- `bedrock backup` - Backup completo
- `bedrock update` - Actualización del sistema

## 🏗️ Arquitectura

```
src/
├── Application.php           # App principal Symfony Console
├── Commands/                 # Comandos
│   ├── DockerCommand.php     # ✅ Completo
│   ├── DatabaseCommand.php   # ✅ Completo
│   ├── InstallCommand.php    # ✅ Completo
│   ├── PluginsCommand.php    # ⏳ Esqueleto
│   ├── ThemesCommand.php     # ⏳ Esqueleto
│   ├── BackupCommand.php     # ⏳ Esqueleto
│   └── UpdateCommand.php     # ⏳ Esqueleto
└── Services/                 # Servicios
    ├── DockerService.php     # Wrapper docker-compose
    └── WpCliService.php      # Wrapper WP-CLI
```

## 🔧 Configuración SSH

El archivo `~/.ssh/config` debe contener:

```
Host github.com
    HostName github.com
    User git
    IdentityFile C:/Users/achat/.ssh/github
    IdentitiesOnly yes
    PubkeyAuthentication yes
```

## 📦 Como Paquete Composer

En `my-project/composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/achatainga/bedrock-cli.git"
    }
  ],
  "require-dev": {
    "roots/bedrock-cli": "dev-develop"
  }
}
```

## 🎯 Próximos Pasos

1. **Implementar PluginsCommand**
   - Descomprimir ZIPs
   - Activar plugins
   - Listar plugins

2. **Implementar ThemesCommand**
   - Descomprimir ZIPs
   - Activar temas
   - Listar temas

3. **Implementar BackupCommand**
   - Backup de DB
   - Backup de uploads
   - Backup completo

4. **Implementar UpdateCommand**
   - Update WordPress core
   - Update plugins
   - Update temas

5. **Testing**
   - Unit tests
   - Integration tests
   - CI/CD con GitHub Actions

## 📚 Referencias

- [Symfony Console](https://symfony.com/doc/current/components/console.html)
- [Roots Bedrock](https://roots.io/bedrock/)
- [WP-CLI](https://wp-cli.org/)
