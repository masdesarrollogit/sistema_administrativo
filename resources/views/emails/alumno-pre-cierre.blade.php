<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Último aviso · tu curso cierra pronto</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: #b45309; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">🚨 WebCurso · Último aviso</h1>
        <p style="margin: 5px 0 0; font-size: 14px; opacity: 0.95;">
            Tu curso cierra en {{ $horasRestantes }} horas
        </p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Hola <strong>{{ $alumno->nombre_completo }}</strong>,</p>

        <p>
            Te escribimos porque <strong>{{ $cursoNombre }}</strong> está a punto de cerrar
            y aún no has completado el <strong>cuestionario final</strong>, que es indispensable
            para que el curso quede aprobado y se pueda bonificar ante FUNDAE.
        </p>

        <div style="background: #FEF3C7; border: 2px solid #b45309; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <h3 style="margin: 0 0 12px; color: #92400e;">Tu situación</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #92400e; width: 200px;">Horas para cerrar:</td>
                    <td style="padding: 6px 0; font-size: 18px;"><strong>{{ $horasRestantes }}h</strong></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #92400e;">Puntuación acumulada:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        @if($notaTotal !== null && $notaMax !== null)
                            <strong>{{ rtrim(rtrim(number_format((float)$notaTotal, 2, ',', ''), '0'), ',') }}</strong>
                            <span style="color: #6B7280;"> de {{ rtrim(rtrim(number_format((float)$notaMax, 2, ',', ''), '0'), ',') }} puntos</span>
                            @if($notaPorcentaje !== null)
                                <span style="color: #92400e;"> ({{ number_format($notaPorcentaje, 1) }}%)</span>
                            @endif
                        @else
                            <span style="color: #6B7280;">—</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #92400e;">Cuestionario final:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        <span style="color: #b91c1c; font-weight: bold;">⚠ Pendiente</span>
                    </td>
                </tr>
            </table>
        </div>

        @if($haAlcanzadoUmbral)
            <p style="background: #d1fae5; border-left: 4px solid #059669; padding: 12px; margin: 12px 0; border-radius: 4px;">
                <strong>✓ Ya alcanzaste los 50 puntos.</strong> Solo te queda hacer el cuestionario final
                para que el curso quede aprobado.
            </p>
        @else
            <p style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 12px; margin: 12px 0; border-radius: 4px;">
                <strong>⚠ Aún no llegas a 50 puntos.</strong> Necesitas completar más tests y actividades
                antes de que Moodle te habilite el cuestionario final.
            </p>
        @endif

        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $courseUrl }}" style="display: inline-block; background: #b45309; color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                Acceder al curso AHORA
            </a>
        </p>

        <p>
            Si tienes algún problema o necesitas apoyo, responde a este correo o escríbenos a
            <a href="mailto:administracion@webcurso.es" style="color: #4F46E5;">administracion@webcurso.es</a>.
            Estamos disponibles para ayudarte en estas últimas horas.
        </p>

        <p>
            Un saludo,<br>
            <strong>Equipo WebCurso</strong><br>
            <span style="font-size: 13px; color: #6B7280;">{{ config('candidatos.contacto.email') }} | {{ config('candidatos.contacto.telefono') }}</span>
        </p>
    </div>
</body>
</html>
