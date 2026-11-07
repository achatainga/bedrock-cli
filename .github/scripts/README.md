# Emergency Rollback Scripts

## rollback.sh

Script de emergencia para revertir `origin/develop` a un commit estable anterior.

### Uso

```bash
# Editar REMOTE_COMMIT con el hash del commit estable
nano rollback.sh

# Ejecutar
./rollback.sh
```

### Cuándo usar

- Después de un push que rompe producción
- Cuando múltiples commits tienen errores críticos
- Para restaurar rápidamente a último estado funcional

### Importante

- **NO afecta tu repositorio local** - solo revierte el remote
- Tu código local permanece intacto
- Permite continuar trabajando en fixes sin presión

## backup-info-example.txt

Ejemplo de archivo de backup que documenta:
- Estado del remote antes del push
- Estado del local
- Lista de commits a pushear
- Comando de rollback manual

### Crear antes de push arriesgado

```bash
# Guardar info de commits
git log origin/develop..HEAD --oneline > .git-backup-info
echo "ROLLBACK: git push origin +$(git rev-parse origin/develop):develop --force" >> .git-backup-info
```

---

**Lección aprendida**: Estos scripts salvaron el proyecto después de múltiples pushes con errores de sintaxis (imports duplicados, traits faltantes).
