<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Hace tiempo que no entras a tu curso</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: #4F46E5; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">WebCurso</h1>
        <p style="margin: 5px 0 0; font-size: 14px; opacity: 0.9;">Hace {{ $diasInactivo }} días que no entras al curso</p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Hola <strong>{{ $alumno->nombre_completo }}</strong>,</p>

        <p>
            Han pasado <strong>{{ $diasInactivo }} días</strong> desde tu último acceso a
            <strong>{{ $cursoNombre }}</strong> y queremos asegurarnos de que no pierdas el hilo.
        </p>

        <div style="background: #EEF2FF; border: 2px solid #4F46E5; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <h3 style="margin: 0 0 12px; color: #4F46E5;">Tu progreso actual</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #4F46E5; width: 180px;">Puntuación acumulada:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        @if($notaTotal !== null && $notaMax !== null)
                            <strong>{{ rtrim(rtrim(number_format((float)$notaTotal, 2, ',', ''), '0'), ',') }}</strong>
                            <span style="color: #6B7280;"> de {{ rtrim(rtrim(number_format((float)$notaMax, 2, ',', ''), '0'), ',') }} puntos</span>
                            @if($notaPorcentaje !== null)
                                <span style="color: #4F46E5;"> ({{ number_format($notaPorcentaje, 1) }}%)</span>
                            @endif
                        @else
                            <span style="color: #6B7280;">—</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #4F46E5;">Días para finalizar:</td>
                    <td style="padding: 6px 0; font-size: 16px;"><strong>{{ $diasRestantes }} días</strong></td>
                </tr>
            </table>
        </div>

        <p>
            Para que el curso se considere superado y se aplique la bonificación FUNDAE necesitas alcanzar
            al menos <strong>50 puntos</strong> y completar el cuestionario final. Aún estás a tiempo, pero
            cada día sin entrar al aula reduce tus opciones.
        </p>

        <p style="text-align: center; margin: 20px 0;">
            <a href="{{ $courseUrl }}" style="display: inline-block; background: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Acceder al curso ahora
            </a>
        </p>

        <p>
            Si tienes algún problema técnico, no recuerdas tus credenciales o necesitas apoyo para retomar el ritmo,
            responde a este correo o escríbenos a
            <a href="mailto:administracion@webcurso.es" style="color: #4F46E5;">administracion@webcurso.es</a>.
        </p>

        <p>
            Un saludo,<br>
            <strong>Equipo WebCurso</strong><br>
            <span style="font-size: 13px; color: #6B7280;">{{ config('candidatos.contacto.email') }} | {{ config('candidatos.contacto.telefono') }}</span>
        </p>
    </div>
</body>
</html>
