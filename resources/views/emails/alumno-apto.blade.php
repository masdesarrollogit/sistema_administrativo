<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>¡Has aprobado el curso!</title></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: #059669; color: white; padding: 24px 20px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 26px;">🎉 ¡Enhorabuena!</h1>
        <p style="margin: 8px 0 0; font-size: 15px; opacity: 0.95;">
            Has aprobado tu curso con éxito
        </p>
    </div>

    <div style="background: #f9fafb; padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">

        <p>Hola <strong>{{ $alumno->nombre_completo }}</strong>,</p>

        <p>
            Nos complace confirmarte que has <strong>completado y aprobado</strong>
            <strong>{{ $cursoNombre }}</strong>. Has alcanzado los puntos requeridos
            y realizado el cuestionario final. ¡Excelente trabajo!
        </p>

        <div style="background: #ECFDF5; border: 2px solid #059669; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <h3 style="margin: 0 0 12px; color: #065f46;">Resultado final</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #065f46; width: 200px;">Puntuación final:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        @if($notaTotal !== null && $notaMax !== null)
                            <strong style="color: #047857;">{{ rtrim(rtrim(number_format((float)$notaTotal, 2, ',', ''), '0'), ',') }}</strong>
                            <span style="color: #6B7280;"> de {{ rtrim(rtrim(number_format((float)$notaMax, 2, ',', ''), '0'), ',') }} puntos</span>
                            @if($notaPorcentaje !== null)
                                <span style="color: #047857;"> ({{ number_format($notaPorcentaje, 1) }}%)</span>
                            @endif
                        @else
                            <span style="color: #6B7280;">—</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #065f46;">Cuestionario final:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        <span style="color: #047857; font-weight: bold;">✓ Realizado</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; font-weight: bold; color: #065f46;">Estado:</td>
                    <td style="padding: 6px 0; font-size: 16px;">
                        <span style="color: #047857; font-weight: bold;">APROBADO</span>
                    </td>
                </tr>
            </table>
        </div>

        <p>
            Con esta aprobación tu formación queda <strong>oficialmente cerrada</strong> y
            cumple los requisitos para la <strong>bonificación FUNDAE</strong>. WebCurso
            se encarga del trámite administrativo correspondiente.
        </p>

        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $courseUrl }}" style="display: inline-block; background: #059669; color: white; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Ver mi curso aprobado
            </a>
        </p>

        <p>
            Gracias por confiar en WebCurso para tu formación. Si en el futuro deseas
            realizar otro curso, no dudes en contactarnos.
        </p>

        <p>
            ¡Muchas felicidades!<br>
            <strong>Equipo WebCurso</strong><br>
            <span style="font-size: 13px; color: #6B7280;">{{ config('candidatos.contacto.email') }} | {{ config('candidatos.contacto.telefono') }}</span>
        </p>
    </div>
</body>
</html>
