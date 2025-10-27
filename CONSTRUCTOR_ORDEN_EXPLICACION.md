# 🎯 Constructor de Orden de Activación - Explicación Completa

## ¿Qué es y para qué sirve?

El **Constructor de Orden** (`plugins:order:build`) es una herramienta interactiva que te permite definir **en qué secuencia se activan los plugins de WordPress**.

### 🤔 ¿Por qué es importante el orden de activación?

En WordPress, el orden en que se activan los plugins **SÍ importa** cuando:

1. **Un plugin depende de otro**: Por ejemplo, `dt24-woocommerce` necesita que `woocommerce` esté activo primero
2. **Hooks y filtros**: Si un plugin modifica el comportamiento de otro, debe cargarse en el orden correcto
3. **Compatibilidad**: Algunos plugins pueden tener conflictos si se cargan en orden incorrecto

---

## 📋 ¿Cómo funciona?

### Paso 1: Acceder al Constructor
```bash
vendor/bin/bedrock plugins
# Seleccionar opción 7: Construir Orden
```

### Paso 2: Selección Interactiva
El sistema te muestra todos los plugins instalados en 2 columnas:

```
Plugins disponibles:

  [ 1] woocommerce              [ 6] dt24-reclamos
  [ 2] dt24-woocommerce         [ 7] user-role-editor
  [ 3] dt24-filtros-productos   [ 8] query-monitor
  [ 4] dt24-metodos-envio       [ 9] wp-mail-smtp
  [ 5] dt24-metodo-zoom
```

**Tú seleccionas** el orden en que quieres que se activen, uno por uno.

### Paso 3: Definir Dependencias
Para cada plugin que seleccionas, el sistema pregunta:

```
Plugin seleccionado: dt24-woocommerce
¿Tiene dependencias? (plugins que deben activarse antes):
  [1] No tiene dependencias
  [2] Sí, seleccionar dependencias
```

Si seleccionas "Sí", puedes elegir qué plugins deben activarse **ANTES** de este:

```
Selecciona las dependencias de dt24-woocommerce:
(Plugins que deben activarse ANTES de este)

  [1] woocommerce
  [2] dt24-metodos-envio
  [0] Terminar selección
```

### Paso 4: Cálculo Automático
El sistema **recalcula automáticamente** el orden final para respetar las dependencias.

**Ejemplo**:
- Seleccionaste: `dt24-woocommerce` (posición 1), `woocommerce` (posición 2)
- Definiste: `dt24-woocommerce` depende de `woocommerce`
- **Resultado automático**: `woocommerce` (posición 1), `dt24-woocommerce` (posición 2)

### Paso 5: Guardar Configuración
El sistema muestra el orden final y te pide un nombre de archivo:

```
Orden final calculado:

  [1] woocommerce
  [2] dt24-woocommerce ↳ woocommerce
  [3] dt24-metodos-envio
  [4] dt24-filtros-productos

Nombre del archivo [activation-order-20251027-143000.json]:
```

---

## 📁 ¿Dónde se guarda?

El archivo se guarda en: `config/plugins/<nombre>.json`

**Formato del archivo**:
```json
{
  "activation_order": {
    "woocommerce": 1,
    "dt24-woocommerce": 2,
    "dt24-metodos-envio": 3,
    "dt24-filtros-productos": 4
  },
  "dependencies": {
    "dt24-woocommerce": ["woocommerce"]
  },
  "created_at": "2025-10-27 14:30:00"
}
```

---

## 🎯 ¿Cómo usar el orden guardado?

### Opción 1: Desde el menú de Plugins
```bash
vendor/bin/bedrock plugins
# Opción 6: Orden de Activación
# Opción 3: Aplicar Orden
# Seleccionar el archivo guardado
```

### Opción 2: Comando directo
```bash
vendor/bin/bedrock plugins:order:activate config/plugins/mi-orden.json
```

---

## 🔄 Flujo Completo - Ejemplo Real

### Escenario: Tienes plugins dt24 que dependen de WooCommerce

**1. Ejecutar constructor**:
```bash
vendor/bin/bedrock plugins
# Opción 7: Construir Orden
```

**2. Seleccionar plugins en orden deseado**:
- Seleccionas: `woocommerce` → Sin dependencias
- Seleccionas: `dt24-woocommerce` → Depende de `woocommerce`
- Seleccionas: `dt24-metodos-envio` → Depende de `woocommerce`, `dt24-woocommerce`
- Presionas "s" para guardar

