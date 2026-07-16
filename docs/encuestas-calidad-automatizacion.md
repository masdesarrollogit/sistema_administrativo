# Encuestas de Calidad — Automatización con Power Automate + IMAP

Guía para activar la **entrada automática** de respuestas del cuestionario de calidad
FUNDAE (Microsoft Forms) al Panel, **sin coste y sin Azure**.

> El código ya está listo (comando `encuestas-calidad:leer-imap`, cron cada 10 min,
> validación por token e idempotencia). Solo falta la configuración de este documento.

## Cómo funciona (resumen)

```
Alumno rellena el Form (fin del curso en Moodle)
        │
        ▼
Microsoft Forms ──trigger──▶ Power Automate (flujo gratis, conectores estándar)
                                 │  "Obtener detalles de la respuesta"
                                 │  compone un correo con un bloque JSON
                                 ▼
                        Office 365 Outlook: "Enviar un correo (V2)"
                        → saldoswebcurso@gmail.com
                                 │
                                 ▼
        Panel ──cron cada 10 min──▶ encuestas-calidad:leer-imap
              lee el correo por IMAP → valida token → guarda la respuesta
              (vincula alumno por email/nombre y resuelve el curso por fecha)
```

La acción HTTP directa de Power Automate es **Premium**; por eso usamos el correo como
puente y lo leemos por IMAP (gratis).

---

## Paso 1 — Gmail (buzón `saldoswebcurso@gmail.com`)

1. Activar **IMAP**: Gmail → ⚙️ Configuración → **Reenvío y correo POP/IMAP** → *Habilitar IMAP*.
2. Activar la **verificación en 2 pasos** en la cuenta de Google.
3. Crear una **contraseña de aplicación** (App Password): Cuenta de Google → Seguridad →
   Contraseñas de aplicaciones → generar una para "Correo". Guarda los 16 caracteres.

---

## Paso 2 — Variables de entorno (`.env` de PRODUCCIÓN)

```env
# Secreto compartido (invéntalo, largo). Debe coincidir con el del JSON del flujo.
ENCUESTA_CALIDAD_TOKEN=pon-aqui-un-secreto-largo-y-unico

# App Password de Gmail (paso 1)
ENCUESTA_IMAP_PASSWORD=xxxxxxxxxxxxxxxx

# Activa la lectura IMAP (solo en producción)
ENCUESTA_CALIDAD_IMAP_ENABLED=true

# (Opcional) filtro extra: dirección Office 365 desde la que envía el flujo
ENCUESTA_CALIDAD_REMITENTE=

# Estos ya traen valor por defecto; cámbialos solo si usas otra cuenta:
ENCUESTA_IMAP_HOST=imap.gmail.com
ENCUESTA_IMAP_PORT=993
ENCUESTA_IMAP_ENCRYPTION=ssl
ENCUESTA_IMAP_USERNAME=saldoswebcurso@gmail.com
```

Tras editar el `.env`, refresca caché de config si la usas:
`php artisan config:clear` (o `config:cache`).

> En desarrollo se queda `ENCUESTA_CALIDAD_IMAP_ENABLED=false` para no tocar el buzón real.

---

## Paso 3 — El flujo en Power Automate

En **make.powerautomate.com**, con la cuenta Microsoft donde vive el Formulario:

1. **Crear → Flujo de nube automatizado**. Nombre: *Encuesta calidad → Panel WebCurso*.
2. Desencadenador: **Microsoft Forms → "Cuando se envía una respuesta nueva"**.
   En *Id. del formulario* elige el cuestionario de calidad. → **Crear**.
3. **+ Nuevo paso → Microsoft Forms → "Obtener los detalles de la respuesta"**.
   *Id. del formulario* = el mismo; *Id. de respuesta* = token dinámico **"Id. de respuesta"**
   (Power Automate lo envuelve en un "Aplicar a cada uno"; es correcto).
4. **+ Nuevo paso → Office 365 Outlook → "Enviar un correo (V2)"** (conector estándar, gratis):
   - **Para:** `saldoswebcurso@gmail.com`
   - **Asunto:** `[ENCUESTA-CALIDAD] nueva respuesta`
   - **Opciones avanzadas → ¿Es HTML? → No** (texto plano; importante para que el JSON llegue limpio).
   - **Cuerpo:** pega el bloque de abajo y sustituye cada `@{...}` por el **token dinámico**
     de la pregunta correspondiente (del paso 3).

```
===ENCUESTA-CALIDAD-JSON===
{
  "token": "el-mismo-valor-de-ENCUESTA_CALIDAD_TOKEN",
  "forms_id": "@{Id. de respuesta}",
  "alumno_nombre": "@{Nombre y Apellido del Alumno}",
  "alumno_email": "@{Email alumno}",
  "fecha": "@{Fecha de cumplimentación}",
  "numero_accion": "@{Nº Acción}",
  "numero_grupo": "@{Nº Grupo}",
  "satisfaccion_general": "@{10. Grado de satisfacción general con el curso}",
  "observaciones": "@{Si desea realizar cualquier sugerencia u observación}"
}
===FIN===
```

5. **Guardar**.

### Campos del JSON

| Clave | Imprescindible | Para qué sirve |
|---|---|---|
| `token` | ✅ | Seguridad. Debe coincidir con `ENCUESTA_CALIDAD_TOKEN`. |
| `forms_id` | ✅ | Idempotencia (no duplicar la misma respuesta). |
| `alumno_email` | ✅ | Vincular con el alumno del Panel. |
| `alumno_nombre` | Recomendado | Fallback de vínculo si el email no coincide. |
| `fecha` | ✅ | Año del filtro + resolver a qué curso pertenece. |
| `satisfaccion_general` | ✅ | La métrica principal (1–4). |
| `observaciones` | Recomendado | Ver las sugerencias/quejas. |
| `numero_accion`, `numero_grupo` | Opcional | Si el Form los trae, prioriza ese curso. |
| `item_01` … `item_19` | Opcional | Guardar cada pregunta 1.1…9.5. Se añaden como `"item_01": "@{...}"`. |

---

## Paso 4 — Verificación

1. Envía una **respuesta de prueba** en el Formulario.
2. Comprueba que **llega el correo** a `saldoswebcurso@gmail.com` con el asunto `[ENCUESTA-CALIDAD]`.
3. Ejecuta el comando a mano (o espera al cron de 10 min):
   ```bash
   php artisan encuestas-calidad:leer-imap          # producción
   # (en Docker) docker compose exec laravel.test php artisan encuestas-calidad:leer-imap
   ```
4. La respuesta debe aparecer en **`/webcurso/encuestas-calidad`** con origen *Power Automate*.

El cron ya está programado cada 10 minutos (lo ejecuta el contenedor `scheduler`).

---

## Solución de problemas

| Síntoma | Causa probable |
|---|---|
| El comando dice "⏸ Lectura IMAP DESACTIVADA" | Falta `ENCUESTA_CALIDAD_IMAP_ENABLED=true`. |
| "token inválido" en el log | El `token` del JSON no coincide con `ENCUESTA_CALIDAD_TOKEN`. |
| No conecta a IMAP | App Password incorrecta o IMAP no habilitado en Gmail. |
| "sin bloque JSON" | El correo se envió como HTML: pon **¿Es HTML? → No**. |
| El correo llega pero no se procesa | Revisa que el asunto contenga `[ENCUESTA-CALIDAD]`. |

> Reprocesar el mismo correo no duplica: la respuesta es idempotente por `forms_id`.
