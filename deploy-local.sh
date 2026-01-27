#!/bin/bash

# Script de deploy automático local para bedrock-cli
# Uso: ./deploy-local.sh "mensaje del commit"

set -e

# Verificar que se pasó un mensaje
if [ -z "$1" ]; then
    echo "❌ Error: Debes proporcionar un mensaje de commit"
    echo "Uso: $0 \"mensaje del commit\""
    exit 1
fi

MESSAGE="$1"

# Detectar si estamos en un repositorio git
if [ ! -d ".git" ]; then
    echo "❌ Error: No estás en un repositorio git"
    exit 1
fi

# Obtener información del repositorio
CURRENT_BRANCH=$(git branch --show-current)
COMPOSER_PACKAGE="achatainga/bedrock-cli"

echo "🚀 Iniciando deploy local bedrock-cli..."
echo "🌿 Rama: $CURRENT_BRANCH"
echo "📦 Paquete: $COMPOSER_PACKAGE"
echo "💬 Mensaje: $MESSAGE"
echo ""

# Verificar cambios pendientes
if [ -n "$(git status --porcelain)" ]; then
    echo "📝 Agregando cambios..."
    git add .
else
    echo "ℹ️  No hay cambios pendientes"
fi

# Commit y push
echo "📤 Haciendo commit y push..."
git commit -m "$MESSAGE" || echo "ℹ️  No hay cambios para commit"
git push

# Obtener hash del último commit
HASH=$(git rev-parse --short HEAD)
echo "🔗 Hash del commit: $HASH"

# Deploy global
echo "🌍 Actualizando paquete global..."
composer global update "$COMPOSER_PACKAGE:dev-$CURRENT_BRANCH#$HASH"

echo ""
echo "✅ Deploy local completado exitosamente!"
echo "🔗 Versión desplegada: $COMPOSER_PACKAGE:dev-$CURRENT_BRANCH#$HASH"
echo "🌍 Instalado globalmente"