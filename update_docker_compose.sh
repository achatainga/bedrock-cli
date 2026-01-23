#!/bin/bash

# Script para reemplazar docker-compose con detección automática
cd /home/achat/code/bedrock-cli

# Archivos que necesitan el trait
files_with_trait=(
    "src/Commands/Setup/NewCommand.php"
    "src/Commands/Setup/SetupCommand.php"
    "src/Commands/Docker/UpdateConfigCommand.php"
)

# Agregar use y trait a archivos específicos
for file in "${files_with_trait[@]}"; do
    if [ -f "$file" ]; then
        # Agregar use statement después de otros uses
        sed -i '/^use.*$/a use BedrockCli\\Traits\\DockerComposeTrait;' "$file"
        
        # Agregar trait después de otros traits
        sed -i '/use.*Trait;$/a \    use DockerComposeTrait;' "$file"
    fi
done

# Reemplazar todas las ocurrencias de docker-compose en archivos PHP
find . -name "*.php" -type f -exec sed -i 's/docker-compose/\$this->getDockerComposeCommand()/g' {} \;

# Casos especiales para comandos con argumentos
find . -name "*.php" -type f -exec sed -i 's/passthru(\x27\$this->getDockerComposeCommand() \([^"]*\)\x27/\$this->dockerComposeExec(\x27\1\x27/g' {} \;
find . -name "*.php" -type f -exec sed -i 's/passthru("\$this->getDockerComposeCommand() \([^"]*\)"/\$this->dockerComposeExec("\1"/g' {} \;

echo "✓ Reemplazo completado"