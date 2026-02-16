# Reglas del Espacio de Trabajo

## Idioma
1. **Español Obligatorio (CRÍTICO)**:
   - Toda comunicación con el usuario debe ser estrictamente en español.
   - Todos los artefactos de gestión (`task.md`, `implementation_plan.md`, `walkthrough.md`, etc.) deben generarse EXCLUSIVAMENTE en español. No se permite el uso de inglés en estos documentos.
   - Los comentarios en el código y la documentación generada deben estar en español.

## Protocolo de Trabajo (Strict Mode)
1. **Adherencia al Plan**:
   - NO desviarse del `implementation_plan.md` aprobado sin consultar primero.
   - Cualquier nueva solicitud que cambie el alcance requiere actualizar el plan y solicitar re-aprobación (PLANNING mode).

2. **Gestión de Tareas**:
   - Mantener `task.md` actualizado en tiempo real.
   - Marcar tareas como `[/]` al iniciar y `[x]` al terminar.

3. **Manejo de Errores**:
   - Si una herramienta falla, analizar el error, proponer solución y NO reintentar ciegamente.

## Entorno de Ejecución
1. **Terminal WSL Nativa**:
   - Usar DIRECTAMENTE la terminal de Ubuntu WSL para todos los comandos.
   - NO usar prefijos como `wsl` o wrappers como `bash -c`.
   - Los comandos deben ser puramente compatibles con Linux/Bash.
   - El path de trabajo es `/home/greicy/proyectos/mi-proyecto`.

## Estilo de Código
1. **Estilo de Código**:
   - **Bash**: Seguir estándares POSIX o Bash explícito. Usar nombres de variables descriptivos.
   - **Makefiles**: Mantener modularidad.
