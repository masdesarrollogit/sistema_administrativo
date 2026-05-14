<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Riesgo de no aprobar el curso</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: #dc2626; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">WebCurso · Aviso urgente</h1>
        <p style="margin: 5px 0 0; font-size: 14px; opacity: 0.95;">
            Estás en riesgo de no aprobar el curso
        </p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Hola <strong>{{ $alumno->nombre_completo }}</strong>,</p>

        <p>
            Te escribimos porque ya ha transcurrido más de la mitad del tiempo de tu curso
            <strong>{{ $cursoNombre }}</strong> y aún no has alcanzado los <strong>50 puntos</strong>
            necesarios para aprobar.
        </p>

        <div style="background: #FEF2F2; border: 2px solid #dc2626; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <h3 style="margin: 0 0 12px; color: #b91c1c;">Tu situación actual</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #b91c1c; width: 200px;">Puntuación acumulada:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        @if($notaTotal !== null && $notaMax !== null)
                            <strong>{{ rtrim(rtrim(number_format((float)$notaTotal, 2, ',', ''), '0'), ',') }}</strong>
                            <span style="color: #6B7280;"> de {{ rtrim(rtrim(number_format((float)$notaMax, 2, ',', ''), '0'), ',') }} puntos</span>
                            @if($notaPorcentaje !== null)
                                <span style="color: #b91c1c;"> ({{ number_format($notaPorcentaje, 1) }}%)</span>
                            @endif
                        @else
                            <span style="color: #6B7280;">—</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #b91c1c;">Días para finalizar:</td>
                    <td style="padding: 6px 0; font-size: 16px;"><strong>{{ $diasRestantes }} días</strong></td>
                </tr>
                @if($pctTiempoTranscurrido !== null)
                    <tr>
                        <td style="padding: 6px 0; font-weight: bold; color: #b91c1c;">Tiempo transcurrido:</td>
                        <td style="padding: 6px 0; font-size: 16px;">{{ number_format($pctTiempoTranscurrido, 0) }}%</td>
                    </tr>
                @endif
            </table>
        </div>

        <p>
            <strong>Para que el curso pueda bonificarse ante FUNDAE debes aprobarlo</strong>: alcanzar 50 puntos
            entre tests y actividades, y luego realizar el cuestionario final. Aún estás a tiempo, pero
            necesitas actuar ya.
        </p>

        <p>
            Cada actividad que dejes pendiente reduce tus opciones. Te recomendamos retomar el curso ahora mismo
            y centrarte en las tareas que más puntos aportan.
        </p>

        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $courseUrl }}" style="display: inline-block; background: #dc2626; color: white; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px;">
                Acceder al curso ahora
            </a>
        </p>

        <p>
            Si necesitas apoyo (dudas con el contenido, problemas técnicos, recuperar credenciales),
            responde a este correo o escríbenos a
            <a href="mailto:administracion@webcurso.es" style="color: #4F46E5;">administracion@webcurso.es</a>.
            Tu tutor también te puede contactar en los próximos días.
        </p>

        <p>
            Un saludo,<br>
            <strong>Equipo WebCurso</strong><br>
            <span style="font-size: 13px; color: #6B7280;">{{ config('candidatos.contacto.email') }} | {{ config('candidatos.contacto.telefono') }}</span>
        </p>
    </div>
</body>
</html>
