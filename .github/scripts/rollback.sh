#!/bin/bash
# ROLLBACK SCRIPT - Revert origin/develop to previous state
# Usage: ./rollback.sh

set -e

REMOTE_COMMIT="07aceed"
BRANCH="develop"

echo "🔄 ROLLBACK: Revirtiendo origin/$BRANCH a $REMOTE_COMMIT"
echo "⚠️  Esto NO afectará tu repositorio local"
echo ""
read -p "¿Continuar? (y/n): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📡 Forzando push de $REMOTE_COMMIT a origin/$BRANCH..."
    git push origin +$REMOTE_COMMIT:$BRANCH --force
    echo "✅ Rollback completado"
    echo "💾 Tu código local sigue en de94e01 (intacto)"
else
    echo "❌ Rollback cancelado"
fi