**3. Sistema calcula orden automático**:
```
[1] woocommerce
[2] dt24-woocommerce ↳ woocommerce
[3] dt24-metodos-envio ↳ woocommerce, dt24-woocommerce
```

**4. Guardas como**: `dt24-production.json`

**5. Aplicar en WordPress**:
```bash
vendor/bin/bedrock plugins:order:activate config/plugins/dt24-production.json
```

WordPress activará los plugins **exactamente en ese orden**, garantizando que las dependencias se carguen primero.

---

## 🎓 Ventajas del Constructor

### ✅ Sin Constructor (Manual)
- Tienes que recordar el orden correcto
- Activar plugins uno por uno en WP Admin
- Fácil cometer errores
- No hay registro del orden usado

### ✅ Con Constructor
- **Interactivo**: Seleccionas visualmente
- **Inteligente**: Calcula dependencias automáticamente
- **Reproducible**: Guardas configuraciones reutilizables
- **Documentado**: El JSON muestra claramente las dependencias
- **Automatizable**: Aplicas el orden con un comando

---

## 🚀 Casos de Uso

### 1. Entorno de Desarrollo
Creas un orden específico para desarrollo con plugins de debug:
```
activation-order-development.json
```

### 2. Entorno de Producción
Creas un orden optimizado para producción:
```
activation-order-production.json
```

### 3. Testing
Creas órdenes diferentes para probar compatibilidad:
```
activation-order-test-1.json
activation-order-test-2.json
```

### 4. Migración
Documentas el orden exacto usado en el servidor antiguo para replicarlo en el nuevo.

---

## 🔧 Algoritmo de Dependencias

El sistema usa un algoritmo iterativo (máximo 100 iteraciones) que:

1. **Detecta conflictos**: Si un plugin está antes de su dependencia
2. **Reordena automáticamente**: Intercambia posiciones
3. **Renumera secuencialmente**: Asigna posiciones 1, 2, 3, etc.
4. **Previene loops infinitos**: Límite de 100 iteraciones

**Ejemplo de reordenamiento**:
```
Orden inicial (incorrecto):
  dt24-woocommerce: 1
  woocommerce: 2

Dependencia detectada:
  dt24-woocommerce depende de woocommerce

Orden final (correcto):
  woocommerce: 1
  dt24-woocommerce: 2
```

---

## 📊 Comparación con Otras Opciones

| Característica | Constructor | Guardar Orden Actual | Manual en WP Admin |
|----------------|-------------|----------------------|--------------------|
| Interactivo | ✅ | ❌ | ✅ |
| Define dependencias | ✅ | ❌ | ❌ |
| Calcula orden automático | ✅ | ❌ | ❌ |
| Reproducible | ✅ | ✅ | ❌ |
| Documentado | ✅ | ⚠️ | ❌ |
| Rápido | ✅ | ✅ | ❌ |

---

## 💡 Tips y Mejores Prácticas

1. **Nombra descriptivamente**: `dt24-production.json`, `testing-woo-only.json`
2. **Documenta dependencias**: Siempre define las dependencias conocidas
3. **Versiona los archivos**: Commitea los JSON al repositorio
4. **Prueba antes de producción**: Usa el orden en desarrollo primero
5. **Mantén múltiples configuraciones**: Una por entorno

---

## 🐛 Troubleshooting

### Problema: "No hay plugins instalados"
**Solución**: Verifica que existan plugins en `web/app/plugins/`

### Problema: El orden no se aplica correctamente
**Solución**: Revisa que el JSON tenga el formato correcto y que los nombres de plugins coincidan exactamente

### Problema: Dependencias circulares
**Solución**: El algoritmo detecta esto automáticamente (máx 100 iteraciones). Revisa tus dependencias.

---

## 🎯 Resumen en 3 Puntos

1. **Constructor = Herramienta interactiva** para definir orden de activación de plugins
2. **Define dependencias** y el sistema calcula el orden correcto automáticamente
3. **Guarda configuraciones reutilizables** en JSON para aplicar con un comando

---

**¿Necesitas más ayuda?** Ejecuta el comando y sigue las instrucciones en pantalla. Es muy intuitivo! 🚀
