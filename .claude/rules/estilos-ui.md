# Estilos y convenciones de UI

> TODO: Este archivo se completara cuando se definan las convenciones de UI del proyecto.

## Contenido previsto

- Paleta de colores y tema (Tailwind CSS)
- Soporte dark mode
- Estructura de componentes Blade/Livewire reutilizables
- Patrones de tablas, modales, formularios y paginacion
- Convenciones de responsividad
- Iconografia y tipografia

## Sidebar lateral (decidido 2026-05-13)

- El sidebar (`resources/views/components/sidebar-layout.blade.php`) **siempre permanece abierto** con ancho fijo `w-64`.
- No tiene boton de colapso ni estado contraido (`w-20`). Decision: la usuaria prefiere todas las etiquetas visibles permanentemente.
- El estado Alpine `open` se inicializa en `true` y no se modifica en runtime; los `x-show="open"` quedan por compatibilidad pero siempre evaluan true.

## Badges de tipo de curso (definido 2026-05-24, vista `/webcurso/alumnos`)

Cada tipo de curso del historial del alumno usa un color distintivo:

| Tipo | Clases Tailwind | Significado |
|---|---|---|
| **FUNDAE** | `bg-blue-100 text-blue-700` | Grupo formativo creado en el Panel |
| **FUNDAE imp.** | `bg-emerald-100 text-emerald-700` | Participación bonificada importada del XLS FUNDAE |
| **Autónomo** | `bg-amber-100 text-amber-700` | Matrícula autónoma 2x1 (sin bonificación FUNDAE) |
| **Legacy** | `bg-violet-100 text-violet-700` | Curso del historial webcourses2014 (ya enriquecido) |
| **No bonificado** | `bg-orange-100 text-amber-700 border border-orange-200` | Alumno autónomo/privado/repaso marcado manualmente para no enriquecer |

**Píldora acción/grupo** (al lado del badge tipo): `bg-indigo-100 text-indigo-700 border border-indigo-200` con `font-mono font-semibold`. Color indigo de marca, uniforme para todos los tipos, independiente del color del tipo.

## Resaltado del año en curso (modal Historial, 2026-05-24)

El modal Historial agrupa cursos por año y resalta el año actual:

- **Bloque año en curso** (`$anio === date('Y')`):
  - Wrapper: `rounded-lg border-l-4 border-emerald-500 bg-emerald-50/40 p-3 mb-3`
  - Header: texto `text-emerald-700 uppercase tracking-wide` + punto pulsante `bg-emerald-500 animate-pulse` + sufijo " (EN CURSO)" + contador
- **Bloque años pasados**:
  - Wrapper: `rounded-lg border-l-2 border-gray-300 bg-gray-50/30 p-3 mb-3`
  - Header: texto `text-gray-500 uppercase tracking-wide` + contador
  - Tabla interna con `opacity-90` para atenuar

## Tablas Livewire — convenciones generales

- Tabla principal: `min-w-full divide-y divide-gray-200`, header `bg-gray-50 text-xs font-medium text-gray-500 uppercase`
- Filas: `hover:bg-gray-50`, padding `px-6 py-4` (`px-4 py-3` en tablas anidadas/modales)
- Texto principal: `text-sm font-medium text-gray-900`; metadata: `text-xs text-gray-500`
- Empty state: `colspan` igual al número de columnas, `px-6 py-12 text-center text-gray-500`
